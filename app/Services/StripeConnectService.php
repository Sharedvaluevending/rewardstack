<?php

namespace App\Services;

use App\Models\User;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Helpers\DatabaseHelper;

class StripeConnectService
{
    protected ?StripeClient $stripe = null;

    /**
     * NOTE: Do NOT typehint StripeClient here.
     * Laravel will auto-resolve it with no API key, causing "No API key provided" failures.
     */
    public function __construct($stripe = null)
    {
        if ($stripe instanceof StripeClient) {
            $this->stripe = $stripe;
        } elseif ($this->isEnabled()) {
            $this->stripe = new StripeClient(config('services.stripe.secret'));
        }
    }

    /**
     * Check if Stripe Connect is enabled and configured
     * 
     * Note: Client ID is optional for Express accounts created via API.
     * It's only required if using OAuth flows.
     */
    public function isEnabled(): bool
    {
        return config('stripe.connect.enabled') 
            && !empty(config('services.stripe.secret'));
            // Client ID is optional for Express accounts - only needed for OAuth
    }

    /**
     * Create a Stripe Connect Express account for a user
     */
    public function createConnectAccount(User $user): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $country = strtoupper((string) config('stripe.connect.default_country', 'CA'));
            if (!in_array($country, ['CA', 'US'], true)) {
                $country = 'CA';
            }

            $account = $this->stripe->accounts->create([
                'type' => 'express',
                'country' => $country,
                'email' => $user->email,
                // Referrers can be individuals (not businesses). This reduces friction in onboarding and
                // avoids Stripe treating the user like they must have a business website.
                'business_type' => 'individual',
                // Prefill platform website/product so Stripe doesn't block onboarding on "website required".
                // (User may still be asked for additional details depending on country/requirements.)
                'business_profile' => [
                    'url' => config('app.url') ?: null,
                    'product_description' => 'Referral commissions payouts',
                ],
                'capabilities' => [
                    // Safe default: request both. Stripe will guide the user through any required steps.
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'referral_code' => $user->referral_code,
                ],
            ]);

            // Internal field: do not rely on mass-assignable fillable fields.
            $user->forceFill(['stripe_connect_id' => $account->id])->save();

