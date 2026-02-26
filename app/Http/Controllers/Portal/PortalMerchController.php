<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MerchTag;
use App\Models\MerchReferralAward;
use App\Models\Redemption;
use App\Models\Scan;
use App\Jobs\BackfillMerchTagQrImage;
use App\Services\QRGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PortalMerchController extends Controller
{
    public function __construct(
        protected QRGeneratorService $qrService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $tags = MerchTag::with(['business', 'qrCode.merchReferralReward'])
            ->where('owner_user_id', $user->id)
            ->orderByDesc('claimed_at')
            ->get();

        // Dispatch background jobs for any tags missing QR images (non-blocking).
        foreach ($tags as $tag) {
            if (!$tag->qr_image_path && $tag->qrCode) {
                BackfillMerchTagQrImage::dispatch($tag->id);
            }
        }

        $tagIds = $tags->pluck('id');

        $scanCounts = Scan::selectRaw('merch_tag_id, count(*) as total_scans, count(distinct session_id) as unique_scans')
            ->whereIn('merch_tag_id', $tagIds)
            ->groupBy('merch_tag_id')
            ->get()
            ->keyBy('merch_tag_id');

        $redemptionCounts = Redemption::selectRaw('scans.merch_tag_id, count(*) as redemptions, count(distinct redemptions.customer_user_id) as unique_redemptions')
            ->join('scans', 'scans.id', '=', 'redemptions.scan_id')
            ->whereIn('scans.merch_tag_id', $tagIds)
            ->groupBy('scans.merch_tag_id')
            ->get()
            ->keyBy('merch_tag_id');

        $awardsByTag = MerchReferralAward::with('userPromoToken')
            ->whereIn('merch_tag_id', $tagIds)
            ->orderByDesc('awarded_at')
            ->get()
            ->groupBy('merch_tag_id');

        $items = $tags->map(function ($tag) use ($scanCounts, $redemptionCounts, $awardsByTag) {
            $scanStats = $scanCounts->get($tag->id);
            $redemptions = $redemptionCounts->get($tag->id);
            $reward = $tag->qrCode?->merchReferralReward;
            $awards = $awardsByTag->get($tag->id, collect());

            return [
                'id' => $tag->id,
                'code' => $tag->code,
                'qr_image_url' => $tag->qr_image_path ? asset('storage/' . $tag->qr_image_path) : null,
                'business' => [
                    'name' => $tag->business?->name,
                    'logo_url' => $tag->business?->logo_url,
                ],
                'qr_code' => [
                    'name' => $tag->qrCode?->name,
                    'code' => $tag->qrCode?->code,
                ],
                'claimed_at' => optional($tag->claimed_at)->toDateTimeString(),
                'stats' => [
                    'total_scans' => (int) ($scanStats->total_scans ?? 0),
                    'unique_scans' => (int) ($scanStats->unique_scans ?? 0),
                    'redemptions' => (int) ($redemptions->redemptions ?? 0),
                    'unique_redemptions' => (int) ($redemptions->unique_redemptions ?? 0),
                ],
                'reward' => $reward ? [
                    'type' => $reward->reward_type,
                    'value' => $reward->reward_value,
                    'item_value' => $reward->reward_item_value,
                    'description' => $reward->reward_description,
                    'redemptions_required' => (int) $reward->redemptions_required,
                ] : null,
                'awards' => $awards->take(3)->map(fn ($award) => [
                    'awarded_at' => $award->awarded_at?->toDateTimeString(),
                    'count_snapshot' => (int) $award->redemptions_count_snapshot,
                    'token_code' => $award->userPromoToken?->code,
                    'token_qr' => $award->userPromoToken?->qr_image_path ? asset('storage/' . $award->userPromoToken->qr_image_path) : null,
                ])->values(),
                'awards_count' => $awards->count(),
            ];
        });

        $summary = [
            'total_merch' => $items->count(),
            'total_scans' => $items->sum(fn ($item) => $item['stats']['total_scans']),
            'unique_scans' => $items->sum(fn ($item) => $item['stats']['unique_scans']),
            'redemptions' => $items->sum(fn ($item) => $item['stats']['redemptions']),
            'awards' => $items->sum(fn ($item) => $item['awards_count']),
        ];

        return Inertia::render('Portal/Merch/Index', [
            'items' => $items,
            'summary' => $summary,
            'flare' => [
                'scan_xp' => (int) config('xp.merch.scan_xp', 50),
            ],
        ]);
    }

    public function ping(Request $request)
    {
        $user = $request->user();
        $tagIds = MerchTag::where('owner_user_id', $user->id)->pluck('id');

        if ($tagIds->isEmpty()) {
            return response()->json([
                'scan_id' => null,
                'merch_tag_id' => null,
                'scanned_at' => null,
            ]);
        }

        $scan = Scan::whereIn('merch_tag_id', $tagIds)
            ->orderByDesc('scanned_at')
            ->first();

        return response()->json([
            'scan_id' => $scan?->id,
            'merch_tag_id' => $scan?->merch_tag_id,
            'scanned_at' => $scan?->scanned_at?->toDateTimeString(),
        ]);
    }
}
