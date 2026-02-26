<?php

namespace App\Services;

use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPromoTokenService
{
    public function __construct(
        protected QRGeneratorService $qrGeneratorService
    ) {}

    /**
     * Ensure a per-user token exists for this promotion QR.
     * Returns the token (existing or newly created).
     *
     * Optionally provide a precomputed active count + latest active token
     * to avoid N+1 queries in list views.
     */
    public function ensure(
        User $user,
        QRCode $qrCode,
        ?int $precomputedActiveCount = null,
        ?UserPromoToken $precomputedLatestActive = null
    ): ?UserPromoToken
    {
        // Check if user has correct role
        if (!in_array(($user->role ?? null), ['customer', 'user'], true)) {
            // DEBUG
            // dump("UserPromoTokenService: User role '{$user->role}' invalid for token creation.");
            return null;
        }

        if (!$qrCode->promotion) {
            // DEBUG
            // dump("UserPromoTokenService: QR code has no promotion.");
            return null;
        }

        $promotion = $qrCode->promotion;
        $rules = is_array($promotion->rules) ? $promotion->rules : [];

        // Portal stacking policy:
        // - Default: one active (unredeemed) token per promo per user.
        // - If portal_multiple_scans is enabled: allow multiple active tokens up to max_redemptions_per_user.
        $multipleAllowed = (bool) ($rules['portal_multiple_scans'] ?? false);
        $limit = $multipleAllowed ? (int) ($rules['max_redemptions_per_user'] ?? 0) : 1;

        if (($qrCode->type ?? null) === 'merch_referral') {
            $multipleAllowed = false;
            $limit = 1;
        }
        if ($multipleAllowed && $limit <= 0) {
            // Safety cap if unlimited (prevents customers from creating thousands of tokens).
            $limit = 50;
        }

        return DB::transaction(function () use ($user, $qrCode, $multipleAllowed, $limit, $rules, $precomputedActiveCount, $precomputedLatestActive) {
            // Lock the user's existing tokens for this QR so concurrent requests
            // see the same count and don't both create past the limit.
            $activeTokensQuery = UserPromoToken::where('user_id', $user->id)
                ->where('qr_code_id', $qrCode->id)
                ->whereNull('redeemed_at')
                ->lockForUpdate()
                ->orderByDesc('created_at');

            // Inside the lock, always recount (ignore precomputed values).
            $activeCount = (int) $activeTokensQuery->count();
            $latestActive = $activeTokensQuery->first();

            // If multiple is NOT enabled, always reuse the current active token.
            if (!$multipleAllowed && $activeCount > 0) {
                if ($latestActive) {
                    return $this->verifyQrImage($latestActive, $qrCode);
                }
                $token = $activeTokensQuery->first();
                return $token ? $this->verifyQrImage($token, $qrCode) : null;
            }

            // If multiple IS enabled and user is already at the cap, reuse the latest active token.
            if ($multipleAllowed && $activeCount >= $limit) {
                if ($latestActive) {
                    return $this->verifyQrImage($latestActive, $qrCode);
                }
                $token = $activeTokensQuery->first();
                if ($token) {
                    return $this->verifyQrImage($token, $qrCode);
                }
            }

            // Check if user has exhausted all allowed redemptions before creating a new token.
            $maxPerUser = (int) ($rules['max_redemptions_per_user'] ?? 0);
            if ($maxPerUser > 0) {
                $totalRedemptions = \App\Models\Redemption::where('promotion_id', $qrCode->promotion_id)
                    ->where('customer_user_id', $user->id)
                    ->count();
                if ($totalRedemptions >= $maxPerUser) {
                    return UserPromoToken::where('user_id', $user->id)
                        ->where('qr_code_id', $qrCode->id)
                        ->orderByDesc('created_at')
                        ->first();
                }
            }

            $token = UserPromoToken::create([
                'user_id' => $user->id,
                'qr_code_id' => $qrCode->id,
                'promotion_id' => $qrCode->promotion_id,
                'business_id' => $qrCode->business_id,
                'code' => $this->generateUniqueCode(),
            ]);

            $token->update([
                'qr_image_path' => $this->generateQrImagePath($token, $qrCode),
            ]);

            return $token->fresh();
        });
    }

    /**
     * Create a one-time reward token (always creates a new token).
     */
    public function createRewardToken(User $user, QRCode $qrCode): ?UserPromoToken
    {
        if (!in_array(($user->role ?? null), ['customer', 'user'], true)) {
            return null;
        }

        if (!$qrCode->promotion) {
            return null;
        }

        $token = UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $qrCode->promotion_id,
            'business_id' => $qrCode->business_id,
            'code' => $this->generateUniqueCode(),
        ]);

        $token->update([
            'qr_image_path' => $this->generateQrImagePath($token, $qrCode),
        ]);

        return $token->fresh();
    }

    /**
     * Create or retrieve a token for a specific promotion (used in Cross-Promos).
     * The sourceQrCode is used for metadata/design but the promotion is explicit.
     */
    public function createForCrossPromo(User $user, QRCode $sourceQrCode, \App\Models\Promotion $promotion): UserPromoToken
    {
        return DB::transaction(function () use ($user, $sourceQrCode, $promotion) {
            $existing = UserPromoToken::where('user_id', $user->id)
                ->where('promotion_id', $promotion->id)
                ->whereNull('redeemed_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->verifyQrImage($existing, $sourceQrCode);
            }

            $token = UserPromoToken::create([
                'user_id' => $user->id,
                'qr_code_id' => $sourceQrCode->id,
                'promotion_id' => $promotion->id,
                'business_id' => $promotion->business_id,
                'code' => $this->generateUniqueCode(),
            ]);

            $token->update([
                'qr_image_path' => $this->generateQrImagePath($token, $sourceQrCode),
            ]);

            return $token;
        });
    }

    protected function verifyQrImage(UserPromoToken $token, QRCode $qrCode): UserPromoToken
    {
        if (!$token->qr_image_path) {
            $token->update([
                'qr_image_path' => $this->generateQrImagePath($token, $qrCode),
            ]);
        }
        return $token;
    }

    /**
     * Token format: UP-XXXX-XXXX (avoids O/0 and I/1)
     */
    protected function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $part1 = $this->randFromAlphabet($alphabet, 4);
            $part2 = $this->randFromAlphabet($alphabet, 4);
            $code = 'UP-' . $part1 . '-' . $part2;
        } while (UserPromoToken::where('code', $code)->exists());

        return $code;
    }

    protected function randFromAlphabet(string $alphabet, int $len): string
    {
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    protected function generateQrImagePath(UserPromoToken $token, QRCode $qrCode): string
    {
        // Encode a staff-friendly URL (using short route). 
        // Staff device must be logged in, or they'll be prompted.
        $data = url('/t/' . $token->code);

        // Use the promo QR's existing design so the new QR looks like the original.
        $design = method_exists($qrCode, 'getDesignWithDefaults')
            ? $qrCode->getDesignWithDefaults()
            : ($qrCode->design ?? []);

        // Keep it visually identical; the portal will show the code under the QR.
        unset($design['generated_path']);

        return $this->qrGeneratorService->generateFile($data, $design, 'png');
    }
}


