<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmAiRecommendation;
use App\Models\Promotion;
use App\Models\Redemption;
use App\Models\Scan;
use App\Services\DeepSeekAIService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecommendationsController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        $recs = CrmAiRecommendation::where('business_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return Inertia::render('Business/CRM/Recommendations', [
            'recommendations' => $recs->map(fn (CrmAiRecommendation $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'payload' => $r->payload,
                'created_at' => $r->created_at?->format('M d, Y H:i'),
            ]),
            'aiConfigured' => app(DeepSeekAIService::class)->isConfigured(),
            'campaignsUrl' => route('business.crm.campaigns.create'),
        ]);
    }

    public function generate(Request $request, DeepSeekAIService $ai)
    {
        $business = $request->user()->business;

        $since = now()->subDays(30);
        $scans30 = Scan::where('business_id', $business->id)->where('scanned_at', '>=', $since)->count();
        $redemptions30 = Redemption::where('business_id', $business->id)->where('redeemed_at', '>=', $since)->count();
        $uniqueCustomers30 = Scan::where('business_id', $business->id)
            ->whereNotNull('user_id')
            ->where('scanned_at', '>=', $since)
            ->distinct('user_id')
            ->count('user_id');

        $topPromos = Promotion::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'discount_type', 'discount_value', 'starts_at', 'ends_at']);

        $context = [
            'business' => [
                'name' => $business->name,
                'type' => $business->type,
                'city' => $business->city,
                'state' => $business->state,
                'subscription_tier' => $business->subscription_tier,
            ],
            'last_30_days' => [
                'scans' => $scans30,
                'redemptions' => $redemptions30,
                'unique_customers' => $uniqueCustomers30,
                'estimated_conversion' => $scans30 > 0 ? round(($redemptions30 / $scans30) * 100, 2) . '%' : 'n/a',
            ],
            'top_promotions' => $topPromos->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'discount_type' => $p->discount_type,
                'discount_value' => $p->discount_value,
                'starts_at' => $p->starts_at?->toDateString(),
                'ends_at' => $p->ends_at?->toDateString(),
            ])->values(),
        ];

        // Always generate something: AI if configured, otherwise a safe heuristic fallback.
        $payload = null;
        $type = 'campaign_ideas';

        if ($ai->isConfigured()) {
            $prompt = "Create 5 high-performing email campaign ideas for this business. Each idea must include:\n"
                . "- title\n- audience (who to target)\n- audience_filters (object with keys: last_seen_days, min_scans, min_redemptions, min_saved, min_level; set to null if not needed)\n- subject_line\n- short_body (2-4 sentences)\n- offer_structure\n- promo (pick ONE from top_promotions by id, or null if no promo should be attached)\n- priority (high/medium/low)\n"
                . "Also include 3 automation ideas (welcome/winback/punch-card).\n\n"
                . "Return STRICT JSON with keys: campaigns (array), automations (array). Each campaigns[i] must include: title, audience, audience_filters, subject_line, short_body, offer_structure, promo_id (number|null), promo_name (string|null), priority.";

            $result = $ai->generateInsight($prompt, $context, true);
            $payload = [
                'ai_success' => (bool) ($result['success'] ?? false),
                'ai_content' => $result['content'] ?? null,
            ];

            // Try to parse JSON out of the response
            $parsed = null;
            if (!empty($result['content']) && preg_match('/\{.*\}/s', $result['content'], $m)) {
                $tmp = json_decode($m[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $parsed = $tmp;
                }
            }

            if ($parsed) {
                $payload['recommendations'] = $this->sanitizeAiRecommendations($parsed);
            }
        }

        if (!$payload || empty($payload['recommendations'])) {
            $payload = [
                'recommendations' => [
                    'campaigns' => [
                        [
                            'title' => 'VIP thank-you + limited claim',
                            'audience' => 'Customers who redeemed before',
                            'subject_line' => 'VIP-only: limited spots for a deal this week',
                            'short_body' => 'You’re one of our best customers. Show this message in-store for a limited reward. First come, first served.',
                            'offer_structure' => 'Limited quantity + clear expiration',
                            'priority' => 'high',
                        ],
                        [
                            'title' => 'New week / fresh deal',
                            'audience' => 'All subscribers',
                            'subject_line' => 'This week’s deal is live (scan + save)',
                            'short_body' => 'We just dropped a new promotion. Save it to your wallet so it’s ready at checkout.',
                            'offer_structure' => 'Simple % off or $ off with clear minimum spend',
                            'priority' => 'medium',
                        ],
                    ],
                    'automations' => [
                        [
                            'title' => 'Welcome flow',
                            'trigger' => 'Subscribe',
                            'message' => 'Thanks for subscribing — here’s how to save and redeem from your portal.',
                        ],
                        [
                            'title' => 'Winback',
                            'trigger' => 'No scans in 30 days',
                            'message' => 'We miss you — here’s a quick comeback offer.',
                        ],
                    ],
                ],
                'ai_success' => false,
            ];
        }

        CrmAiRecommendation::create([
            'business_id' => $business->id,
            'type' => $type,
            'payload' => $payload,
            'status' => 'new',
        ]);

        return back()->with('success', 'Recommendations generated.');
    }

    /**
     * Sanitize AI-generated recommendation content to remove any HTML/script tags
     * and truncate excessively long fields. Vue {{ }} auto-escapes HTML, but this
     * provides defense-in-depth against stored malicious content.
     */
    protected function sanitizeAiRecommendations(array $data): array
    {
        $maxFieldLength = 2000;
        $textFields = ['title', 'audience', 'subject_line', 'short_body', 'offer_structure', 'message', 'trigger', 'promo_name', 'description'];

        // Sanitize campaigns array
        if (isset($data['campaigns']) && is_array($data['campaigns'])) {
            $data['campaigns'] = array_map(function ($campaign) use ($textFields, $maxFieldLength) {
                if (!is_array($campaign)) {
                    return $campaign;
                }
                foreach ($textFields as $field) {
                    if (isset($campaign[$field]) && is_string($campaign[$field])) {
                        $campaign[$field] = mb_substr(strip_tags($campaign[$field]), 0, $maxFieldLength);
                    }
                }
                return $campaign;
            }, $data['campaigns']);

            // Cap at 10 campaigns max (AI could return more than requested)
            $data['campaigns'] = array_slice($data['campaigns'], 0, 10);
        }

        // Sanitize automations array
        if (isset($data['automations']) && is_array($data['automations'])) {
            $data['automations'] = array_map(function ($automation) use ($textFields, $maxFieldLength) {
                if (!is_array($automation)) {
                    return $automation;
                }
                foreach ($textFields as $field) {
                    if (isset($automation[$field]) && is_string($automation[$field])) {
                        $automation[$field] = mb_substr(strip_tags($automation[$field]), 0, $maxFieldLength);
                    }
                }
                return $automation;
            }, $data['automations']);

            $data['automations'] = array_slice($data['automations'], 0, 10);
        }

        return $data;
    }
}

