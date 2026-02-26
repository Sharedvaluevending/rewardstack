<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\PunchCard;
use App\Models\Promotion;
use App\Models\SavedQRCode;
use App\Models\QRCode;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\QRCodeGame;
use App\Models\Redemption;
use App\Models\UserPromoToken;
use App\Models\Leaderboard;
use App\Services\CustomerCodeService;
use App\Services\UserPromoTokenService;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortalScanController extends Controller
{
    public function __construct(
        protected CustomerCodeService $customerCodeService,
        protected UserPromoTokenService $userPromoTokenService
    ) {}

    /**
     * Show user's scanned QR codes and promotions
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // Exclude all Portal Join Flyer QR codes (admin-only, never show in user portal)
        $onboardingQrIds = QRCode::withTrashed()
            ->where('code', OnboardingQr::CODE)
            ->pluck('id')
            ->all();

        // Get filter parameters
        $promotionType = $request->get('promotion_type');
        $businessId = $request->get('business_id');
        $status = $request->get('status', 'all'); // active, expired, all
        $savedQ = $request->get('saved_q'); // search within saved promos (scales better than loading 100s)
        $crossPromoOnly = $request->boolean('cross_promo_only'); // Filter for partner deal chains only

        // Stack counts (for promos that allow portal_max_per_user=5, show xN instead of duplicating cards)
        $scanCountsByQr = Scan::where('user_id', $user->id)
            ->when(!empty($onboardingQrIds), function ($q) use ($onboardingQrIds) {
                $q->where(function ($inner) use ($onboardingQrIds) {
                    $inner->whereNull('qr_code_id')->orWhereNotIn('qr_code_id', $onboardingQrIds);
                });
            })
            ->selectRaw('qr_code_id, COUNT(*) as cnt')
            ->groupBy('qr_code_id')
            ->get()
            ->keyBy('qr_code_id');

        // Get saved PROMOTION QR code IDs for this user (do not count non-promo QR types)
        $savedQRCodeIds = SavedQRCode::where('user_id', $user->id)
            ->whereHas('qrCode', function ($q) {
                $q->where('type', 'promotion')->withTrashed();
            })
            ->pluck('qr_code_id')
            ->toArray();

        $customerCode = null;
        if (($user->role ?? null) === 'customer') {
            $customerCode = $this->customerCodeService->getOrCreate($user);
            $user->refresh();
        }

        // If the user has redeemed a promotion (non-punch-card), remove that promo from
        // "My Scans & Promotions" so the portal reflects "used" vs "available".
        // Check both Redemptions table (via customer_user_id) and UserPromoToken (via redeemed_at)
        $redeemedPromotionQrIdsFromRedemptions = Redemption::query()
            ->where('customer_user_id', $user->id)
            ->whereNotNull('qr_code_id')
            ->whereHas('promotion', function ($q) {
                $q->where('discount_type', '!=', Promotion::TYPE_PUNCH_CARD)->withTrashed();
            })
            ->pluck('qr_code_id')
            ->unique()
            ->values();

        // Also check UserPromoToken for redeemed tokens (more reliable for per-user tracking)
        // This catches promotions where the user redeemed via their customer promo code
        // Check both redeemed_at and redemption_id to catch all cases
        $redeemedPromotionQrIdsFromTokens = \App\Models\UserPromoToken::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNotNull('redeemed_at')
                  ->orWhereNotNull('redemption_id');
            })
            ->whereHas('promotion', function ($q) {
                $q->where('discount_type', '!=', Promotion::TYPE_PUNCH_CARD)->withTrashed();
            })
            ->pluck('qr_code_id')
            ->unique()
            ->values();

        // Also check promotions where user has actually redeemed (via customer_identifier)
        // This catches cases where redemption was done but UserPromoToken might not be marked as redeemed
        // OR where the redemption record exists but customer_user_id wasn't set correctly
        $userCustomerCode = $customerCode;
        $qrIdsAtUserLimit = collect();
        
        if ($userCustomerCode) {
            // Get all promotions where user has redeemed (via customer_identifier OR customer_user_id in redemptions)
            $userRedeemedPromotionIds = Redemption::query()
                ->where(function ($q) use ($user, $userCustomerCode) {
                    $q->where('customer_user_id', $user->id)
                      ->orWhere('customer_identifier', $userCustomerCode);
                })
                ->whereNotNull('promotion_id')
                ->whereHas('promotion', function ($q) {
                    $q->where('discount_type', '!=', Promotion::TYPE_PUNCH_CARD)->withTrashed();
                })
                ->pluck('promotion_id')
                ->unique()
                ->values();

            // Get QR code IDs for promotions the user has redeemed
            if ($userRedeemedPromotionIds->count() > 0) {
                $qrIdsAtUserLimit = QRCode::whereIn('promotion_id', $userRedeemedPromotionIds->all())
                    ->withTrashed()
                    ->pluck('id')
                    ->unique()
                    ->values();
            }
        }

        // Combine all sources: redeemed promotions + promotions at user limit
        $redeemedPromotionQrIds = $redeemedPromotionQrIdsFromRedemptions
            ->merge($redeemedPromotionQrIdsFromTokens)
            ->merge($qrIdsAtUserLimit)
            ->unique()
            ->values();

        // For multi-redemption promos (max_redemptions_per_user > 1), only exclude
        // QR codes where the user has exhausted all allowed redemptions.
        if ($redeemedPromotionQrIds->count() > 0) {
            $qrIdsWithRemainingRedemptions = QRCode::whereIn('id', $redeemedPromotionQrIds->all())
                ->withTrashed()
                ->whereNotNull('promotion_id')
                ->with(['promotion' => fn($q) => $q->withTrashed()->select('id', 'rules')])
                ->get(['id', 'promotion_id'])
                ->filter(function ($qr) use ($user) {
                    $rules = is_array($qr->promotion?->rules) ? $qr->promotion->rules : [];
                    $maxPerUser = (int) ($rules['max_redemptions_per_user'] ?? 0);
                    if ($maxPerUser <= 1) {
                        return false;
                    }
                    $userRedemptions = Redemption::where('promotion_id', $qr->promotion_id)
                        ->where('customer_user_id', $user->id)
                        ->count();
                    return $userRedemptions < $maxPerUser;
                })
                ->pluck('id')
                ->unique()
                ->values();

            if ($qrIdsWithRemainingRedemptions->count() > 0) {
                $redeemedPromotionQrIds = $redeemedPromotionQrIds->diff($qrIdsWithRemainingRedemptions)->values();
            }
        }

        // Punch cards: treat a completed card redemption as "redeemed" for My Promotions
        $redeemedPunchCardQrIds = Redemption::query()
            ->where('customer_user_id', $user->id)
            ->whereNotNull('qr_code_id')
            ->where('card_completed', true)
            ->whereHas('promotion', function ($q) {
                $q->where('discount_type', Promotion::TYPE_PUNCH_CARD)->withTrashed();
            })
            ->pluck('qr_code_id')
            ->unique()
            ->values();

        if ($redeemedPunchCardQrIds->count() > 0) {
            $redeemedPromotionQrIds = $redeemedPromotionQrIds
                ->merge($redeemedPunchCardQrIds)
                ->unique()
                ->values();
        }

        // Get QR codes that resulted in redeemed rewards (exclude from recent scans)
        // BUT keep QR codes that still have active (available/claimed) rewards so
        // the user can always find them in Recent Activity.
        $redeemedRewardQrIds = GameReward::query()
            ->where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->whereHas('gamePlay', function ($q) {
                $q->whereNotNull('qr_code_id');
            })
            ->with('gamePlay:id,qr_code_id')
            ->get()
            ->pluck('gamePlay.qr_code_id')
            ->filter()
            ->unique()
            ->values();

        $activeRewardQrIds = GameReward::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
            ->whereHas('gamePlay', function ($q) {
                $q->whereNotNull('qr_code_id');
            })
            ->with('gamePlay:id,qr_code_id')
            ->get()
            ->pluck('gamePlay.qr_code_id')
            ->filter()
            ->unique();

        // Only exclude QR codes where ALL rewards are redeemed (none still active)
        $excludedQrIds = $redeemedRewardQrIds->diff($activeRewardQrIds)->unique()->values();

        // Count unredeemed scans per QR code for the "xN" badge in the carousel
        $activeScanCounts = Scan::where('user_id', $user->id)
            ->when(!empty($onboardingQrIds), function ($q) use ($onboardingQrIds) {
                $q->where(function ($inner) use ($onboardingQrIds) {
                    $inner->whereNull('qr_code_id')->orWhereNotIn('qr_code_id', $onboardingQrIds);
                });
            })
            ->whereDoesntHave('redemption')
            ->whereHas('qrCode', function ($qr) {
                $qr->withTrashed()->where(function ($inner) {
                    $inner->whereHas('promotion', fn($p) => $p->withTrashed())
                          ->orWhereNull('promotion_id');
                });
            })
            ->selectRaw('qr_code_id, COUNT(*) as cnt')
            ->groupBy('qr_code_id')
            ->get()
            ->keyBy('qr_code_id');

        // Get full scan history for this user (Savings Journal)
        // - Include promo scans where the promotion is active
        // - ALSO include QRcade scans (game + leaderboard) so the user sees the scan that started the game
        // - Exclude Portal Join Flyer QR (admin-only, never show in user portal)
        $scansQuery = Scan::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('scan_type')->orWhere('scan_type', '!=', 'blocked');
            })
            ->when(!empty($onboardingQrIds), function ($q) use ($onboardingQrIds) {
                $q->where(function ($inner) use ($onboardingQrIds) {
                    $inner->whereNull('qr_code_id')->orWhereNotIn('qr_code_id', $onboardingQrIds);
                });
            })
            ->where(function ($q) use ($crossPromoOnly) {
                if ($crossPromoOnly) {
                    // Filter for cross-promo QR codes only
                    $q->whereHas('qrCode', function ($qr) {
                        $qr->where('type', 'cross_promo')
                           ->whereNotNull('cross_promotion_id')
                           ->withTrashed();
                    });
                } else {
                    $q->whereHas('qrCode', function ($qr) {
                        $qr->withTrashed()->where(function ($inner) {
                            $inner->whereHas('promotion', fn($p) => $p->withTrashed())
                                  ->orWhereNull('promotion_id')
                                  ->orWhereDoesntHave('promotion');
                        });
                    })
                    ->orWhere(function ($g) {
                        $g->whereIn('scan_type', [\App\Models\Scan::TYPE_QRCADE_GAME, \App\Models\Scan::TYPE_QRCADE_LEADERBOARD])
                          ->whereHas('qrCode', function ($qr) {
                              $qr->whereIn('type', ['qrcade', 'qrcade_leaderboard'])->withTrashed();
                          });
                    })
                    ->orWhere(function ($cp) {
                        // Also include cross-promo scans
                        $cp->whereHas('qrCode', function ($qr) {
                            $qr->where('type', 'cross_promo')
                               ->whereNotNull('cross_promotion_id')
                               ->withTrashed();
                        });
                    });
                }
            })
            ->when($excludedQrIds->count() > 0, function ($q) use ($excludedQrIds) {
                $q->whereNotIn('qr_code_id', $excludedQrIds->all());
            });

        // Get redemption info for scans to show if they've been redeemed
        $redemptionInfoByQrCode = Redemption::where('customer_user_id', $user->id)
            ->whereHas('qrCode', function ($q) {
                $q->where('type', 'promotion')->withTrashed();
            })
            ->with(['qrCode' => fn($q) => $q->withTrashed(), 'promotion' => fn($q) => $q->withTrashed()])
            ->get()
            ->groupBy('qr_code_id')
            ->map(function ($redemptions) {
                $latest = $redemptions->sortByDesc('redeemed_at')->first();
                return [
                    'is_redeemed' => true,
                    'latest_redemption' => [
                        'id' => $latest->id,
                        'discount_amount' => $latest->discount_amount,
                        'final_amount' => $latest->final_amount,
                        'original_amount' => $latest->original_amount,
                        'redeemed_at' => $latest->redeemed_at,
                    ],
                    'total_savings' => $redemptions->sum('discount_amount'),
                ];
            });

        // Paginate scan history at the DB level so this page remains fast even when
        // a user has thousands of scans.
        $perPage = (int) $request->integer('page_size', 20);
        $perPage = max(5, min(50, $perPage));

        $scans = $scansQuery
            ->with([
                'qrCode' => function($q) {
                    $q->select('id','code','name','type','promotion_id','business_id','is_active')->withTrashed();
                },
                'qrCode.promotion' => fn($q) => $q->withTrashed(),
                'qrCode.business:id,name,logo_path',
                'business:id,name,logo_path',
                'redemption:id,scan_id,user_promo_token_id,customer_identifier,discount_amount,original_amount,final_amount,card_completed,redeemed_at',
            ])
            ->orderBy('scanned_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Get tokens for QR codes on this page (used to show customer promo codes/QRs).
        $scanQrIds = $scans->getCollection()->pluck('qr_code_id')->unique()->filter()->values();
        $tokens = $scanQrIds->count() > 0
            ? UserPromoToken::query()
                ->where('user_id', $user->id)
                ->whereIn('qr_code_id', $scanQrIds->all())
                ->orderBy('created_at', 'asc')
                ->get()
            : collect();

        $allUserTokens = $tokens->groupBy('qr_code_id');
        $tokensById = $tokens->keyBy('id');

        $tokenCodes = $tokens->pluck('code')->filter()->values();
        $tokenRedemptionsByQr = collect();
        if ($tokenCodes->count() > 0 && $scanQrIds->count() > 0) {
            $tokenRedemptionsByQr = Redemption::query()
                ->whereIn('customer_identifier', $tokenCodes->all())
                ->whereIn('qr_code_id', $scanQrIds->all())
                ->orderByDesc('redeemed_at')
                ->get(['id', 'qr_code_id', 'scan_id', 'user_promo_token_id', 'discount_amount', 'original_amount', 'final_amount', 'card_completed', 'redeemed_at'])
                ->groupBy('qr_code_id');
        }

        // Batch-load possible legacy redemptions (older rows missing scan_id) for scans on this page.
        $scansNeedingLegacyLink = $scans->getCollection()->filter(function ($scan) {
            return !$scan->relationLoaded('redemption') || !$scan->redemption;
        })->filter(fn ($scan) => (bool) $scan->qr_code_id);

        $legacyRedemptionsByQr = collect();
        if ($scansNeedingLegacyLink->count() > 0) {
            $qrIds = $scansNeedingLegacyLink->pluck('qr_code_id')->unique()->values();
            $minScannedAt = $scansNeedingLegacyLink->min('scanned_at')?->copy()?->subSeconds(15);
            $maxScannedAt = $scansNeedingLegacyLink->max('scanned_at')?->copy()?->addMinutes(10);

            if ($qrIds->count() > 0 && $minScannedAt && $maxScannedAt) {
                $legacyRedemptionsByQr = Redemption::query()
                    ->where('customer_user_id', $user->id)
                    ->whereIn('qr_code_id', $qrIds->all())
                    ->whereNull('scan_id')
                    ->whereBetween('redeemed_at', [$minScannedAt, $maxScannedAt])
                    ->orderBy('redeemed_at')
                    ->get(['id', 'qr_code_id', 'scan_id', 'user_promo_token_id', 'discount_amount', 'original_amount', 'final_amount', 'card_completed', 'redeemed_at'])
                    ->groupBy('qr_code_id');
            }
        }

        $completedPunchRedemptionsByQr = collect();
        if ($scanQrIds->count() > 0) {
            $completedPunchRedemptionsByQr = Redemption::query()
                ->where('customer_user_id', $user->id)
                ->whereIn('qr_code_id', $scanQrIds->all())
                ->where('card_completed', true)
                ->orderByDesc('redeemed_at')
                ->get(['id', 'qr_code_id', 'scan_id', 'user_promo_token_id', 'discount_amount', 'original_amount', 'final_amount', 'card_completed', 'redeemed_at'])
                ->groupBy('qr_code_id');
        }

        // Enrich scan rows (redeemed status + best-effort promo token attachment).
        $matchedTokenIds = [];
        $scans->setCollection(
            $scans->getCollection()->map(function ($scan) use ($user, $legacyRedemptionsByQr, $tokenRedemptionsByQr, $allUserTokens, $tokensById, $completedPunchRedemptionsByQr, &$matchedTokenIds) {
                $qrId = $scan->qr_code_id;
                $promoType = $scan->qrCode?->promotion?->discount_type;
                $isRedeemed = (bool) $scan->redemption;
                $promoDeleted = $scan->qrCode?->promotion?->trashed();
                $qrDeleted = $scan->qrCode?->trashed();

                // If not directly linked to redemption via scan_id, try to link a legacy redemption (missing scan_id)
                // that occurred shortly after this scan.
                if (!$isRedeemed && $qrId) {
                    $candidates = $legacyRedemptionsByQr->get($qrId) ?? collect();
                    if ($candidates->count() > 0 && $scan->scanned_at) {
                        $start = $scan->scanned_at->copy()->subSeconds(15);
                        $end = $scan->scanned_at->copy()->addMinutes(10);
                        $match = $candidates->first(fn ($r) => $r->redeemed_at && $r->redeemed_at->betweenIncluded($start, $end));
                        if ($match) {
                            $isRedeemed = true;
                            $scan->setRelation('redemption', $match);
                        }
                    }
                }

                // If still not linked and we have token-based redemptions, link the latest redemption.
                // When multiple tokens exist for the same QR (multi-redemption promos),
                // skip this QR-wide fallback — per-token matching below will handle it.
                if (!$isRedeemed && $qrId) {
                    $allQrTokens = $allUserTokens->get($qrId) ?? collect();
                    if ($allQrTokens->count() <= 1) {
                        $tokenRedemptions = $tokenRedemptionsByQr->get($qrId) ?? collect();
                        if ($tokenRedemptions->count() > 0) {
                            $latestTokenRedemption = $tokenRedemptions->first();
                            $scan->setRelation('redemption', $latestTokenRedemption);
                            $isRedeemed = true;
                        }
                    }
                }

                // For punch cards, only mark redeemed when the card reward was claimed.
                if ($promoType === Promotion::TYPE_PUNCH_CARD) {
                    $completedRedemption = null;
                    if ($scan->redemption && $scan->redemption->card_completed) {
                        $completedRedemption = $scan->redemption;
                    }
                    if (!$completedRedemption && $qrId) {
                        $candidates = $completedPunchRedemptionsByQr->get($qrId) ?? collect();
                        if ($candidates->count() > 0) {
                            // Direct scan_id match is authoritative.
                            $completedRedemption = $candidates->first(fn ($c) => $c->scan_id === $scan->id);
                            // Legacy fallback: time proximity for older data without scan_id.
                            if (!$completedRedemption) {
                                $completedRedemption = $candidates->first(function ($candidate) use ($scan) {
                                    if (!$scan->scanned_at || !$candidate->redeemed_at) {
                                        return true;
                                    }
                                    return $candidate->redeemed_at->greaterThanOrEqualTo($scan->scanned_at);
                                });
                            }
                            if (!$completedRedemption) {
                                $completedRedemption = $candidates->first();
                            }
                        }
                    }

                    if ($completedRedemption) {
                        $scan->setRelation('redemption', $completedRedemption);
                        $isRedeemed = true;
                    } else {
                        $isRedeemed = false;
                        $scan->setRelation('redemption', null);
                    }
                }

                // If this scan represents a reward promo, reflect redemption status by promotion id.
                // This keeps "View Reward" entries in sync after redemption.
                if (!$isRedeemed && !empty($scan->reward_promotion_id)) {
                    $promoId = (int) $scan->reward_promotion_id;
                    if ($promoId > 0) {
                        $tokenCodes = UserPromoToken::where('user_id', $user->id)
                            ->where('promotion_id', $promoId)
                            ->pluck('code')
                            ->filter()
                            ->values();

                        $redemption = Redemption::where('promotion_id', $promoId)
                            ->where(function ($q) use ($user, $tokenCodes) {
                                $q->where('customer_user_id', $user->id);
                                if ($tokenCodes->count() > 0) {
                                    $q->orWhereIn('customer_identifier', $tokenCodes->all());
                                }
                            })
                            ->orderByDesc('redeemed_at')
                            ->first();

                        if ($redemption) {
                            $scan->setRelation('redemption', $redemption);
                            $isRedeemed = true;
                        }
                    }
                }

                $scan->is_redeemed = $isRedeemed;
                if ($isRedeemed && $scan->redemption) {
                    $scan->redemption_id = $scan->redemption->id;
                    $scan->redemption_discount_amount = $scan->redemption->discount_amount;
                    $scan->redemption_at = $scan->redemption->redeemed_at;
                }

                // Flag deleted/deactivated promos/QRs so the portal can show "Removed by business" but keep history.
                $promoMissing = (!$scan->qrCode?->promotion && !empty($scan->qrCode?->promotion_id));
                $qrDeactivated = $scan->qrCode && !$scan->qrCode->trashed() && ($scan->qrCode->is_active === false);
                $qrGone = !$scan->qrCode && !empty($scan->qr_code_id);
                $scan->removed_by_business = (!$isRedeemed) && ($promoDeleted || $promoMissing || $qrDeleted || $qrDeactivated || $qrGone);
                $scan->removed_label = $scan->removed_by_business ? 'Removed by business' : null;

                // Flag expired promotions so the user can clean up their scan history.
                $promoExpired = false;
                if (!$isRedeemed && !$scan->removed_by_business && $scan->qrCode?->promotion) {
                    $promo = $scan->qrCode->promotion;
                    if (($promo->ends_at && $promo->ends_at->isPast()) || !$promo->is_active) {
                        $promoExpired = true;
                    }
                }
                $scan->promo_expired = $promoExpired;

                // Add token info:
                // - If redeemed and the redemption record knows the token, use that exact token.
                // - Otherwise, best-effort match by time (closest token created_at to scanned_at).
                $token = null;
                if ($isRedeemed && $scan->redemption && $scan->redemption->user_promo_token_id) {
                    $token = $tokensById->get($scan->redemption->user_promo_token_id);
                }

                if (!$token && $qrId) {
                    $candidates = $allUserTokens->get($qrId) ?? collect();
                    $hasMultipleTokens = $candidates->count() > 1;

                    // For single-token QRs, filter by redeemed status as before.
                    // For multi-token QRs (multi-redemption promos), search ALL tokens
                    // by timestamp proximity, then derive redeemed status from the match.
                    if (!$hasMultipleTokens) {
                        $candidates = $isRedeemed
                            ? $candidates->filter(fn ($t) => (bool) $t->redeemed_at)
                            : $candidates->filter(fn ($t) => !(bool) $t->redeemed_at);
                    }

                    // Exclude tokens already matched to another scan to prevent
                    // double-matching when portal_multiple_scans is enabled.
                    if ($hasMultipleTokens && count($matchedTokenIds) > 0) {
                        $unmatched = $candidates->filter(fn ($t) => !in_array($t->id, $matchedTokenIds, true));
                        if ($unmatched->isNotEmpty()) {
                            $candidates = $unmatched;
                        }
                    }

                    $closest = null;
                    $closestDelta = null;
                    foreach ($candidates as $cand) {
                        if (!$cand->created_at || !$scan->scanned_at) {
                            continue;
                        }
                        $delta = abs($cand->created_at->diffInSeconds($scan->scanned_at));
                        if ($closestDelta === null || $delta < $closestDelta) {
                            $closest = $cand;
                            $closestDelta = $delta;
                        }
                    }

                    $token = $closest ?: $candidates->sortByDesc('created_at')->first();
                    if (!$token) {
                        $token = ($allUserTokens->get($qrId) ?? collect())->sortByDesc('created_at')->first();
                    }

                    // Token is the single source of truth for redeemed status on
                    // all promotion-type scans. Punch cards use card_completed instead,
                    // and reward promos are handled above via their own promotion_id lookup.
                    if ($token && $promoType !== Promotion::TYPE_PUNCH_CARD && empty($scan->reward_promotion_id)) {
                        $tokenIsRedeemed = (bool) $token->redeemed_at;
                        if ($tokenIsRedeemed && !$isRedeemed) {
                            $tokenRedemption = ($tokenRedemptionsByQr->get($qrId) ?? collect())
                                ->first(fn ($r) => ($r->user_promo_token_id ?? null) === $token->id);
                            if ($tokenRedemption) {
                                $scan->setRelation('redemption', $tokenRedemption);
                            }
                            $isRedeemed = true;
                            $scan->is_redeemed = true;
                            if ($scan->redemption) {
                                $scan->redemption_id = $scan->redemption->id;
                                $scan->redemption_discount_amount = $scan->redemption->discount_amount;
                                $scan->redemption_at = $scan->redemption->redeemed_at;
                            }
                        } elseif (!$tokenIsRedeemed && $isRedeemed) {
                            $isRedeemed = false;
                            $scan->is_redeemed = false;
                            $scan->setRelation('redemption', null);
                            $scan->redemption_id = null;
                            $scan->redemption_discount_amount = null;
                            $scan->redemption_at = null;
                        }
                    }
                }

                if ($token) {
                    $scan->customer_promo = [
                        'code' => $token->code,
                        'qr_image_url' => $token->qr_image_url,
                    ];
                    $matchedTokenIds[] = $token->id;
                }

                return $scan;
            })
        );

        /**
         * Enhance the "Recent Scans" list.
         * If the user has a recent WIN for that scanned QR code, link directly to the won reward/promo instead.
         */
        $scanQrCodeIds = $scans->getCollection()->pluck('qr_code_id')->unique()->filter()->values();
        
        $allWinsByQr = collect();
        $latestWinByQr = collect();
        $rewardsByPlay = collect();
        $matchedWinByScanId = [];
        
        $qrCodeGameModes = collect();
        if ($scanQrCodeIds->count() > 0) {
            $plays = GamePlay::query()
                ->where('user_id', $user->id)
                ->whereIn('qr_code_id', $scanQrCodeIds)
                ->orderByDesc('created_at')
                ->get(['id', 'qr_code_id', 'game_id', 'result', 'reward_tier', 'created_at', 'completed_at', 'game_data']);

            // Latest play for each scanned QR (used for leaderboard linking, even if not a win)
            $latestPlayByQr = $plays
                ->groupBy('qr_code_id')
                ->map(fn ($group) => $group->first());

            $allWinsByQr = $plays
                ->where('result', GamePlay::RESULT_WIN)
                ->groupBy('qr_code_id');

            $latestWinByQr = $allWinsByQr
                ->map(fn ($group) => $group->first());

            $allWinPlayIds = $plays
                ->where('result', GamePlay::RESULT_WIN)
                ->pluck('id')
                ->filter()
                ->values();

            if ($allWinPlayIds->count() > 0) {
                $rewardsByPlay = GameReward::query()
                    ->whereIn('game_play_id', $allWinPlayIds)
                    ->orderByDesc('created_at')
                    ->with(['promotion:id,discount_type', 'business:id,name,logo_path'])
                    ->get(['id', 'game_play_id', 'promotion_id', 'status', 'reward_code', 'qr_image_path', 'business_id', 'description', 'expires_at', 'discount_value', 'redeemed_at', 'leaderboard_entry_id'])
                    ->groupBy('game_play_id')
                    ->map(fn ($group) => $group->first());
            }

            $qrCodeGamePairs = $plays
                ->map(fn ($play) => [
                    'qr_code_id' => $play->qr_code_id,
                    'game_id' => $play->game_id,
                ])
                ->filter(fn ($pair) => $pair['qr_code_id'] && $pair['game_id'])
                ->unique(fn ($pair) => $pair['qr_code_id'] . ':' . $pair['game_id'])
                ->values();

            if ($qrCodeGamePairs->count() > 0) {
                $qrCodeIds = $qrCodeGamePairs->pluck('qr_code_id')->unique()->values();
                $gameIds = $qrCodeGamePairs->pluck('game_id')->unique()->values();

                $qrCodeGameModes = QRCodeGame::query()
                    ->whereIn('qr_code_id', $qrCodeIds->all())
                    ->whereIn('game_id', $gameIds->all())
                    ->get(['qr_code_id', 'game_id', 'win_mode'])
                    ->keyBy(fn ($row) => $row->qr_code_id . ':' . $row->game_id);
            }

            // Match each scan to its specific win chronologically.
            // When a promo allows multiple wins/redemptions per user, each scan
            // must pair with its own win — not share the latest one.
            $scansByQrForWinMatch = $scans->getCollection()
                ->filter(fn ($s) => $s->qr_code_id)
                ->groupBy('qr_code_id');

            foreach ($scansByQrForWinMatch as $qrId => $qrScans) {
                $qrWins = $allWinsByQr->get($qrId);
                if (!$qrWins || $qrWins->isEmpty() || ($qrScans->count() <= 1 && $qrWins->count() <= 1)) {
                    continue;
                }

                $sortedScans = $qrScans->sortBy('scanned_at')->values();
                $sortedWins = $qrWins->sortBy('created_at')->values();
                $winIdx = 0;

                foreach ($sortedScans as $sIdx => $scan) {
                    if ($winIdx >= $sortedWins->count()) {
                        break;
                    }
                    $nextScanTs = $sortedScans->get($sIdx + 1)?->scanned_at?->getTimestamp();

                    while ($winIdx < $sortedWins->count()) {
                        $win = $sortedWins[$winIdx];
                        $winTs = $win->created_at?->getTimestamp() ?? 0;
                        $scanTs = $scan->scanned_at?->getTimestamp() ?? 0;

                        if ($scanTs > 0 && $winTs > 0 && $winTs < $scanTs - 300) {
                            $winIdx++;
                            continue;
                        }

                        if ($nextScanTs && $winTs >= $nextScanTs) {
                            break;
                        }

                        if ($scanTs > 0 && $winTs > 0 && ($winTs - $scanTs) > 7200) {
                            break;
                        }

                        $matchedWinByScanId[$scan->id] = $win;
                        $winIdx++;
                        break;
                    }
                }
            }
        }

        // Leaderboard linking (so Portal "View" goes to the leaderboard for leaderboard challenges)
        $leaderboardsByBusinessGame = collect();
        $locationLeaderboardsByBusiness = collect();

        if (isset($latestPlayByQr) && $latestPlayByQr instanceof \Illuminate\Support\Collection) {
            $businessIds = $scans->getCollection()->pluck('business_id')->filter()->unique()->values();
            $gameIds = $latestPlayByQr->pluck('game_id')->filter()->unique()->values();

            if ($businessIds->count() > 0) {
                $lbs = Leaderboard::active()
                    ->whereIn('business_id', $businessIds->all())
                    ->where(function ($q) use ($gameIds) {
                        $q->where('type', Leaderboard::TYPE_LOCATION);
                        if ($gameIds->count() > 0) {
                            $q->orWhere(function ($qq) use ($gameIds) {
                                $qq->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                                    ->whereIn('game_id', $gameIds->all());
                            });
                        }
                    })
                    ->get(['id', 'business_id', 'game_id', 'type', 'name', 'reset_frequency', 'current_period_start', 'current_period_end']);

                $leaderboardsByBusinessGame = $lbs
                    ->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
                    ->groupBy(fn ($lb) => $lb->business_id . ':' . $lb->game_id)
                    ->map(fn ($group) => $group->first());

                $locationLeaderboardsByBusiness = $lbs
                    ->where('type', Leaderboard::TYPE_LOCATION)
                    ->groupBy('business_id')
                    ->map(fn ($group) => $group->first());
            }
        } else {
            $latestPlayByQr = collect();
        }

        $leaderboardRewardByPeriod = [];
        $leaderboardTokensByPromotionId = collect();
        $leaderboardIds = $leaderboardsByBusinessGame
            ->values()
            ->merge($locationLeaderboardsByBusiness->values())
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($leaderboardIds->count() > 0) {
            $leaderboardRewards = GameReward::query()
                ->where('user_id', $user->id)
                ->whereNotNull('leaderboard_entry_id')
                ->whereHas('leaderboardEntry', function ($q) use ($leaderboardIds) {
                    $q->whereIn('leaderboard_id', $leaderboardIds->all());
                })
                ->with(['leaderboardEntry:id,leaderboard_id,period_key'])
                ->get(['id', 'leaderboard_entry_id', 'promotion_id', 'status', 'reward_code', 'qr_image_path', 'business_id', 'description', 'expires_at', 'discount_value', 'redeemed_at']);

            foreach ($leaderboardRewards as $reward) {
                $entry = $reward->leaderboardEntry;
                if (!$entry) {
                    continue;
                }
                $leaderboardRewardByPeriod[$entry->leaderboard_id][$entry->period_key] = $reward;
            }

            $leaderboardPromotionIds = $leaderboardRewards
                ->pluck('promotion_id')
                ->filter()
                ->unique()
                ->values();

            if ($leaderboardPromotionIds->count() > 0) {
                $leaderboardTokensByPromotionId = UserPromoToken::query()
                    ->where('user_id', $user->id)
                    ->whereIn('promotion_id', $leaderboardPromotionIds->all())
                    ->orderByDesc('created_at')
                    ->get(['id', 'promotion_id', 'code'])
                    ->groupBy('promotion_id')
                    ->map(fn ($group) => $group->first());
            }
        }

        $periodKeyForDate = function ($leaderboard, $date): string {
            if (!$leaderboard || !$date) {
                return 'all-time';
            }

            return match ($leaderboard->reset_frequency) {
                Leaderboard::RESET_DAILY => $date->format('Y-m-d'),
                Leaderboard::RESET_WEEKLY => $date->format('Y-\\WW'),
                Leaderboard::RESET_MONTHLY => $date->format('Y-m'),
                default => 'all-time',
            };
        };

        $rewardPromoRedemptionCache = [];

        $scans->getCollection()->transform(function ($scan) use (
            $user,
            $activeScanCounts,
            $latestWinByQr,
            $matchedWinByScanId,
            $rewardsByPlay,
            $latestPlayByQr,
            $qrCodeGameModes,
            $leaderboardsByBusinessGame,
            $locationLeaderboardsByBusiness,
            $leaderboardRewardByPeriod,
            $leaderboardTokensByPromotionId,
            $periodKeyForDate,
            &$rewardPromoRedemptionCache
        ) {
            // 1. Stack count for unredeemed scans
            $scan->stack_count = (int)($activeScanCounts->get($scan->qr_code_id)->cnt ?? 0);
            
            // 2. Links and metadata
                    $scan->reward_link = null;
            $scan->reward_kind = null;
            $scan->reward_promotion_id = null;
                    $scan->play_link = $scan->qrCode?->code ? ('/play/' . $scan->qrCode->code) : null;
            $scan->leaderboard_link = null;
            $scan->leaderboard_name = null;
            $scan->play_again = false;
            $scan->is_winner = false;
            $scan->is_non_winner = false;
            $hasWin = false;

            // 3. Game reward linking (play-to-win only; leaderboard scans get enriched later in section 4)
            $isLeaderboardScan = (($scan->scan_type ?? null) === \App\Models\Scan::TYPE_QRCADE_LEADERBOARD);
            if (!$isLeaderboardScan) {
                    $winPlay = $matchedWinByScanId[$scan->id] ?? $latestWinByQr->get($scan->qr_code_id);
                    $hasWin = (bool) $winPlay;
                    $scan->is_winner = $hasWin;
                    if ($winPlay) {
                        $reward = $rewardsByPlay->get($winPlay->id);
                        if ($reward) {
                            $scan->reward_link = '/portal/rewards/' . $reward->id;
                            $scan->reward_kind = 'reward';
                            $scan->reward_promotion_id = $reward->promotion_id ?? null;
                            $scan->is_winner = $scan->is_winner || (bool) $reward->leaderboard_entry_id;
                            $scan->reward_data = [
                                'id' => $reward->id,
                                'status' => $reward->status,
                                'reward_code' => $reward->reward_code,
                                'qr_image_url' => $reward->qr_image_path ? asset('storage/' . $reward->qr_image_path) : null,
                                'description' => $reward->description,
                                'discount_value' => $reward->discount_value,
                                'redeemed_at' => $reward->redeemed_at,
                    ];
                        }
                    }
            }

            // 3b. Promo token linking (play-to-win only; leaderboard scans get enriched in section 4)
            if (!$isLeaderboardScan && !$scan->reward_link) {
                $winPlay = $matchedWinByScanId[$scan->id] ?? $latestWinByQr->get($scan->qr_code_id);
                if ($winPlay && ($winPlay->result ?? null) === \App\Models\GamePlay::RESULT_WIN) {
                    $data = is_array($winPlay->game_data) ? $winPlay->game_data : [];
                    $tokenCode = $data['_user_promo_token_code'] ?? null;
                    if (!is_string($tokenCode) || !str_starts_with($tokenCode, 'UP-')) {
                        // Backfill: older wins (or wins earned on a QRcade QR code that didn't have a promo QR)
                        // may not have a token stored. Create a proper PROMOTION-type QR and token now so the user can view it.
                        try {
                            $qrCodeGame = QRCodeGame::query()
                                ->where('qr_code_id', $scan->qr_code_id)
                                ->where('game_id', $winPlay->game_id)
                                ->first();

                            // For tiered mode, the promotion is stored in tier_rewards (keyed by tier),
                            // not in promotion_id. Use the winning play's reward_tier to look up the correct promo.
                            $promotionId = 0;
                            if ($qrCodeGame?->win_mode === QRCodeGame::WIN_MODE_TIERED && $winPlay->reward_tier) {
                                $tierRewards = is_array($qrCodeGame->tier_rewards) ? $qrCodeGame->tier_rewards : [];
                                $promotionId = (int) ($tierRewards[$winPlay->reward_tier] ?? 0);
                            }
                            if ($promotionId <= 0) {
                                $promotionId = (int) ($qrCodeGame?->promotion_id ?? 0);
                            }
                            if ($promotionId > 0) {
                                $promotion = Promotion::find($promotionId);

                                if ($promotion) {
                                    // Ensure a dedicated PROMOTION-type QR exists for this promo.
                                    $promoQr = QRCode::query()
                                        ->where('promotion_id', $promotionId)
                                        ->where('type', 'promotion')
                                        ->first();

                                    if (!$promoQr) {
                                        $sourceQr = $scan->qrCode;
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
                                        // Mark internal so it doesn't appear in business QR lists.
                                        'intended_use' => \App\Models\QRCode::INTENDED_USE_LEADERBOARD_PRIZE,
                                            'design' => $sourceDesign,
                                            'is_active' => true,
                                        ]);

                                        // Generate and persist a QR image path so portal/public promo pages can render it immediately.
                                        $qrGenerator = app(\App\Services\QRGeneratorService::class);
                                        $path = $qrGenerator->generateFile(
                                            $promoQr->getScanUrl(),
                                            $promoQr->getDesignWithDefaults(),
                                            'png'
                                        );
                                        $design = $promoQr->design ?? [];
                                        $design['generated_path'] = $path;
                                        $promoQr->updateQuietly(['design' => $design]);
                                    }

                                    // Ensure it shows up in Portal -> Saved promotions for this user.
                                    \App\Models\SavedQRCode::firstOrCreate(
                                        ['user_id' => $user->id, 'qr_code_id' => $promoQr->id],
                                        ['saved_at' => now()]
                                    );

                                    // Ensure a per-user token exists (UP-XXXX-XXXX) tied to the promo QR (NOT the QRcade/game QR).
                                    $token = $this->userPromoTokenService->ensure($user, $promoQr);
                                    if ($token?->code) {
                                        $tokenCode = $token->code;
                                        $data['_user_promo_token_code'] = $tokenCode;
                                        $winPlay->updateQuietly(['game_data' => $data]);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // Soft-fail: do not break scans page if backfill fails.
                        }
                    }

                    // If no token code was stored on the play, fall back to latest user promo token by promotion.
                    if (!is_string($tokenCode) || !str_starts_with($tokenCode, 'UP-')) {
                        try {
                            $qrCodeGame = $qrCodeGame ?? QRCodeGame::query()
                                ->where('qr_code_id', $scan->qr_code_id)
                                ->where('game_id', $winPlay->game_id)
                                ->first();
                            // For tiered mode, look up from tier_rewards instead of promotion_id.
                            $promotionId = 0;
                            if ($qrCodeGame?->win_mode === QRCodeGame::WIN_MODE_TIERED && $winPlay->reward_tier) {
                                $tierRewards = is_array($qrCodeGame->tier_rewards) ? $qrCodeGame->tier_rewards : [];
                                $promotionId = (int) ($tierRewards[$winPlay->reward_tier] ?? 0);
                            }
                            if ($promotionId <= 0) {
                                $promotionId = (int) ($qrCodeGame?->promotion_id ?? 0);
                            }
                            if ($promotionId > 0) {
                                $token = UserPromoToken::query()
                                    ->where('user_id', $user->id)
                                    ->where('promotion_id', $promotionId)
                                    ->orderByDesc('created_at')
                                    ->first();
                                if ($token?->code && str_starts_with($token->code, 'UP-')) {
                                    $tokenCode = $token->code;
                                }
                            }
                        } catch (\Throwable $e) {
                            // Soft-fail.
                        }
                    }

                    if (is_string($tokenCode) && str_starts_with($tokenCode, 'UP-')) {
                        $scan->reward_link = '/promo/' . $tokenCode . '?source=portal';
                        $scan->reward_kind = 'promo';
                        $scan->reward_promo_code = $tokenCode;
                        if (!empty($promotionId)) {
                            $scan->reward_promotion_id = (int) $promotionId;
                        }
                    }
                }
            }

            // 3c. If a reward was redeemed, reflect that on the scan row.
            if (
                !$scan->is_redeemed
                && !empty($scan->reward_data)
                && (($scan->reward_data['status'] ?? null) === GameReward::STATUS_REDEEMED)
            ) {
                $scan->is_redeemed = true;
                if (!isset($scan->redemption_discount_amount)) {
                    $scan->redemption_discount_amount = (float) ($scan->reward_data['discount_value'] ?? 0);
                }
                if (!isset($scan->redemption_at)) {
                    $scan->redemption_at = $scan->reward_data['redeemed_at'] ?? null;
                }
            }

            // 3d. If this scan represents a reward promo, reflect redemption status by promotion id.
            if (!$scan->is_redeemed && !empty($scan->reward_promotion_id)) {
                $promoId = (int) $scan->reward_promotion_id;
                if ($promoId > 0) {
                    if (!array_key_exists($promoId, $rewardPromoRedemptionCache)) {
                        $tokenCodes = UserPromoToken::where('user_id', $user->id)
                            ->where('promotion_id', $promoId)
                            ->pluck('code')
                            ->filter()
                            ->values();

                        $redemption = Redemption::where('promotion_id', $promoId)
                            ->where(function ($q) use ($user, $tokenCodes) {
                                $q->where('customer_user_id', $user->id);
                                if ($tokenCodes->count() > 0) {
                                    $q->orWhereIn('customer_identifier', $tokenCodes->all());
                                }
                            })
                            ->orderByDesc('redeemed_at')
                            ->first();

                        $rewardPromoRedemptionCache[$promoId] = $redemption;
                    }

                    $redemption = $rewardPromoRedemptionCache[$promoId] ?? null;
                    if ($redemption) {
                        $scan->setRelation('redemption', $redemption);
                        $scan->is_redeemed = true;
                        $scan->redemption_id = $redemption->id;
                        $scan->redemption_discount_amount = $redemption->discount_amount;
                        $scan->redemption_at = $redemption->redeemed_at;
                    }
                }
            }

            // 4. Leaderboard linking (for leaderboard challenges where there's no immediate reward to "view")
            $isQrcade = in_array(($scan->scan_type ?? null), [\App\Models\Scan::TYPE_QRCADE_GAME, \App\Models\Scan::TYPE_QRCADE_LEADERBOARD], true)
                || in_array(($scan->qrCode?->type ?? null), ['qrcade', 'qrcade_leaderboard'], true);
            if ($isQrcade) {
                $latestPlay = $latestPlayByQr->get($scan->qr_code_id);
                $businessId = $scan->business_id ?: ($scan->qrCode?->business_id);
                $gameId = $latestPlay?->game_id;

                $playResult = $latestPlay?->result;
                $isLeaderboardQr = ($scan->scan_type ?? null) === \App\Models\Scan::TYPE_QRCADE_LEADERBOARD
                    || ($scan->qrCode?->type ?? null) === 'qrcade_leaderboard';
                $modeKey = ($scan->qr_code_id && $gameId) ? ($scan->qr_code_id . ':' . $gameId) : null;
                $qrCodeGame = $modeKey ? ($qrCodeGameModes->get($modeKey) ?? null) : null;
                $winMode = $qrCodeGame?->win_mode;
                $isLeaderboardMode = $winMode
                    ? ($winMode === QRCodeGame::WIN_MODE_LEADERBOARD)
                    : $isLeaderboardQr;

                if ($isLeaderboardMode) {
                    $scan->play_again = false;
                    $scan->is_non_winner = false;

                    $lb = null;
                    if ($businessId && $gameId) {
                        $lb = $leaderboardsByBusinessGame->get($businessId . ':' . $gameId);
                    }
                    if (!$lb && $businessId) {
                        $lb = $locationLeaderboardsByBusiness->get($businessId);
                    }

                    if ($lb) {
                        $scan->leaderboard_link = '/portal/leaderboards/' . $lb->id;
                        $scan->leaderboard_name = $lb->name;
                    }

                    // Use the SCAN's creation date to determine which leaderboard period
                    // this entry belongs to. This ensures each scan only shows the reward
                    // from its own period — a new-week scan won't inherit last week's prize.
                    $scanTimestamp = $scan->created_at ?? $scan->scanned_at;
                    $scanPeriodKey = ($lb && $scanTimestamp) ? $periodKeyForDate($lb, $scanTimestamp) : null;
                    $reward = ($lb && $scanPeriodKey && isset($leaderboardRewardByPeriod[$lb->id][$scanPeriodKey]))
                        ? $leaderboardRewardByPeriod[$lb->id][$scanPeriodKey]
                        : null;

                    if ($reward) {
                        $scan->is_winner = true;
                        $scan->reward_link = '/portal/rewards/' . $reward->id;
                        $scan->reward_kind = 'reward';
                        $scan->reward_promotion_id = $reward->promotion_id ?? null;
                        $scan->reward_data = [
                            'id' => $reward->id,
                            'status' => $reward->status,
                            'reward_code' => $reward->reward_code,
                            'qr_image_url' => $reward->qr_image_path ? asset('storage/' . $reward->qr_image_path) : null,
                            'description' => $reward->description,
                            'discount_value' => $reward->discount_value,
                            'redeemed_at' => $reward->redeemed_at,
                        ];

                        $promoId = $reward->promotion_id ?? null;
                        $token = $promoId ? ($leaderboardTokensByPromotionId->get($promoId) ?? null) : null;
                        if ($token?->code && str_starts_with($token->code, 'UP-')) {
                            $scan->reward_promo_code = $token->code;
                        }
                    } else {
                        // No reward for this scan's period. Check if the period has ended
                        // to show "Period ended" state vs active "View Leaderboard".
                        $playTimestamp = $latestPlay?->completed_at ?? $latestPlay?->created_at;
                        $currentPeriodKey = $lb ? $periodKeyForDate($lb, now()) : null;
                        $userHasCurrentEntry = false;
                        if ($lb && $currentPeriodKey) {
                            $userHasCurrentEntry = \App\Models\LeaderboardEntry::where('leaderboard_id', $lb->id)
                                ->where('user_id', $user->id)
                                ->where('period_key', $currentPeriodKey)
                                ->exists();
                        }

                        // The scan's period has ended if:
                        // - The scan was created in a period before the current one, OR
                        // - The current period's end date has passed (scheduler hasn't reset yet)
                        // But if the user has an entry in the current period, they're still active.
                        $scanInOldPeriod = ($scanPeriodKey && $currentPeriodKey && $scanPeriodKey !== $currentPeriodKey);
                        $periodEnded = !$userHasCurrentEntry && $lb && (
                            $scanInOldPeriod
                            || ($lb->current_period_end && $lb->current_period_end->isPast())
                        );
                        if ($periodEnded) {
                            $scan->is_non_winner = true;
                            $scan->leaderboard_link = null;
                        }
                    }
                } else {
                    $scan->play_again = !$hasWin && ($playResult !== GamePlay::RESULT_WIN);
                    $scan->is_non_winner = false;
                }
            }

            // If the QR/promo was removed or deactivated, clear playable actions
            // so the user sees "Removed by business" + Delete instead of a broken link.
            if ($scan->removed_by_business) {
                $scan->play_again = false;
                $scan->play_link = null;
            }

                    return $scan;
        });

        $rewardPromoCodes = $scans->getCollection()
            ->pluck('reward_promo_code')
            ->filter()
            ->unique()
            ->values();

        $rewardPromoTokensByCode = collect();
        $rewardPromoRedemptionsByCode = collect();
        if ($rewardPromoCodes->count() > 0) {
            $rewardPromoTokensByCode = UserPromoToken::query()
                ->where('user_id', $user->id)
                ->whereIn('code', $rewardPromoCodes->all())
                ->get(['id', 'code', 'redeemed_at', 'redemption_id'])
                ->keyBy('code');

            $rewardPromoRedemptionsByCode = Redemption::query()
                ->whereIn('customer_identifier', $rewardPromoCodes->all())
                ->orderByDesc('redeemed_at')
                ->get(['id', 'customer_identifier', 'discount_amount', 'redeemed_at'])
                ->groupBy('customer_identifier');
        }

        $scans->setCollection(
            $scans->getCollection()->map(function ($scan) use ($rewardPromoTokensByCode, $rewardPromoRedemptionsByCode) {
                if (!$scan->is_redeemed && is_string($scan->reward_promo_code) && $scan->reward_promo_code !== '') {
                    $code = $scan->reward_promo_code;
                    $token = $rewardPromoTokensByCode->get($code);
                    $redemption = ($rewardPromoRedemptionsByCode->get($code) ?? collect())->first();

                    if (($token && $token->redeemed_at) || $redemption) {
                        $scan->is_redeemed = true;
                        if ($redemption) {
                            $scan->setRelation('redemption', $redemption);
                            $scan->redemption_id = $redemption->id;
                            $scan->redemption_discount_amount = $redemption->discount_amount;
                            $scan->redemption_at = $redemption->redeemed_at;
                        } elseif ($token && $token->redeemed_at) {
                            $scan->redemption_at = $token->redeemed_at;
                        }
                    }
                }

                if ($scan->is_redeemed && $scan->is_winner) {
                    $scan->is_winner = false;
                }

                return $scan;
            })
        );

        // De-duplicate scan rows that represent the same promo/reward.
        // Prefer entries with a reward link (View Reward), otherwise keep the most recent scan.
        // QRcade play-to-win: each play is a distinct activity entry.
        // QRcade leaderboard: scans sharing the same QR + reward collapse (same period);
        //   different rewards (different periods) remain separate entries.
        $scans->setCollection(
            $scans->getCollection()
                ->groupBy(function ($scan) {
                    // QRcade games: winning/rewarded plays stay as separate entries;
                    // non-winning plays (fun-only, play-again) collapse per QR code.
                    if (($scan->scan_type ?? null) === \App\Models\Scan::TYPE_QRCADE_GAME) {
                        if ($scan->is_winner || !empty($scan->reward_link) || !empty($scan->reward_promo_code)) {
                            return 'scan:' . $scan->id;
                        }
                        return 'qrcade_game:' . ($scan->qr_code_id ?? $scan->id);
                    }

                    // QRcade leaderboard: collapse scans that share the same
                    // QR code + reward promo code (same period/reward).
                    // Different rewards (different periods) remain separate entries.
                    if (($scan->scan_type ?? null) === \App\Models\Scan::TYPE_QRCADE_LEADERBOARD) {
                        $lbPromo = $scan->reward_promo_code ?? null;
                        if ($lbPromo) {
                            return 'lb_qr:' . $scan->qr_code_id . ':' . $lbPromo;
                        }
                        // No reward yet — group by QR so re-scans within a period collapse.
                        return 'lb_qr:' . $scan->qr_code_id;
                    }

                    // Leaderboard wins: use reward ID so each period's win stays visible.
                    $rewardId = $scan->reward_data['id'] ?? null;
                    if ($rewardId && ($scan->is_winner ?? false)) {
                        return 'lb_reward:' . $rewardId;
                    }

                    // Multi-scan promotions: each scan with its own token is a distinct entry.
                    $multiScan = (bool) ($scan->qrCode?->promotion?->rules['portal_multiple_scans'] ?? false);
                    if ($multiScan && !empty($scan->customer_promo['code'])) {
                        return 'multi:' . $scan->id;
                    }

                    $promoId = $scan->reward_promotion_id ?? null;
                    if (is_numeric($promoId) && (int) $promoId > 0) {
                        return 'promo_id:' . (int) $promoId;
                    }

                    $promoCode = $scan->reward_promo_code
                        ?? ($scan->customer_promo['code'] ?? null)
                        ?? null;
                    if (is_string($promoCode) && $promoCode !== '') {
                        return 'promo:' . $promoCode;
                    }
                    return 'qr:' . ($scan->qr_code_id ?? 0) . ':' . ($scan->scan_type ?? '');
                })
                ->map(function ($group) {
                    $withReward = $group->first(function ($scan) {
                        return !empty($scan->reward_link) || !empty($scan->reward_promo_code);
                    });
                    if ($withReward) {
                        return $withReward;
                    }
                    return $group->sortByDesc(function ($scan) {
                        return $scan->scanned_at ?? $scan->created_at ?? null;
                    })->first();
                })
                ->values()
        );

        // Ensure every active (claimed/available) game reward appears in Recent
        // Activity so the carousel and Recent Activity stay in sync.  A reward
        // may be missing because: (a) its scan was paginated to a later page,
        // (b) its scan was deleted by the user, or (c) it never had a scan
        // (e.g. leaderboard prizes awarded via scheduler).
        $visibleRewardIds = $scans->getCollection()
            ->pluck('reward_data')
            ->filter()
            ->pluck('id')
            ->filter()
            ->unique();

        $activeRewards = GameReward::where('user_id', $user->id)
            ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
            ->whereNotIn('id', $visibleRewardIds->all())
            ->with([
                'business:id,name,logo_path',
                'promotion' => fn($q) => $q->withTrashed(),
                'gamePlay:id,qr_code_id,game_id',
            ])
            ->get();

        if ($activeRewards->count() > 0) {
            // Look up promo tokens for these rewards
            $missingPromoIds = $activeRewards->pluck('promotion_id')->filter()->unique()->values();
            $missingTokensByPromoId = $missingPromoIds->count() > 0
                ? UserPromoToken::query()
                    ->where('user_id', $user->id)
                    ->whereIn('promotion_id', $missingPromoIds->all())
                    ->orderByDesc('created_at')
                    ->get(['id', 'promotion_id', 'code', 'redeemed_at', 'redemption_id'])
                    ->groupBy('promotion_id')
                    ->map(fn ($g) => $g->first())
                : collect();

            // Try to find existing scans for these rewards (via game QR codes)
            $gamePlayQrIds = $activeRewards->pluck('gamePlay.qr_code_id')->filter()->unique()->values();
            // Also look for scans via the actual game QR codes (through QRCodeGame)
            $gameIds = $activeRewards->pluck('gamePlay.game_id')->filter()->unique()->values();
            $gameQrIds = collect();
            if ($gameIds->count() > 0) {
                $gameQrIds = QRCodeGame::whereIn('game_id', $gameIds->all())
                    ->pluck('qr_code_id')
                    ->filter()
                    ->unique();
            }
            $allPossibleQrIds = $gamePlayQrIds->merge($gameQrIds)->unique()->values();

            $existingScans = collect();
            if ($allPossibleQrIds->count() > 0) {
                $scanIds = Scan::where('user_id', $user->id)
                    ->whereIn('qr_code_id', $allPossibleQrIds->all())
                    ->selectRaw('MAX(id) as id')
                    ->groupBy('qr_code_id')
                    ->pluck('id');

                if ($scanIds->count() > 0) {
                    $existingScans = Scan::whereIn('id', $scanIds->all())
                        ->with([
                            'qrCode' => fn($q) => $q->select('id','code','name','type','promotion_id','business_id','is_active')->withTrashed(),
                            'qrCode.business:id,name,logo_path',
                            'business:id,name,logo_path',
                        ])
                        ->get()
                        ->keyBy('qr_code_id');
                }
            }

            $syntheticEntries = collect();

            foreach ($activeRewards as $reward) {
                $promoId = $reward->promotion_id;
                $token = $promoId ? ($missingTokensByPromoId->get($promoId) ?? null) : null;
                $promoCode = ($token?->code && str_starts_with($token->code, 'UP-')) ? $token->code : null;

                // If the associated promo token has already been redeemed, skip
                // creating an active entry. The GameReward status should have been
                // updated to "redeemed" during the redemption flow, but this
                // safeguards against stale data (e.g. from earlier bugs).
                if ($token && $token->redeemed_at) {
                    continue;
                }

                // Try to find an existing scan for this reward
                $scan = null;
                $playQrId = $reward->gamePlay?->qr_code_id;
                if ($playQrId && $existingScans->has($playQrId)) {
                    $scan = $existingScans->get($playQrId);
                }
                if (!$scan) {
                    // Try game QR codes through QRCodeGame
                    $gameId = $reward->gamePlay?->game_id;
                    if ($gameId) {
                        $qrIds = QRCodeGame::where('game_id', $gameId)->pluck('qr_code_id');
                        foreach ($qrIds as $qrId) {
                            if ($existingScans->has($qrId)) {
                                $scan = $existingScans->get($qrId);
                                break;
                            }
                        }
                    }
                }

                if ($scan) {
                    // Enhance existing scan with reward data
                    $scan = clone $scan;
                } else {
                    // Create a synthetic scan-like entry from the GameReward
                    $scan = new Scan();
                    $scan->id = -$reward->id; // negative ID to avoid collision
                    $scan->user_id = $user->id;
                    $scan->qr_code_id = $playQrId;
                    $scan->business_id = $reward->business_id;
                    $scan->scanned_at = $reward->claimed_at ?? $reward->created_at;
                    $scan->scan_type = 'qrcade_leaderboard';

                    // Load QR code info if available
                    if ($playQrId) {
                        $scan->setRelation('qrCode', QRCode::withTrashed()
                            ->select('id','code','name','type','promotion_id','business_id','is_active')
                            ->find($playQrId));
                    }
                    $scan->setRelation('business', $reward->business);
                }

                // Set reward enhancement properties
                $scan->is_redeemed = false;
                $scan->removed_by_business = false;
                $scan->promo_expired = false;
                $scan->is_winner = true;
                $scan->is_non_winner = false;
                $scan->play_again = false;
                $scan->stack_count = 1;
                $scan->customer_promo = null;
                $scan->leaderboard_link = null;
                $scan->play_link = $scan->qrCode?->code ? ('/play/' . $scan->qrCode->code) : null;
                $scan->reward_kind = 'reward';
                $scan->reward_promotion_id = $promoId;
                $scan->reward_promo_code = $promoCode;
                $scan->reward_link = $promoCode
                    ? ('/promo/' . $promoCode . '?source=portal')
                    : ('/portal/rewards/' . $reward->id);
                $scan->reward_data = [
                    'id' => $reward->id,
                    'status' => $reward->status,
                    'reward_code' => $reward->reward_code,
                    'description' => $reward->description,
                    'discount_value' => $reward->discount_value,
                ];

                $syntheticEntries->push($scan);
            }

            if ($syntheticEntries->count() > 0) {
                $scans->setCollection(
                    $syntheticEntries->merge($scans->getCollection())->values()
                );
            }
        }

        // Get unique QR codes scanned (for promotions list)
        // Note: we no longer build a separate scanned promo library list for this page.

        // Get saved QR codes (not just scanned).
        // Include deleted/deactivated promos so the user can see "Removed by business" and clean up.
        $allowedSavedTypes = ['promotion', 'cross_promo', 'stackable', 'level_exclusive'];
        $savedQRCodesQuery = SavedQRCode::where('user_id', $user->id)
            ->with(['qrCode' => fn($q) => $q->withTrashed(), 'qrCode.promotion' => fn($q) => $q->withTrashed(), 'qrCode.business:id,name,logo_path'])
            ->whereHas('qrCode', function ($query) use ($allowedSavedTypes) {
                $query->whereIn('type', $allowedSavedTypes)->withTrashed();
            })
            ->whereDoesntHave('qrCode.promotion', function ($q) {
                $q->where('discount_type', Promotion::TYPE_PUNCH_CARD);
            });

        // Filter by redeemed status if requested, otherwise default to excluding redeemed from saved list
        if ($status === 'redeemed') {
            $savedQRCodesQuery->whereHas('qrCode', function ($q) use ($redeemedPromotionQrIds) {
                $q->whereIn('id', $redeemedPromotionQrIds->all());
            });
        } elseif ($redeemedPromotionQrIds->count() > 0) {
            $savedQRCodesQuery->whereHas('qrCode', function ($q) use ($redeemedPromotionQrIds) {
                $q->whereNotIn('id', $redeemedPromotionQrIds->all());
            });
        }

        // Apply the same filters to saved promos (so the UI filters behave intuitively)
        if ($promotionType) {
            $savedQRCodesQuery->whereHas('qrCode.promotion', function ($query) use ($promotionType) {
                if ($promotionType === Promotion::TYPE_FIXED_AMOUNT) {
                    $query->whereIn('discount_type', [Promotion::TYPE_FIXED_AMOUNT, 'fixed']);
                    return;
                }
                $query->where('discount_type', $promotionType);
            });
        }

        if ($businessId) {
            $savedQRCodesQuery->whereHas('qrCode', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        }

        if ($status === 'active') {
            $savedQRCodesQuery->where(function ($q) {
                $q->whereHas('qrCode.promotion', function ($qp) {
                    $qp->where('is_active', true)
                        ->where(function ($qq) {
                            $qq->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                        })
                        ->where(function ($qq) {
                            $qq->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                        });
                })
                ->orWhereHas('qrCode', function ($qq) {
                    $qq->whereIn('type', ['cross_promo', 'stackable'])
                        ->where('is_active', true)
                        ->where(function ($qexp) {
                            $qexp->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                        });
                });
            });
        } elseif ($status === 'expired') {
            $savedQRCodesQuery->where(function ($q) {
                $q->whereHas('qrCode.promotion', function ($qp) {
                    $qp->where(function ($qq) {
                        $qq->where('is_active', false)
                            ->orWhere('ends_at', '<', now());
                    });
                })
                ->orWhereHas('qrCode', function ($qq) {
                    $qq->whereIn('type', ['cross_promo', 'stackable'])
                        ->where(function ($qexp) {
                            $qexp->where('is_active', false)
                                ->orWhere('expires_at', '<', now());
                        });
                });
            });
        }

        if (is_string($savedQ) && trim($savedQ) !== '') {
            $needle = '%' . trim($savedQ) . '%';
            $savedQRCodesQuery->where(function ($q) use ($needle) {
                $q->whereHas('qrCode', function ($qq) use ($needle) {
                    $qq->where('name', 'like', $needle)
                        ->orWhere('code', 'like', $needle)
                        ->orWhereHas('business', function ($qb) use ($needle) {
                            $qb->where('name', 'like', $needle);
                        })
                        ->orWhereHas('promotion', function ($qp) use ($needle) {
                            $qp->where('name', 'like', $needle)
                                ->orWhere('description', 'like', $needle);
                        });
                });
            });
        }

        $savedPromotions = $savedQRCodesQuery
            ->orderByDesc('saved_at')
            ->paginate(15, ['*'], 'saved_page')
            ->withQueryString();

        // Ensure per-user tokens exist for the saved promos on this page (so UI can show QR + code).
        $savedQrIds = $savedPromotions->getCollection()->pluck('qr_code_id')->unique()->values();
        if ($savedQrIds->count() > 0) {
            $qrCodesForTokens = QRCode::query()
                ->whereIn('id', $savedQrIds)
                ->with('promotion')
                ->get()
                ->keyBy('id');

            $activeTokensByQrId = UserPromoToken::query()
                ->where('user_id', $user->id)
                ->whereIn('qr_code_id', $savedQrIds->all())
                ->whereNull('redeemed_at')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('qr_code_id');

            foreach ($savedQrIds as $qrId) {
                $qr = $qrCodesForTokens->get($qrId);
                if ($qr) {
                    $activeTokens = $activeTokensByQrId->get($qrId) ?? collect();
                    $this->userPromoTokenService->ensure(
                        $user,
                        $qr,
                        $activeTokens->count(),
                        $activeTokens->first()
                    );
                }
            }
        }

        $allTokensByQrId = UserPromoToken::query()
            ->where('user_id', $user->id)
            ->whereIn('qr_code_id', $savedQrIds->all())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('qr_code_id');

        $savedPromotions->setCollection(
            $savedPromotions->getCollection()->map(function ($saved) use ($activeScanCounts, $allTokensByQrId, $user) {
                $qrCode = $saved->qrCode;
                $promotion = $qrCode?->promotion;
                $qrTokens = $qrCode ? ($allTokensByQrId->get($qrCode->id) ?? collect()) : collect();
                $token = $qrTokens->first();

                // Detect deleted/deactivated promos so the user can clean up.
                $promoDeleted = $promotion?->trashed() ?? false;
                $qrDeleted = $qrCode?->trashed() ?? false;
                $promoMissing = (!$promotion && !empty($qrCode?->promotion_id));
                $qrDeactivated = $qrCode && !$qrCode->trashed() && ($qrCode->is_active === false);
                $removedByBusiness = $promoDeleted || $promoMissing || $qrDeleted || $qrDeactivated
                    || ($promotion && !$promotion->is_active);

                // Check if promotion is active
                $isActive = true;
                $redeemMessage = null;
                $isDailyLimit = false;

                if ($removedByBusiness) {
                    $isActive = false;
                    $redeemMessage = 'Removed by business';
                } elseif ($promotion) {
                    $canRedeem = $promotion->canRedeem(
                        $token?->code,
                        $user->id,
                        ['current_qr_code_id' => $qrCode->id]
                    );
                    $isActive = $canRedeem['allowed'];
                    $redeemMessage = $canRedeem['reason'];
                    $isDailyLimit = str_contains(strtolower($redeemMessage ?? ''), 'daily limit');
                }

                // Explicit QR-level expiry/inactive overrides
                if (!$removedByBusiness) {
                    if ($qrCode && $qrCode->expires_at && $qrCode->expires_at->isPast()) {
                        $isActive = false;
                        $redeemMessage = 'This QR code has expired';
                    } elseif ($isDailyLimit) {
                        $isActive = true;
                    }
                }

                $rules = is_array($promotion?->rules) ? $promotion->rules : [];
                $isMultiScan = (bool) ($rules['portal_multiple_scans'] ?? false);
                $unredeemedTokens = $isMultiScan
                    ? $qrTokens->filter(fn ($t) => !$t->redeemed_at)->values()
                    : collect();

                $entry = [
                    'qr_code' => [
                        'id' => $qrCode?->id,
                        'code' => $qrCode?->code,
                        'name' => $qrCode?->name,
                        'image_url' => $qrCode?->image_url,
                        'promotion_id' => $promotion?->id,
                    ],
                    'promotion_id' => $promotion?->id,
                    'customer_promo' => $token ? [
                        'code' => $token->code,
                        'qr_image_url' => $token->qr_image_url,
                        'redeemed_at' => optional($token->redeemed_at)?->toISOString(),
                    ] : null,
                    'promotion' => $promotion ? [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'description' => $promotion->description,
                        'discount_type' => $promotion->discount_type,
                        'display_value' => $promotion->getDisplayDescription(),
                        'is_punch_card' => $promotion->discount_type === 'punch_card',
                        'punches_required' => $promotion->punches_required,
                        'starts_at' => $promotion->starts_at?->format('M d, Y'),
                        'ends_at' => $promotion->ends_at?->format('M d, Y'),
                    ] : null,
                    'business' => $qrCode?->business ? [
                        'id' => $qrCode->business->id,
                        'name' => $qrCode->business->name,
                        'logo_url' => $qrCode->business->logo_url,
                    ] : null,
                    'saved_at' => $saved->saved_at->format('M d, Y'),
                    'is_saved' => true,
                    'is_active' => $isActive,
                    'removed_by_business' => $removedByBusiness,
                    'redeem_message' => $redeemMessage,
                    'stack_count' => (int)($activeScanCounts->get($qrCode?->id) ? ($activeScanCounts->get($qrCode->id)->cnt ?? 0) : 0),
                    '_multi_tokens' => $unredeemedTokens,
                ];

                return $entry;
            })
        );

        // Expand multi-scan promotions: one carousel card per active unredeemed token.
        $savedPromotions->setCollection(
            $savedPromotions->getCollection()->flatMap(function ($entry) {
                $multiTokens = $entry['_multi_tokens'] ?? collect();
                unset($entry['_multi_tokens']);
                if ($multiTokens->count() <= 1) {
                    return collect([$entry]);
                }
                return $multiTokens->map(function ($tok) use ($entry) {
                    $card = $entry;
                    $card['customer_promo'] = [
                        'code' => $tok->code,
                        'qr_image_url' => $tok->qr_image_url,
                        'redeemed_at' => optional($tok->redeemed_at)?->toISOString(),
                    ];
                    return $card;
                });
            })->values()
        );

        // No longer returning a merged "promotionLibrary" list; the UI uses savedPromotions only.

        // Get unique businesses for filter dropdown
        // Get unique businesses for filter dropdown (avoid loading all scans)
        $businessIdsFromScans = Scan::query()
            ->where('user_id', $user->id)
            ->whereNotNull('business_id')
            ->distinct()
            ->pluck('business_id');

        $businessesFromScans = \App\Models\Business::query()
            ->whereIn('id', $businessIdsFromScans->all())
            ->get(['id', 'name', 'logo_path']);

        $businessesFromSaved = SavedQRCode::where('user_id', $user->id)
            ->with('qrCode.business:id,name,logo_path')
            ->get()
            ->pluck('qrCode.business')
            ->filter();

        $businesses = $businessesFromScans->concat($businessesFromSaved)
            ->unique('id')
            ->values()
            ->map(function($business) {
                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'logo_url' => $business->logo_url,
                ];
            });

        // Get punch cards for this user
        $punchCardsQuery = PunchCard::query();

        // Prefer account linkage but support legacy identifiers (older rows may have email/phone as customer_identifier)
        $legacyIdentifiers = collect([
            $customerCode,
            $user->email,
            $user->phone,
        ])->filter(fn ($v) => is_string($v) && trim($v) !== '')->map(fn ($v) => trim($v))->unique()->values();

        $punchCardsQuery->where(function ($q) use ($user, $legacyIdentifiers) {
            $q->where('user_id', $user->id);
            if ($legacyIdentifiers->count() > 0) {
                $q->orWhereIn('customer_identifier', $legacyIdentifiers->all());
            }
        });

        $punchCardRecords = $punchCardsQuery
            ->with(['promotion.business:id,name,logo_path', 'promotion.qrCodes:id,promotion_id,code'])
            ->get()
            ->tap(function ($cards) use ($user, $customerCode, $legacyIdentifiers) {
                // Best-effort backfill: if the user had legacy punch cards, link them to the account + stable customer_code.
                if (!$customerCode || $legacyIdentifiers->count() === 0) {
                    return;
                }

                $cardIds = $cards->pluck('id')->values();
                if ($cardIds->count() === 0) {
                    return;
                }

                PunchCard::whereIn('id', $cardIds)
                    ->whereNull('user_id')
                    ->update([
                        'user_id' => $user->id,
                        'customer_identifier' => $customerCode,
                    ]);

                PunchCard::whereIn('id', $cardIds)
                    ->where('user_id', $user->id)
                    ->where(function ($q) use ($customerCode) {
                        $q->whereNull('customer_identifier')
                            ->orWhere('customer_identifier', '!=', $customerCode);
                    })
                    ->update([
                        'customer_identifier' => $customerCode,
                    ]);
            });

        // Get all tokens for punch card promotions that this user has scanned
        $userTokens = UserPromoToken::query()
            ->where('user_id', $user->id)
            ->whereHas('qrCode.promotion', function ($q) {
                $q->where('discount_type', Promotion::TYPE_PUNCH_CARD);
            })
            ->with(['qrCode.promotion.business:id,name,logo_path'])
            ->get();

        // Build punch cards array — one entry per punch card record (promotion).
        // Guard: track which punch_card_ids we've already added to prevent duplicates
        // when multiple tokens point at the same punch card (same promo, same or different QR codes).
        $punchCards = collect();
        $addedPunchCardIds = [];
        
        foreach ($userTokens as $token) {
            if (!$token->qrCode || !$token->qrCode->promotion) {
                continue;
            }

            $promo = $token->qrCode->promotion;
            
            // Find the punch card record for this promotion
            $punchCardRecord = $punchCardRecords->firstWhere('promotion_id', $token->qrCode->promotion_id);
            
            if (!$punchCardRecord) {
                // Enforce punch-card caps before creating a new card record
                if ($promo->punch_card_total_cards_limit) {
                    $totalCardsIssued = PunchCard::where('promotion_id', $promo->id)->count();
                    if ($totalCardsIssued >= $promo->punch_card_total_cards_limit) {
                        // Do not create a new card; cap reached
                        continue;
                    }
                }

                if ($promo->punch_card_max_cards_per_user) {
                    $userCard = PunchCard::where('promotion_id', $promo->id)
                        ->where('user_id', $user->id)
                        ->first();

                    $userCardsUsed = 0;
                    if ($userCard) {
                        $userCardsUsed = ($userCard->completed_cards ?? 0);
                        if (($userCard->punches ?? 0) > 0) {
                            $userCardsUsed += 1;
                        }
                    }

                    if ($userCardsUsed >= $promo->punch_card_max_cards_per_user) {
                        continue;
                    }
                }

                // Create punch card if it doesn't exist.
                // IMPORTANT: multiple tokens can point at the same punch-card promotion, so we MUST avoid duplicate inserts.
                $identifier = $customerCode ?? app(CustomerCodeService::class)->getOrCreate($user);

                $punchCardRecord = PunchCard::firstOrCreate(
                    [
                        'promotion_id' => $token->qrCode->promotion_id,
                        'customer_identifier' => $identifier,
                    ],
                    [
                        'user_id' => $user->id,
                        'punches' => 0,
                        'completed_cards' => 0,
                    ]
                );

                // If this was an existing legacy card without linkage, link it now.
                if (!$punchCardRecord->user_id) {
                    $punchCardRecord->forceFill(['user_id' => $user->id])->saveQuietly();
                }

                // Ensure subsequent tokens in this request can see it without re-querying.
                $punchCardRecords->push($punchCardRecord);

                $punchCardRecord->load('promotion.business:id,name,logo_path');
            }
            
            // Only one carousel card per punch card record — skip if already added.
            if (in_array($punchCardRecord->id, $addedPunchCardIds, true)) {
                continue;
            }

            // No-grandfathering: if the promotion/QR is disabled or expired, treat the punch card as inactive immediately.
            $promo = $punchCardRecord->promotion;
            $qr = $token->qrCode;
            $isActive = true;
            $removedByBusiness = false;
            if (!$promo || !$qr) {
                $isActive = false;
                $removedByBusiness = true;
            } else {
                $promoGone = $promo->trashed() || !$promo->is_active;
                $qrGone = $qr->trashed() || !$qr->is_active;
                if ($promoGone || $qrGone) {
                    $removedByBusiness = true;
                    $isActive = false;
                }
                if (!$promo->isCurrentlyValid()) {
                    $isActive = false;
                }
                if ($qr->expires_at && $qr->expires_at->isPast()) {
                    $isActive = false;
                }
            }

            $required = (int)($punchCardRecord->promotion?->punches_required ?? 0);

            $addedPunchCardIds[] = $punchCardRecord->id;
            $punchCards->push([
                'id' => $punchCardRecord->id . '_' . $token->qr_code_id,
                'punch_card_id' => $punchCardRecord->id,
                'is_active' => $isActive && !($punchCardRecord->punches === 0 && $punchCardRecord->completed_cards > 0),
                'removed_by_business' => $removedByBusiness,
                'punch_card_state' => [
                    'punches' => $punchCardRecord->punches,
                    'completed_cards' => $punchCardRecord->completed_cards,
                    'completed_and_reset' => ($punchCardRecord->punches === 0 && $punchCardRecord->completed_cards > 0),
                ],
                'promotion' => $punchCardRecord->promotion ? [
                    'id' => $punchCardRecord->promotion->id,
                    'name' => $punchCardRecord->promotion->name,
                    'punches_required' => $punchCardRecord->promotion->punches_required,
                    'punch_icon' => $punchCardRecord->promotion->getPunchIcon(),
                    'qr_code' => [
                        'code' => $token->qrCode->code ?? '',
                    ],
                ] : null,
                'business' => $punchCardRecord->promotion?->business ? [
                    'id' => $punchCardRecord->promotion->business->id,
                    'name' => $punchCardRecord->promotion->business->name,
                    'logo_url' => $punchCardRecord->promotion->business->logo_url,
                ] : null,
                'customer_promo' => [
                    'code' => $token->code,
                    'qr_image_url' => $token->qr_image_url,
                ],
                'current_punches' => $punchCardRecord->punches,
                'completed_cards' => $punchCardRecord->completed_cards,
                'last_punch_at' => $punchCardRecord->last_punch_at?->diffForHumans(),
                'progress_percent' => $required > 0
                    ? round(((int)$punchCardRecord->punches / $required) * 100)
                    : 0,
            ]);
        }
        
        // If there are punch card records without tokens, add them too (fallback)
        foreach ($punchCardRecords as $punchCardRecord) {
            // Already added from the token loop — skip.
            if (in_array($punchCardRecord->id, $addedPunchCardIds, true)) {
                continue;
            }

            $hasToken = $userTokens->contains(function ($token) use ($punchCardRecord) {
                return $token->qrCode && $token->qrCode->promotion_id === $punchCardRecord->promotion_id;
            });
            
            if (!$hasToken && $punchCardRecord->promotion) {
                // Get first QR code and ensure token exists
                $qrCode = $punchCardRecord->promotion->qrCodes?->first();

                // Don't recreate tokens for promos/QRs that are no longer available,
                // or when the user deliberately deleted all their scans for this promotion.
                $skipFallback = false;
                if ($qrCode) {
                    $fbPromo = $punchCardRecord->promotion;
                    if (!$fbPromo->isCurrentlyValid() || !$qrCode->is_active || ($qrCode->expires_at && $qrCode->expires_at->isPast())) {
                        $skipFallback = true;
                    }

                    if (!$skipFallback) {
                        $promoQrIds = $punchCardRecord->promotion->qrCodes->pluck('id')->all();
                        $userHasScans = Scan::where('user_id', $user->id)
                            ->whereIn('qr_code_id', $promoQrIds)
                            ->exists();
                        if (!$userHasScans) {
                            $skipFallback = true;
                        }
                    }
                }

                if ($qrCode && !$skipFallback) {
                    $userPromoTokenService = app(UserPromoTokenService::class);
                    $token = $userPromoTokenService->ensure($user, $qrCode);
                    
                    if ($token) {
                        $promo = $punchCardRecord->promotion;
                        $isActive = true;
                        $removedByBusiness = false;
                        if (!$promo || !$qrCode) {
                            $isActive = false;
                            $removedByBusiness = true;
                        } else {
                            $promoGone = $promo->trashed() || !$promo->is_active;
                            $qrGone = $qrCode->trashed() || !$qrCode->is_active;
                            if ($promoGone || $qrGone) {
                                $removedByBusiness = true;
                                $isActive = false;
                            }
                            if (!$promo->isCurrentlyValid()) {
                                $isActive = false;
                            }
                            if ($qrCode->expires_at && $qrCode->expires_at->isPast()) {
                                $isActive = false;
                            }
                        }

                        $required = (int)($punchCardRecord->promotion?->punches_required ?? 0);
                        $addedPunchCardIds[] = $punchCardRecord->id;
                        $punchCards->push([
                            'id' => $punchCardRecord->id . '_' . $qrCode->id,
                            'punch_card_id' => $punchCardRecord->id,
                            'is_active' => $isActive && !($punchCardRecord->punches === 0 && $punchCardRecord->completed_cards > 0),
                            'removed_by_business' => $removedByBusiness,
                            'punch_card_state' => [
                                'punches' => $punchCardRecord->punches,
                                'completed_cards' => $punchCardRecord->completed_cards,
                                'completed_and_reset' => ($punchCardRecord->punches === 0 && $punchCardRecord->completed_cards > 0),
                            ],
                            'promotion' => [
                                'id' => $punchCardRecord->promotion->id,
                                'name' => $punchCardRecord->promotion->name,
                                'punches_required' => $punchCardRecord->promotion->punches_required,
                                'punch_icon' => $punchCardRecord->promotion->getPunchIcon(),
                                'qr_code' => [
                                    'code' => $qrCode->code ?? '',
                                ],
                            ],
                            'business' => $punchCardRecord->promotion?->business ? [
                                'id' => $punchCardRecord->promotion->business->id,
                                'name' => $punchCardRecord->promotion->business->name,
                                'logo_url' => $punchCardRecord->promotion->business->logo_url,
                            ] : null,
                            'customer_promo' => [
                                'code' => $token->code,
                                'qr_image_url' => $token->qr_image_url,
                            ],
                            'current_punches' => $punchCardRecord->punches,
                            'completed_cards' => $punchCardRecord->completed_cards,
                            'last_punch_at' => $punchCardRecord->last_punch_at?->diffForHumans(),
                            'progress_percent' => $required > 0
                                ? round(((int)$punchCardRecord->punches / $required) * 100)
                                : 0,
                        ]);
                    }
                }
            }
        }
        
        // Deduplicate by punch_card_id — keep first entry per punch card record.
        $punchCards = $punchCards->unique('punch_card_id')->values();

        // Remove from carousel: completed+reset cards, and cards where business deleted/deactivated the promotion.
        $punchCards = $punchCards->filter(function ($c) {
            if ($c['punch_card_state']['completed_and_reset'] ?? false) {
                return false;
            }
            if ($c['removed_by_business'] ?? false) {
                return false;
            }
            return true;
        })->values();

        // Calculate stats: total_redeemed and total_savings (reuse logic from PortalRewardController)
        $identifiers = collect([
            $customerCode,
            $user->email,
            $user->phone,
        ])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->unique()
            ->values();

        // Get GameReward redemption IDs to avoid double-counting
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

        $rewardsRedeemedCount = GameReward::where('user_id', $user->id)
            ->where('status', GameReward::STATUS_REDEEMED)
            ->count();

        // Count ALL redemptions for this user (including punch card stamps, but excluding game reward redemptions)
        // This matches what's shown in the "Recent Scans" list
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
            ->whereHas('qrCode', function ($q) {
                $q->where('type', 'promotion')->withTrashed(); // Only count promotion redemptions
            })
            ->count(); // Count ALL redemptions, not just non-punch-card ones

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
                    $promoQ->where('discount_type', '!=', Promotion::TYPE_PUNCH_CARD)->withTrashed();
                })
                ->orWhere(function ($punchQ) {
                    $punchQ->where('card_completed', true)
                        ->where('discount_amount', '>', 0)
                        ->whereHas('promotion', function ($promoQ) {
                            $promoQ->where('discount_type', Promotion::TYPE_PUNCH_CARD)->withTrashed();
                        });
                });
            })
            ->sum('discount_amount');

        // Get claimed/available rewards to show in the portal.
        // Deduplicate by promotion_id so repeat leaderboard wins (same promotion
        // awarded each period) don't produce duplicate carousel cards.
        $rewardRows = GameReward::where('user_id', $user->id)
            ->whereIn('status', [GameReward::STATUS_CLAIMED, GameReward::STATUS_AVAILABLE])
            ->with(['business:id,name,logo_path,primary_color', 'promotion' => fn($q) => $q->withTrashed()])
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('promotion_id')
            ->values();

        $rewardPromotionIds = $rewardRows->pluck('promotion_id')->filter()->unique()->values();
        $promoQrByPromotionId = collect();
        $promoTokensByQrId = collect();
        if ($rewardPromotionIds->count() > 0) {
            $promoQrByPromotionId = QRCode::withTrashed()
                ->whereIn('promotion_id', $rewardPromotionIds->all())
                ->where('type', 'promotion')
                ->get(['id', 'promotion_id'])
                ->groupBy('promotion_id')
                ->map(fn ($group) => $group->first());

            $promoQrIds = $promoQrByPromotionId->pluck('id')->filter()->values();
            if ($promoQrIds->count() > 0) {
                $promoTokensByQrId = UserPromoToken::query()
                    ->where('user_id', $user->id)
                    ->whereIn('qr_code_id', $promoQrIds->all())
                    ->orderByDesc('created_at')
                    ->get(['id', 'qr_code_id', 'code', 'qr_image_path', 'redeemed_at'])
                    ->groupBy('qr_code_id')
                    ->map(fn ($group) => $group->first());
            }
        }

        $claimedRewards = $rewardRows->map(function($reward) use ($promoQrByPromotionId, $promoTokensByQrId, $savedQRCodeIds) {
            $promoQr = $reward->promotion_id ? $promoQrByPromotionId->get($reward->promotion_id) : null;
            $promoToken = $promoQr ? $promoTokensByQrId->get($promoQr->id) : null;
            $promoIsSaved = $promoQr ? in_array($promoQr->id, $savedQRCodeIds, true) : false;
            $customerPromo = null;

            if ($promoToken) {
                $customerPromo = [
                    'code' => $promoToken->code,
                    'qr_image_url' => $promoToken->qr_image_url,
                    'redeemed_at' => optional($promoToken->redeemed_at)?->toISOString(),
                ];
            } elseif ($reward->reward_code || $reward->qr_image_url) {
                $customerPromo = [
                    'code' => $reward->reward_code,
                    'qr_image_url' => $reward->qr_image_url,
                    'redeemed_at' => optional($reward->redeemed_at)?->toISOString(),
                ];
            }

            $displayCode = (is_string($reward->reward_code) && str_starts_with($reward->reward_code, 'UP-'))
                ? $reward->reward_code
                : null;
            $isActive = in_array($reward->status, [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED], true)
                && (!$reward->expires_at || !$reward->expires_at->isPast());
            $promotion = $reward->promotion;
            return [
                'id' => $reward->id,
                'promotion_id' => $reward->promotion_id,
                'promotion' => $promotion ? [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'discount_type' => $promotion->discount_type,
                    'is_punch_card' => $promotion->discount_type === Promotion::TYPE_PUNCH_CARD,
                ] : null,
                'reward_code' => $reward->reward_code,
                'display_code' => $displayCode,
                'status' => $reward->status,
                'description' => $reward->description,
                'discount_value' => $reward->discount_value,
                'reward_type' => $reward->reward_type,
                'display_value' => $reward->getDisplayValue(),
                'qr_image_url' => $reward->qr_image_url,
                'customer_promo' => $customerPromo,
                'promo_qr_code_id' => $promoQr?->id,
                'promo_is_saved' => $promoIsSaved,
                'business' => [
                    'id' => $reward->business?->id,
                    'name' => $reward->business?->name,
                    'logo_url' => $reward->business?->logo_url,
                    'primary_color' => $reward->business?->primary_color,
                ],
                'expires_at' => $reward->expires_at?->format('M d, Y'),
                'is_active' => $isActive,
            ];
        });

        return Inertia::render('Portal/Scans', [
            'scans' => $scans,
            'savedPromotions' => $savedPromotions,
            'claimedRewards' => $claimedRewards,
            'punchCards' => $punchCards,
            'customerCode' => $customerCode,
            'businesses' => $businesses,
            'discountTypes' => Promotion::discountTypes(),
            'filters' => [
                'promotion_type' => $promotionType,
                'business_id' => $businessId,
                'status' => $status,
                'cross_promo_only' => $crossPromoOnly,
            ],
            'savedFilters' => [
                'q' => $savedQ,
            ],
            'stats' => [
                // Matches the deduped "Recent Scans" display
                // Count scan EVENTS (not unique QR codes). This will increase for multi-scan promos,
                // but for one-time promos (where we refresh the same scan row) it will stay stable.
                'total_scans' => Scan::where('user_id', $user->id)
                    ->when(!empty($onboardingQrIds), function ($q) use ($onboardingQrIds) {
                        $q->where(function ($inner) use ($onboardingQrIds) {
                            $inner->whereNull('qr_code_id')->orWhereNotIn('qr_code_id', $onboardingQrIds);
                        });
                    })
                    ->count(),
                // UI now focuses on Saved Promotions in My Promotions
                'promotions_scanned' => $savedPromotions->total(),
                // Count actual punch-card progress rows (not just punch-card promos)
                'active_punch_cards' => $punchCards->count(),
                // Count only saved PROMOTION QR codes, and match what the UI renders
                'saved_count' => $savedPromotions->total(),
                'won_count' => GamePlay::where('user_id', $user->id)->where('result', GamePlay::RESULT_WIN)->count(),
                // New stats: total_redeemed and total_savings
                'total_redeemed' => $rewardsRedeemedCount + $promoRedeemedCount,
                'total_savings' => round($rewardSavings + $promoSavings, 2),
            ],
        ]);
    }

    /**
     * Delete a scan entry from the user's history when a business removes the QR/promo.
     */
    public function destroy(Request $request, Scan $scan)
    {
        $user = $request->user();
        if (!$user || $scan->user_id !== $user->id) {
            abort(403);
        }

        $scan->load(['qrCode' => fn($q) => $q->withTrashed(), 'qrCode.promotion' => fn($q) => $q->withTrashed()]);

        $qrGone = !$scan->qrCode && !empty($scan->qr_code_id);
        $qrDeleted = (bool) ($scan->qrCode?->trashed());
        $qrDeactivated = $scan->qrCode && !$scan->qrCode->trashed() && ($scan->qrCode->is_active === false);
        $promoDeleted = (bool) ($scan->qrCode?->promotion?->trashed());
        $promoMissing = (!$scan->qrCode?->promotion && !empty($scan->qrCode?->promotion_id));
        $isNonWinner = false;

        // Allow deletion when the promotion has expired
        $promoExpired = false;
        $promo = $scan->qrCode?->promotion;
        if ($promo && (($promo->ends_at && $promo->ends_at->isPast()) || !$promo->is_active)) {
            $promoExpired = true;
        }

        $isQrcade = in_array(($scan->scan_type ?? null), [\App\Models\Scan::TYPE_QRCADE_GAME, \App\Models\Scan::TYPE_QRCADE_LEADERBOARD], true)
            || in_array(($scan->qrCode?->type ?? null), ['qrcade', 'qrcade_leaderboard'], true);
        if ($isQrcade && $scan->qr_code_id) {
            $latestPlay = GamePlay::query()
                ->where('user_id', $user->id)
                ->where('qr_code_id', $scan->qr_code_id)
                ->orderByDesc('created_at')
                ->first(['id', 'result']);

            if ($latestPlay && $latestPlay->result !== GamePlay::RESULT_WIN) {
                $isNonWinner = true;
            }
        }

        if (!($qrGone || $qrDeleted || $qrDeactivated || $promoDeleted || $promoMissing || $isNonWinner || $promoExpired)) {
            return back()->with('error', 'This scan cannot be deleted.');
        }

        $qrCodeId = $scan->qr_code_id;

        $scan->delete();

        // Also clean up SavedQRCode and UserPromoToken so the entry
        // disappears from carousels (Saved Promotions, My Punch Cards) too.
        if ($qrCodeId) {
            \App\Models\SavedQRCode::where('user_id', $user->id)
                ->where('qr_code_id', $qrCodeId)
                ->delete();

            \App\Models\UserPromoToken::where('user_id', $user->id)
                ->where('qr_code_id', $qrCodeId)
                ->delete();
        }

        return back()->with('success', 'Scan removed.');
    }
}
