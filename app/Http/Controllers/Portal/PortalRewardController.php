<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\GameReward;
use App\Models\QRCode;
use App\Models\SavedQRCode;
use App\Models\Redemption;
use App\Services\CustomerCodeService;
use App\Services\QRGeneratorService;
use App\Services\RewardCodeService;
use App\Services\UserPromoTokenService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalRewardController extends Controller
{
    public function __construct(
        protected CustomerCodeService $customerCodeService,
        protected QRGeneratorService $qrGeneratorService
    ) {}

    /**
     * List Rewards
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $customerCode = $this->customerCodeService->getOrCreate($user);

        $identifiers = collect([
            $customerCode,
            $user->email,
            $user->phone,
        ])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->unique()
            ->values();

        $redeemedRewards = GameReward::where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->with(['business', 'promotion' => fn($q) => $q->withTrashed()])
            ->orderBy('redeemed_at', 'desc')
            ->limit(20)
            ->get();

        // Promo redemptions done by staff:
        // Prefer reliable linkage via customer_user_id, fallback to legacy customer_identifier values.
        // EXCLUDE redemptions that were created by Game Reward redemptions (to avoid double-counting)
        // We identify these by checking if there's a GameReward with the same user_id, promotion_id, and redeemed_at date
        $gameRewardRedemptionIds = GameReward::where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->whereNotNull('promotion_id')
            ->get()
            ->map(function ($reward) {
                // Find redemption records created on the same day as this game reward was redeemed
                return Redemption::where('customer_user_id', $reward->user_id)
                    ->where('promotion_id', $reward->promotion_id)
                    ->whereDate('redeemed_at', $reward->redeemed_at?->format('Y-m-d'))
                    ->where('discount_amount', $reward->discount_value ?? 0)
                    ->pluck('id');
            })
            ->flatten()
            ->unique()
            ->values();
        
        $promoRedemptions = Redemption::query()
            ->where(function ($q) use ($user, $identifiers) {
                $q->where('customer_user_id', $user->id);
                if ($identifiers->count() > 0) {
                    $q->orWhereIn('customer_identifier', $identifiers->all());
                }
            })
            ->when($gameRewardRedemptionIds->count() > 0, function ($q) use ($gameRewardRedemptionIds) {
                $q->whereNotIn('id', $gameRewardRedemptionIds->all());
            })
            ->where(function ($q) {
                // For punch cards, only show redemptions where the card was completed (final prize redeemed)
                // For other promotions, show all redemptions
                $q->whereHas('promotion', function ($promoQ) {
                    $promoQ->where('discount_type', '!=', \App\Models\Promotion::TYPE_PUNCH_CARD)->withTrashed();
                })
                ->orWhere(function ($punchQ) {
                    // Punch card: only show if card_completed is true AND discount_amount > 0
                    $punchQ->where('card_completed', true)
                        ->where('discount_amount', '>', 0)
                        ->whereHas('promotion', function ($promoQ) {
                            $promoQ->where('discount_type', \App\Models\Promotion::TYPE_PUNCH_CARD)->withTrashed();
                        });
                });
            })
            ->with(['business:id,name,logo_path', 'promotion' => fn($q) => $q->withTrashed()])
            ->orderByDesc('redeemed_at')
            ->limit(50)
            ->get();

        $rewardsRedeemedCount = GameReward::where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->count();

        $promoRedeemedCount = Redemption::query()
            ->where(function ($q) use ($user, $identifiers) {
                $q->where('customer_user_id', $user->id);
                if ($identifiers->count() > 0) {
                    $q->orWhereIn('customer_identifier', $identifiers->all());
                }
            })
            ->when($gameRewardRedemptionIds->count() > 0, function ($q) use ($gameRewardRedemptionIds) {
                $q->whereNotIn('id', $gameRewardRedemptionIds->all());
            })
            ->where(function ($q) {
                // For punch cards, only count final prize redemptions
                $q->whereHas('promotion', function ($promoQ) {
                    $promoQ->where('discount_type', '!=', \App\Models\Promotion::TYPE_PUNCH_CARD)->withTrashed();
                })
                ->orWhere(function ($punchQ) {
                    $punchQ->where('card_completed', true)
                        ->where('discount_amount', '>', 0)
                        ->whereHas('promotion', function ($promoQ) {
                            $promoQ->where('discount_type', \App\Models\Promotion::TYPE_PUNCH_CARD)->withTrashed();
                        });
                });
            })
            ->count();

        $rewardSavings = (float) GameReward::where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->sum('discount_value');

        $promoSavings = (float) Redemption::query()
            ->where(function ($q) use ($user, $identifiers) {
                $q->where('customer_user_id', $user->id);
                if ($identifiers->count() > 0) {
                    $q->orWhereIn('customer_identifier', $identifiers->all());
                }
            })
            ->when($gameRewardRedemptionIds->count() > 0, function ($q) use ($gameRewardRedemptionIds) {
                $q->whereNotIn('id', $gameRewardRedemptionIds->all());
            })
            ->where(function ($q) {
                // For punch cards, only count final prize redemptions
                $q->whereHas('promotion', function ($promoQ) {
                    $promoQ->where('discount_type', '!=', \App\Models\Promotion::TYPE_PUNCH_CARD)->withTrashed();
                })
                ->orWhere(function ($punchQ) {
                    $punchQ->where('card_completed', true)
                        ->where('discount_amount', '>', 0)
                        ->whereHas('promotion', function ($promoQ) {
                            $promoQ->where('discount_type', \App\Models\Promotion::TYPE_PUNCH_CARD)->withTrashed();
                        });
                });
            })
            ->sum('discount_amount');

        return Inertia::render('Portal/Rewards', [
            'redeemedRewards' => $redeemedRewards,
            'promoRedemptions' => $promoRedemptions,
            'stats' => [
                'total_redeemed' => $rewardsRedeemedCount + $promoRedeemedCount,
                'total_savings' => round($rewardSavings + $promoSavings, 2),
            ],
        ]);
    }

    /**
     * Show Reward Details
     */
    public function show(Request $request, GameReward $reward)
    {
        if ($reward->user_id !== $request->user()->id) {
            abort(403);
        }

        $reward->load(['business', 'promotion' => fn($q) => $q->withTrashed(), 'gamePlay.qrCode' => fn($q) => $q->withTrashed(), 'gamePlay.game']);

        // Ensure reward has a code (do not rewrite legacy codes)
        if (empty($reward->reward_code)) {
            $reward->reward_code = app(RewardCodeService::class)->generateUniqueCode();
            $reward->save();
        }

        // Generate QR code if missing (uses original QR design if available)
        if (!$reward->qr_image_path && in_array($reward->status, [
            GameReward::STATUS_CLAIMED,
            GameReward::STATUS_AVAILABLE,
            GameReward::STATUS_EXPIRED,
        ])) {
            try {
                $this->generateRewardQrCode($reward);
                $reward->refresh();
            } catch (\Exception $e) {
                \Log::error('Failed to generate QR code for reward in show method', [
                    'reward_id' => $reward->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Portal/RewardDetail', [
            'reward' => $reward,
            'userAvatar' => [
                'name' => $request->user()->name,
                'avatar_url' => $request->user()->avatar_url,
            ],
        ]);
    }

    /**
     * Claim a Reward
     */
    public function claim(Request $request, GameReward $reward)
    {
        if ($reward->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$reward->isAvailable()) {
            return back()->with('error', 'This reward is no longer available');
        }

        $reward->claim();
        $reward->refresh();

        // Generate QR code when claimed
        if (!$reward->qr_image_path) {
            $this->generateRewardQrCode($reward);
            $reward->refresh();
        }

        // For leaderboard prizes, redirect to a normal promo QR page after claim
        if ($reward->leaderboard_entry_id && $reward->promotion_id) {
            $reward->loadMissing(['promotion', 'gamePlay.qrCode']);
            $promotion = $reward->promotion;
            if ($promotion) {
                $promoQr = QRCode::query()
                    ->where('promotion_id', $promotion->id)
                    ->where('type', 'promotion')
                    ->first();

                if (!$promoQr) {
                    $sourceQr = $reward->gamePlay?->qrCode;
                    $sourceDesign = $sourceQr?->getDesignWithDefaults() ?? [];
                    unset($sourceDesign['generated_path']);
                    $sourceDesign['auto_created'] = true;
                    $sourceDesign['leaderboard_prize_source'] = 'auto';

                    $promoQr = QRCode::create([
                        'business_id' => $promotion->business_id,
                        'name' => $promotion->name . ' (Promo)',
                        'type' => 'promotion',
                        'promotion_id' => $promotion->id,
                        'destination_url' => null,
                        'intended_use' => \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE,
                        'design' => $sourceDesign,
                        'is_active' => true,
                    ]);

                    $path = $this->qrGeneratorService->generateFile(
                        $promoQr->getScanUrl(),
                        $promoQr->getDesignWithDefaults(),
                        'png'
                    );
                    $design = $promoQr->design ?? [];
                    $design['generated_path'] = $path;
                    $promoQr->updateQuietly(['design' => $design]);
                }

                SavedQRCode::firstOrCreate(
                    ['user_id' => $request->user()->id, 'qr_code_id' => $promoQr->id],
                    ['saved_at' => now()]
                );

                // Use createRewardToken (always creates a fresh token) instead of
                // ensure() so that per-user promotion limits don't block recurring
                // leaderboard winners.  The prize was already validated at award time
                // in PrizeService, so we don't need to re-check limits here.
                $token = app(UserPromoTokenService::class)->createRewardToken($request->user(), $promoQr);
                if ($token?->code) {
                    return redirect()
                        ->to('/promo/' . $token->code . '?source=portal')
                        ->with('success', 'Reward claimed! Show this to staff when redeeming.');
                }
            }
        }

        return back()->with('success', 'Reward claimed! Show this to staff when redeeming.');
    }

    /**
     * Generate QR code for a reward
     */
    protected function generateRewardQrCode(GameReward $reward): void
    {
        try {
            // Ensure relationships are loaded
            if (!$reward->relationLoaded('business')) {
                $reward->load('business');
            }
            if (!$reward->relationLoaded('gamePlay')) {
                $reward->load('gamePlay.qrCode');
            }

            // URL that staff will scan to redeem the reward (using short route)
            $data = url('/r/' . $reward->reward_code);

            // Get design from original QR code if available (so it matches the scanned QR)
            $originalQrCode = $reward->gamePlay?->qrCode;
            $design = null;

            if ($originalQrCode && method_exists($originalQrCode, 'getDesignWithDefaults')) {
                // Use the original QR code's design
                $design = $originalQrCode->getDesignWithDefaults();
                // Remove the generated_path since we're creating a new QR code
                unset($design['generated_path']);
                // Increase size for easier scanning
                $design['size'] = 500;
            } else {
                // Fallback to defaults if no original QR code design available
                $design = [
                    'size' => 500, // Larger size for easy scanning
                    'margin' => 10,
                    'error_correction' => 'H', // High error correction for reliability
                    'module_shape' => 'square',
                    'finder_shape' => 'square',
                    'background_color' => '#FFFFFF',
                    'module_color' => '#000000',
                ];
            }

            // Only add business logo if:
            // 1. We're using the fallback design (no original QR code), OR
            // 2. The original design explicitly doesn't have a logo key (not just null)
            // If original design has logo: null, we preserve that (no logo)
            $shouldAddLogo = false;
            if ($originalQrCode && method_exists($originalQrCode, 'getDesignWithDefaults')) {
                // Original design exists - only add logo if it wasn't in the original design at all
                $shouldAddLogo = !array_key_exists('logo', $originalQrCode->design ?? []);
            } else {
                // Using fallback design - add logo if business has one
                $shouldAddLogo = true;
            }
            
            if ($shouldAddLogo && $reward->business && $reward->business->logo_path) {
                $design['logo'] = [
                    'url' => $reward->business->logo_url,
                    'size' => 0.2,
                    'background' => true,
                ];
            }

            $path = $this->qrGeneratorService->generateFile($data, $design, 'png');

            $reward->update(['qr_image_path' => $path]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate QR code for reward', [
                'reward_id' => $reward->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - allow page to load without QR code
        }
    }

    /**
     * Claim a reward by code (for anonymous rewards)
     */
    public function claimByCode(Request $request)
    {
        $request->validate([
            // Legacy rewards used UUIDs/short codes; current rewards use UP-XXXX-XXXX.
            'reward_code' => 'required|string|min:8|max:36'
        ]);

        $user = $request->user();

        // Find the reward by code
        $reward = GameReward::where('reward_code', $request->reward_code)->first();

        if (!$reward) {
            return back()->with('error', 'Reward code not found. Please check the code and try again.');
        }

        // If reward already has a user, it might be claimed by someone else
        if ($reward->user_id && $reward->user_id !== $user->id) {
            return back()->with('error', 'This reward code has already been claimed by another account.');
        }

        // If reward is anonymous (no user_id), claim it for this user
        if (!$reward->user_id) {
            $reward->user_id = $user->id;
            $reward->save();

            // Update user's stats
            $user->increment('total_rewards_won');
        }

        // Now claim the reward
        if (!$reward->isAvailable()) {
            return back()->with('error', 'This reward is no longer available');
        }

        $reward->claim();

        return back()->with('success', 'Reward claimed successfully! You can now redeem it with staff.');
    }
}

