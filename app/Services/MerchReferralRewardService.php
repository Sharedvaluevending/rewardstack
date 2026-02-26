<?php

namespace App\Services;

use App\Models\MerchReferralAward;
use App\Models\MerchReferralReward;
use App\Models\MerchTag;
use App\Models\Redemption;
use App\Models\Scan;
use App\Services\UserPromoTokenService;
use App\Notifications\PortalMerchReferralAwarded;
use Illuminate\Support\Facades\DB;

class MerchReferralRewardService
{
    public function __construct(
        protected UserPromoTokenService $tokenService
    ) {}

    public function evaluateForRedemption(Redemption $redemption, ?Scan $scan): void
    {
        if (!$scan || !$scan->merch_tag_id) {
            return;
        }

        $tag = MerchTag::with('owner')->find($scan->merch_tag_id);
        if (!$tag || !$tag->owner_user_id || !$tag->owner) {
            return;
        }

        $reward = MerchReferralReward::where('qr_code_id', $redemption->qr_code_id)
            ->where('is_active', true)
            ->first();

        if (!$reward) {
            return;
        }

        $required = (int) $reward->redemptions_required;
        if ($required <= 0) {
            return;
        }

        $uniqueCount = (int) Redemption::query()
            ->join('scans', 'scans.id', '=', 'redemptions.scan_id')
            ->where('scans.merch_tag_id', $tag->id)
            ->whereNotNull('redemptions.customer_user_id')
            ->selectRaw('COUNT(DISTINCT redemptions.customer_user_id) as cnt')
            ->value('cnt');

        if ($uniqueCount <= 0) {
            return;
        }

        $expectedAwards = intdiv($uniqueCount, $required);
        if ($expectedAwards <= 0) {
            return;
        }

        $toCreate = DB::transaction(function () use ($tag, $reward, $expectedAwards, $uniqueCount) {
            // Lock existing awards inside transaction to prevent duplicate creation
            $existingAwards = MerchReferralAward::where('merch_tag_id', $tag->id)
                ->where('reward_id', $reward->id)
                ->lockForUpdate()
                ->count();

            $toCreate = $expectedAwards - $existingAwards;
            if ($toCreate <= 0) {
                return 0;
            }

            for ($i = 0; $i < $toCreate; $i++) {
                $token = null;
                if ($reward->reward_qr_code_id && $tag->owner) {
                    $rewardQr = $reward->rewardQrCode;
                    if ($rewardQr && $rewardQr->promotion) {
                        $token = $this->tokenService->createRewardToken($tag->owner, $rewardQr);
                    }
                }

                MerchReferralAward::create([
                    'merch_tag_id' => $tag->id,
                    'owner_user_id' => $tag->owner_user_id,
                    'reward_id' => $reward->id,
                    'user_promo_token_id' => $token?->id,
                    'redemptions_count_snapshot' => $uniqueCount,
                    'awarded_at' => now(),
                ]);
            }

            return $toCreate;
        });

        if ($toCreate <= 0) {
            return;
        }

        if ($tag->owner && in_array($tag->owner->role, ['user', 'customer', 'business'], true)) {
            $tag->owner->notify(new PortalMerchReferralAwarded($reward, $toCreate));
        }
    }
}