            return $account->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect account creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate onboarding link for user to complete Stripe setup
     */
    public function createOnboardingLink(User $user, string $returnUrl, string $refreshUrl): ?string
    {
        if (!$this->isEnabled() || !$user->stripe_connect_id) {
            return null;
        }

        try {
            $link = $this->stripe->accountLinks->create([
                'account' => $user->stripe_connect_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return $link->url;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect onboarding link failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate dashboard link for user to manage their Stripe account
     */
    public function createDashboardLink(User $user): ?string
    {
        if (!$this->isEnabled() || !$user->stripe_connect_id) {
            return null;
        }

        try {
            $link = $this->stripe->accounts->createLoginLink($user->stripe_connect_id);
            return $link->url;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect dashboard link failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if a user's Stripe account is fully onboarded
     */
    public function checkOnboardingStatus(User $user): bool
    {
        if (!$this->isEnabled() || !$user->stripe_connect_id) {
            return false;
        }

        try {
            $account = $this->stripe->accounts->retrieve($user->stripe_connect_id);
            
            $isOnboarded = $account->details_submitted && $account->payouts_enabled;
            
            if ($isOnboarded !== $user->stripe_connect_onboarded) {
                // Internal field: do not rely on mass-assignable fillable fields.
                $user->forceFill(['stripe_connect_onboarded' => $isOnboarded])->save();
            }

            return $isOnboarded;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect status check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Transfer funds to a connected account with idempotency key
     */
    public function createPayout(User $user, float $amount, string $description = 'Referral Commission', string $idempotencyKey = null): ?array
    {
        if (!$this->isEnabled() || !$user->stripe_connect_id || !$user->stripe_connect_onboarded) {
            return null;
        }

        try {
            $transferParams = [
                'amount' => (int) ($amount * 100), // Convert to cents
                'currency' => config('stripe.connect.currency', 'cad'),
                'destination' => $user->stripe_connect_id,
                'description' => $description,
                'metadata' => [
                    'user_id' => $user->id,
                    'referral_code' => $user->referral_code,
                ],
            ];

            // Add idempotency key if provided
            $options = [];
            if ($idempotencyKey) {
                $options['idempotency_key'] = $idempotencyKey;
            }

            // Create transfer to connected account
            $transfer = $this->stripe->transfers->create($transferParams, $options);

            return [
                'success' => true,
                'transfer_id' => $transfer->id,
                'amount' => $amount,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect payout failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process automatic payouts for all eligible referrers
     * 
     * Crash-safe flow with idempotency:
     * 1. Check if user already has processing/completed payout (idempotency check)
     * 2. Create ReferralPayout with status=processing inside transaction
     * 3. Lock and attach commissions to payout (status=processing, payout_reference) inside transaction
     * 4. Commit transaction
     * 5. Call Stripe transfer with idempotency key "ref_payout:{payout_id}"
     * 6. On success: mark payout completed, store transfer_id, mark commissions paid
     * 7. On failure: mark payout failed, revert commissions to approved
     */
    public function processAutomaticPayouts(): array
    {
        if (!$this->isEnabled()) {
            return ['processed' => 0, 'message' => 'Stripe Connect not enabled'];
        }

        $minimum = config('stripe.connect.auto_payout_minimum', 25);
        $processed = 0;
        $failed = 0;
        $skipped = 0;

        // Get users with Stripe Connect who have approved commissions
        $users = User::whereNotNull('stripe_connect_id')
            ->where('stripe_connect_onboarded', true)
            ->where('payout_method', 'stripe')
            ->get();

        foreach ($users as $user) {
            try {
                // IDEMPOTENCY CHECK FIRST: Skip if user already has a recent completed or processing payout
                // Check for completed payout first (most common case)
                $existingCompletedPayout = ReferralPayout::where('user_id', $user->id)
                    ->where('status', ReferralPayout::STATUS_COMPLETED)
                    ->where('method', 'stripe')
                    ->where('created_at', '>=', now()->subDay())
                    ->first();

                if ($existingCompletedPayout) {
                    $skipped++;
                    continue;
                }

                // Check for any processing payout (regardless of amount, to prevent concurrent attempts)
                $existingProcessingPayout = ReferralPayout::where('user_id', $user->id)
                    ->where('status', ReferralPayout::STATUS_PROCESSING)
                    ->where('method', 'stripe')
                    ->where('created_at', '>=', now()->subDay())
                    ->first();

                if ($existingProcessingPayout) {
                    // If processing but no transaction_id, check if commissions are still processing
                    if (!$existingProcessingPayout->transaction_id) {
                        $processingCommissions = ReferralCommission::where('referrer_id', $user->id)
                            ->where('status', ReferralCommission::STATUS_PROCESSING)
                            ->where('payout_reference', "payout:{$existingProcessingPayout->id}")
                            ->count();

                        // If commissions are still processing, skip (payout in progress)
                        if ($processingCommissions > 0) {
                            $skipped++;
                            continue;
                        }
                    } else {
                        // Has transaction_id but still processing - skip to be safe
                        $skipped++;
                        continue;
                    }
                }

                // Calculate available balance (only approved commissions)
                $available = ReferralCommission::where('referrer_id', $user->id)
                    ->where('status', ReferralCommission::STATUS_APPROVED)
                    ->sum('commission_amount');

                if ($available < $minimum) {
                    continue;
                }


                // STEP 1: Create payout record with status=processing inside transaction
                // STEP 2: Lock commissions and mark as processing with payout_reference
                $payout = DatabaseHelper::transactionWithRetry(function () use ($user, $available) {
                    // Create payout record
                    $payout = ReferralPayout::create([
                        'user_id' => $user->id,
                        'amount' => $available,
                        'method' => 'stripe',
                        'destination' => $user->stripe_connect_id,
                        'status' => ReferralPayout::STATUS_PROCESSING,
                        'requested_at' => now(),
                    ]);

                    // Lock and mark commissions as processing
                    // Use lockForUpdate to prevent concurrent modifications
                    ReferralCommission::where('referrer_id', $user->id)
                        ->where('status', ReferralCommission::STATUS_APPROVED)
                        ->lockForUpdate()
                        ->update([
                            'status' => ReferralCommission::STATUS_PROCESSING,
                            'payout_reference' => "payout:{$payout->id}",
                            'payout_method' => 'stripe',
                        ]);

                    return $payout;
                });

                // STEP 3: After transaction commit, call Stripe transfer with idempotency key
                $idempotencyKey = "ref_payout:{$payout->id}";
                $result = $this->createPayout($user, $available, 'Monthly Referral Commission', $idempotencyKey);

                // STEP 4: Handle success or failure
                // createPayout() returns null when the user's Connect account is no longer eligible
                // (e.g. onboarding revoked between query and transfer). Treat null as failure.
                if (is_array($result) && ($result['success'] ?? false)) {
                    // Success: Mark payout completed and commissions paid
                    DatabaseHelper::transactionWithRetry(function () use ($payout, $result, $user) {
                        $payout->update([
                            'status' => ReferralPayout::STATUS_COMPLETED,
                            'transaction_id' => $result['transfer_id'],
                            'processed_at' => now(),
                        ]);

                        // Mark commissions as paid
                        ReferralCommission::where('referrer_id', $user->id)
                            ->where('status', ReferralCommission::STATUS_PROCESSING)
                            ->where('payout_reference', "payout:{$payout->id}")
                            ->update([
                                'status' => ReferralCommission::STATUS_PAID,
                                'paid_at' => now(),
                                'payout_reference' => $result['transfer_id'], // Update to transfer ID
                            ]);
                    });

                    $processed++;
                } else {
                    // Failure: Mark payout failed and revert commissions to approved
                    DatabaseHelper::transactionWithRetry(function () use ($payout, $result, $user) {
                        $errorNote = is_array($result) ? ($result['error'] ?? 'Transfer failed') : 'Transfer failed - account may no longer be eligible';
                        $payout->update([
                            'status' => ReferralPayout::STATUS_FAILED,
                            'notes' => $errorNote,
                            'processed_at' => now(),
                        ]);

                        // Revert commissions to approved status
                        ReferralCommission::where('referrer_id', $user->id)
                            ->where('status', ReferralCommission::STATUS_PROCESSING)
                            ->where('payout_reference', "payout:{$payout->id}")
                            ->update([
                                'status' => ReferralCommission::STATUS_APPROVED,
                                'payout_reference' => null,
                                'payout_method' => null,
                            ]);
                    });

                    $failed++;
                }
            } catch (\Exception $e) {
                Log::error('Error processing automatic payout', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => "Processed {$processed} payouts, {$failed} failed, {$skipped} skipped",
        ];
    }

    /**
     * Get account balance for a connected account
     */
    public function getAccountBalance(User $user): ?array
    {
        if (!$this->isEnabled() || !$user->stripe_connect_id) {
            return null;
        }

        try {
            $balance = $this->stripe->balance->retrieve(
                [],
                ['stripe_account' => $user->stripe_connect_id]
            );

            return [
                'available' => $balance->available[0]->amount / 100,
                'pending' => $balance->pending[0]->amount / 100,
            ];
        } catch (ApiErrorException $e) {
            return null;
        }
    }
}
