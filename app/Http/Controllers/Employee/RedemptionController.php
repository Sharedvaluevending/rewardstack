<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\QRCode;
use App\Models\Promotion;
use App\Models\Redemption;
use App\Models\PunchCard;
use App\Models\Scan;
use App\Models\MerchTag;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Models\SavedQRCode;
use App\Models\GameReward;
use App\Notifications\PortalPunchCardCompleted;
use App\Services\BusinessCustomerService;
use App\Services\MerchReferralRewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Helpers\DatabaseHelper;
use Jenssegers\Agent\Agent;

class RedemptionController extends Controller
{
    protected function getMerchReferralRedeemOptions(?QRCode $qrCode): array
    {
        if (!$qrCode || ($qrCode->type ?? null) !== 'merch_referral') {
            return [];
        }

        return [
            'force_max_redemptions_per_user' => 1,
            'ignore_time_limits' => true,
            'ignore_total_limit' => true,
            'ignore_daily_limit' => true,
            'ignore_time_window' => true,
        ];
    }
    public function index(Request $request)
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return Inertia::render('Employee/NoEmployer');
        }

        // Get today's redemptions by this employee
        $todayRedemptions = Redemption::where('employee_id', $employee->id)
            ->whereDate('redeemed_at', today())
            ->with('promotion:id,name,discount_type')
            ->orderByDesc('redeemed_at')
            ->get();

        $todayStats = [
            'total' => $todayRedemptions->count(),
            'savings' => $todayRedemptions->sum('discount_amount'),
        ];

        return Inertia::render('Employee/Redeem', [
            'employee' => $employee,
            'business' => $employee->business->only(['id', 'name', 'logo_path']),
            'todayRedemptions' => $todayRedemptions,
            'todayStats' => $todayStats,
        ]);
    }

    /**
     * Quick redeem page - accessed from customer promo page
     */
    public function quickRedeem(Request $request, string $code)
    {
        $user = $request->user();
        
        // Must be logged in
        if (!$user) {
            return redirect()->route('login', ['redirect' => "/redeem/{$code}"]);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        // Business owners are identified via the owned Business relation (users table has no business_id).
        $ownedBusiness = $user->business;
        $isBusinessOwner = ($user->role === 'business' && $ownedBusiness);

        // Employees must be registered + have redeem permission. Business owners can quick-redeem their own QR codes.
        if (!$employee && !$isBusinessOwner) {
            return Inertia::render('Employee/NoEmployer', [
                'message' => 'You must be registered as an employee to redeem promotions.',
            ]);
        }

        if ($employee && !$employee->can_redeem) {
            return Inertia::render('Employee/NoEmployer', [
                'message' => 'Your account does not have permission to redeem promotions.',
            ]);
        }

        // Check if this is a reward code
        // Reward codes can be:
        // 1. UP-XXXX-XXXX (current reward format)
        // 2. UUID format: 8-4-4-4-12 (legacy)
        // 3. Short 8-char codes (legacy)
        // Also handle URL-encoded codes and trim whitespace
        $code = trim(urldecode($code));
        $codeUpper = strtoupper($code);
        $exactReward = GameReward::whereRaw('UPPER(reward_code) = ?', [$codeUpper])
            ->where(function ($q) use ($employee, $ownedBusiness) {
                $userBusinessId = $employee ? $employee->business_id : $ownedBusiness?->id;
                if ($userBusinessId) {
                    $q->where('business_id', $userBusinessId);
                }
            })
            ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
            ->first();

        if ($exactReward) {
            return redirect()->route('redeem.reward', ['rewardCode' => $exactReward->reward_code]);
        }
        
        // Check for full UUID format (legacy)
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $code)) {
            // This is a full reward code, redirect to reward redemption
            return redirect()->route('redeem.reward', ['rewardCode' => $code]);
        }
        
        // Check if this is a short code (8 characters) or partial match
        // Allow lookup by exact match or first 8 chars for easier manual entry
        if (preg_match('/^[0-9A-F]{8}$/i', $codeUpper) || preg_match('/^[0-9A-F]{1,8}$/i', $codeUpper)) {
            $reward = GameReward::where(function ($q) use ($code, $codeUpper) {
                    // Match exact code (case-insensitive) or codes starting with this prefix
                    $q->where('reward_code', '=', $code)
                      ->orWhere('reward_code', '=', $codeUpper)
                      ->orWhere('reward_code', 'like', strtolower($code) . '%')
                      ->orWhere('reward_code', 'like', $codeUpper . '%');
                })
                ->where(function ($q) use ($employee, $ownedBusiness) {
                    $userBusinessId = $employee ? $employee->business_id : $ownedBusiness?->id;
                    if ($userBusinessId) {
                        $q->where('business_id', $userBusinessId);
                    }
                })
                ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
                ->first();
            
            if ($reward) {
                // Found a matching reward, redirect to full redemption
                return redirect()->route('redeem.reward', ['rewardCode' => $reward->reward_code]);
            }
        }

        // Check if this is a customer promo code (UP-XXXX-XXXX format)
        // Handle both with and without dashes for flexibility
        $normalizedCode = strtoupper(str_replace('-', '', $code));
        if (str_starts_with($code, 'UP-') || (str_starts_with($normalizedCode, 'UP') && strlen($normalizedCode) === 10)) {
            // Try to find token with dashes first
            $token = UserPromoToken::where('code', $code)->first();
            
            // If not found with dashes, try without dashes (normalize)
            if (!$token && strlen($normalizedCode) === 10) {
                $token = UserPromoToken::whereRaw('REPLACE(UPPER(code), "-", "") = ?', [$normalizedCode])->first();
            }
            
            if ($token) {
                // Redirect to token redemption flow
                return redirect()->route('redeem.token', ['tokenCode' => $token->code]);
            } else {
                return Inertia::render('Public/ScanError', [
                    'message' => 'Customer promo code not found',
                ]);
            }
        }

        // Find QR code
        $qrCode = QRCode::where('code', $code)
            ->with(['promotion', 'business:id,name,logo_path,primary_color'])
            ->first();

        if (!$qrCode) {
            return Inertia::render('Public/ScanError', [
                'message' => 'QR code not found',
            ]);
        }

        // Verify this QR belongs to the user's business (employee or business owner)
        $userBusinessId = $employee ? $employee->business_id : $ownedBusiness?->id;
        if ($qrCode->business_id !== $userBusinessId) {
            $yourBusinessName = $employee
                ? $employee->business->name
                : ($ownedBusiness?->name ?? 'Your Business');

            return Inertia::render('Employee/WrongBusiness', [
                'message' => 'This QR code belongs to a different business.',
                'qrBusiness' => $qrCode->business->name,
                'yourBusiness' => $yourBusinessName,
            ]);
        }

        if (!$qrCode->promotion) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This QR code does not have a promotion attached',
            ]);
        }

        $promotion = $qrCode->promotion;
        $business = $qrCode->business;
        $canRedeem = $promotion->canRedeem(null, null, $this->getMerchReferralRedeemOptions($qrCode));

        // Get punch card progress if applicable
        $punchCardProgress = null;
        if ($promotion->discount_type === Promotion::TYPE_PUNCH_CARD && $user) {
            $punchCard = $promotion->punchCards()
                ->where('user_id', $user->id)
                ->first();
            
            $punchCardProgress = [
                'current_punches' => $punchCard?->punches ?? 0,
                'completed_cards' => $punchCard?->completed_cards ?? 0,
            ];
        }

        return Inertia::render('Employee/QuickRedeem', [
            'qrCode' => $qrCode->only(['id', 'code', 'name']),
            'promotion' => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'discount_type' => $promotion->discount_type,
                'display_value' => $promotion->getDisplayDescription(),
                'original_price' => $promotion->original_price,
                'final_price' => $promotion->getFinalPrice(),
                'discount_value' => $promotion->discount_value,
                'minimum_purchase' => $promotion->minimum_purchase,
                'maximum_discount' => $promotion->maximum_discount,
                'buy_quantity' => $promotion->buy_quantity,
                'get_quantity' => $promotion->get_quantity,
                'for_price' => $promotion->for_price,
                'reward_value' => $promotion->reward_value,
                'punches_required' => $promotion->punches_required,
                'tiers' => $promotion->tiers,
                'rules' => $promotion->rules,
                'needs_calculator' => $promotion->needsCalculator(),
            ],
            'business' => [
                'name' => $business->name,
                'logo_url' => $business->logo_url,
            ],
            'employee' => [
                'id' => $employee?->id,
                'name' => $user->name,
            ],
            'canRedeem' => $canRedeem['allowed'],
            'redeemMessage' => $canRedeem['reason'],
            'prefillCustomerPromoCode' => null,
            'punchCardProgress' => $punchCardProgress,
            'calculatorInfo' => [
                'needs_calculator' => $promotion->needsCalculator(),
                'prefills' => $promotion->getCalculatorPrefills(),
            ],
        ]);
    }

    /**
     * Quick redeem via a per-user promo token (customer shows this to staff).
     */
    public function quickRedeemToken(Request $request, string $tokenCode)
    {
        $user = $request->user();

        // Must be logged in
        if (!$user) {
            return redirect()->route('login', ['redirect' => "/redeem/token/{$tokenCode}"]);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $ownedBusiness = $user->business;
        $isBusinessOwner = ($user->role === 'business' && $ownedBusiness);

        if (!$employee && !$isBusinessOwner) {
            return Inertia::render('Employee/NoEmployer', [
                'message' => 'You must be registered as an employee to redeem promotions.',
            ]);
        }

        if ($employee && !$employee->can_redeem) {
            return Inertia::render('Employee/NoEmployer', [
                'message' => 'Your account does not have permission to redeem promotions.',
            ]);
        }

        // Handle both with and without dashes for flexibility
        // Normalize the code: remove dashes and uppercase for comparison
        $normalizedCode = strtoupper(str_replace('-', '', $tokenCode));
        
        // Try exact match first (with dashes)
        $token = UserPromoToken::query()
            ->where('code', $tokenCode)
            ->with(['qrCode.promotion', 'qrCode.business:id,name,logo_path,primary_color'])
            ->first();
        
        // If not found and code looks like UP-XXXX-XXXX format, try without dashes
        if (!$token && (str_starts_with($tokenCode, 'UP-') || str_starts_with($normalizedCode, 'UP')) && strlen($normalizedCode) === 10) {
            $token = UserPromoToken::query()
                ->whereRaw('REPLACE(UPPER(code), "-", "") = ?', [$normalizedCode])
                ->with(['qrCode.promotion', 'qrCode.business:id,name,logo_path,primary_color'])
                ->first();
        }

        if (!$token || !$token->qrCode || !$token->qrCode->promotion) {
            return Inertia::render('Public/ScanError', [
                'message' => 'Customer promo code not found',
            ]);
        }

        $qrCode = $token->qrCode;
        $promotion = $qrCode->promotion;
        $business = $qrCode->business;

        // Verify this token belongs to the user's business (employee or owner)
        $userBusinessId = $employee ? $employee->business_id : $ownedBusiness?->id;
        if ($qrCode->business_id !== $userBusinessId) {
            $yourBusinessName = $employee
                ? $employee->business->name
                : ($ownedBusiness?->name ?? 'Your Business');

            return Inertia::render('Employee/WrongBusiness', [
                'message' => 'This promo belongs to a different business.',
                'qrBusiness' => $business->name,
                'yourBusiness' => $yourBusinessName,
            ]);
        }

        // Get customer identifier for limit checking
        $customerIdentifier = $token->code; // Use token code as customer identifier
        
        // Check if token can be reused
        // For non-punch-card promotions, tokens can be reused if max_redemptions_per_user allows multiple redemptions
        $canReuseToken = false;
        if ($promotion->discount_type !== Promotion::TYPE_PUNCH_CARD && $token->redeemed_at) {
            $rules = $promotion->rules ?? [];
            
            // Check if per-user limit allows multiple redemptions
            if (isset($rules['max_redemptions_per_user']) && $rules['max_redemptions_per_user'] > 0) {
                // Count how many times this user has redeemed this promotion (across all their scans)
                $query = Redemption::where('promotion_id', $promotion->id);
                if ($token->user_id) {
                    $query->where('customer_user_id', $token->user_id);
                } else {
                    $query->where('customer_identifier', $token->code);
                }
                
                $userRedemptionCount = $query->count();
                
                // If user hasn't reached their limit, allow token reuse
                if ($userRedemptionCount < $rules['max_redemptions_per_user']) {
                    $canReuseToken = true;
                }
            }
            // If max_redemptions_per_user is not set (unlimited), allow reuse
            if (!isset($rules['max_redemptions_per_user']) || $rules['max_redemptions_per_user'] === 0) {
                $canReuseToken = true;
            }
            
            // If token can't be reused, block redemption
            if (!$canReuseToken) {
                return Inertia::render('Employee/QuickRedeem', [
                    'qrCode' => $qrCode->only(['id', 'code', 'name']),
                    'promotion' => [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'description' => $promotion->description,
                        'discount_type' => $promotion->discount_type,
                        'display_value' => $promotion->getDisplayDescription(),
                        'original_price' => $promotion->original_price,
                        'final_price' => $promotion->getFinalPrice(),
                        'discount_value' => $promotion->discount_value,
                        'minimum_purchase' => $promotion->minimum_purchase,
                        'maximum_discount' => $promotion->maximum_discount,
                        'buy_quantity' => $promotion->buy_quantity,
                        'get_quantity' => $promotion->get_quantity,
                        'for_price' => $promotion->for_price,
                        'tiers' => $promotion->tiers,
                        'needs_calculator' => $promotion->needsCalculator(),
                    ],
                    'business' => [
                        'name' => $business->name,
                        'logo_url' => $business->logo_url,
                    ],
                    'employee' => [
                        'id' => $employee?->id,
                        'name' => $user->name,
                    ],
                    'canRedeem' => false,
                    'redeemMessage' => 'This customer promo has already been redeemed.',
                    'prefillCustomerPromoCode' => $token->code,
                    'calculatorInfo' => [
                        'needs_calculator' => $promotion->needsCalculator(),
                        'prefills' => $promotion->getCalculatorPrefills(),
                    ],
                ]);
            }
        }

        $redeemOptions = $this->getMerchReferralRedeemOptions($qrCode);

        // Leaderboard prize tokens: per-user limits were already enforced per-period
        // at award time (PrizeService), so bypass the global per-user limit here.
        if (($qrCode->intended_use ?? null) === QRCode::INTENDED_USE_LEADERBOARD_PRIZE) {
            $redeemOptions['force_max_redemptions_per_user'] = 9999;
        }

        $canRedeem = $promotion->canRedeem($token->code, $token->user_id, $redeemOptions);

        // Get punch card progress if applicable
        $punchCardProgress = null;
        if ($promotion->discount_type === Promotion::TYPE_PUNCH_CARD) {
            $punchCard = $promotion->punchCards()
                ->where('user_id', $token->user_id)
                ->first();
            
            $punchCardProgress = [
                'current_punches' => $punchCard?->punches ?? 0,
                'completed_cards' => $punchCard?->completed_cards ?? 0,
            ];
        }

        return Inertia::render('Employee/QuickRedeem', [
            'qrCode' => $qrCode->only(['id', 'code', 'name']),
            'promotion' => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'discount_type' => $promotion->discount_type,
                'display_value' => $promotion->getDisplayDescription(),
                'original_price' => $promotion->original_price,
                'final_price' => $promotion->getFinalPrice(),
                'discount_value' => $promotion->discount_value,
                'minimum_purchase' => $promotion->minimum_purchase,
                'maximum_discount' => $promotion->maximum_discount,
                'buy_quantity' => $promotion->buy_quantity,
                'get_quantity' => $promotion->get_quantity,
                'for_price' => $promotion->for_price,
                'reward_value' => $promotion->reward_value,
                'punches_required' => $promotion->punches_required,
                'tiers' => $promotion->tiers,
                'rules' => $promotion->rules,
                'needs_calculator' => $promotion->needsCalculator(),
            ],
            'business' => [
                'name' => $business->name,
                'logo_url' => $business->logo_url,
            ],
            'employee' => [
                'id' => $employee?->id,
                'name' => $user->name,
            ],
            'canRedeem' => $canRedeem['allowed'],
            'redeemMessage' => $canRedeem['reason'],
            'prefillCustomerPromoCode' => $token->code,
            'punchCardProgress' => $punchCardProgress,
            'calculatorInfo' => [
                'needs_calculator' => $promotion->needsCalculator(),
                'prefills' => $promotion->getCalculatorPrefills(),
            ],
        ]);
    }

    /**
     * Return token / promotion info for a customer promo code (AJAX).
     * Used by staff UI to prefill punch-card state and prefilled amounts
     * when the staff types a customer's promo code before submitting redemption.
     */
    public function tokenInfo(Request $request, string $tokenCode)
    {
        $tokenCode = trim(urldecode($tokenCode));
        // Handle both with and without dashes for flexibility
        $normalizedCode = strtoupper(str_replace('-', '', $tokenCode));

        // Try exact match first (with dashes)
        $token = UserPromoToken::query()
            ->where('code', $tokenCode)
            ->with(['qrCode.promotion'])
            ->first();

        // If not found and code looks like UP-XXXX-XXXX format, try without dashes
        if (!$token && (str_starts_with($tokenCode, 'UP-') || str_starts_with($normalizedCode, 'UP')) && strlen($normalizedCode) === 10) {
            $token = UserPromoToken::query()
                ->whereRaw('REPLACE(UPPER(code), "-", "") = ?', [$normalizedCode])
                ->with(['qrCode.promotion'])
                ->first();
        }

        if (!$token || !$token->qrCode || !$token->qrCode->promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Customer promo code not found',
            ], 404);
        }

        $promotion = $token->qrCode->promotion;

        $punchCardProgress = null;
        if ($promotion->discount_type === Promotion::TYPE_PUNCH_CARD && $token->user_id) {
            $punchCard = $promotion->punchCards()
                ->where('user_id', $token->user_id)
                ->first();

            $punchCardProgress = [
                'current_punches' => $punchCard?->punches ?? 0,
                'completed_cards' => $punchCard?->completed_cards ?? 0,
            ];
        }

        $promoPayload = [
            'id' => $promotion->id,
            'discount_type' => $promotion->discount_type,
            'original_price' => $promotion->original_price,
            'reward_value' => $promotion->reward_value,
            'punches_required' => $promotion->punches_required,
            'needs_calculator' => $promotion->needsCalculator(),
        ];

        // Include Buy X Get Y / Buy X For Y fields for item price inputs
        if (in_array($promotion->discount_type, [Promotion::TYPE_BUY_X_GET_Y, Promotion::TYPE_BUY_X_FOR_Y], true)) {
            $rules = $promotion->rules ?? [];
            $promoPayload['buy_quantity'] = $promotion->buy_quantity ?? 1;
            $promoPayload['get_quantity'] = $promotion->get_quantity ?? 1;
            $promoPayload['for_price'] = $promotion->for_price;
            $promoPayload['rules'] = [
                'buy_item_prices' => $rules['buy_item_prices'] ?? [],
                'get_item_prices' => $rules['get_item_prices'] ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'token' => [
                'code' => $token->code,
                'user_id' => $token->user_id,
                'redeemed_at' => $token->redeemed_at,
            ],
            'promotion' => $promoPayload,
            'punch_card_progress' => $punchCardProgress,
        ]);
    }

    public function redeem(Request $request, string $code)
    {
        $code = trim(urldecode($code));
        $user = $request->user();
        $businessId = null;
        $employeeId = null;
        $isInertiaRequest = $request->header('X-Inertia');

        // Check if user is a business owner
        // Business owners are identified via the owned Business relation (users table has no business_id).
        $ownedBusiness = $user->business;
        if ($user->role === 'business' && $ownedBusiness) {
            $businessId = $ownedBusiness->id;
        } 
        // Check if user is an employee
        else {
            $employee = Employee::where('user_id', $user->id)->first();

            if (!$employee || !$employee->can_redeem) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to redeem promotions',
                ], 403);
            }

            $businessId = $employee->business_id;
            $employeeId = $employee->id;
        }

        $token = null;
        $customerIdentifier = trim((string) $request->input('customer_identifier', ''));
        $canReuseToken = false;

        // If the staff typed/scanned a per-user customer promo code directly, redeem via token.
        // Handle both with and without dashes for flexibility
        $normalizedCode = strtoupper(str_replace('-', '', $code));
        
        if (str_starts_with($code, 'UP-') || (str_starts_with($normalizedCode, 'UP') && strlen($normalizedCode) === 10)) {
            // Try exact match first (with dashes)
            $token = UserPromoToken::query()
                ->where('code', $code)
                ->with(['user', 'qrCode.promotion'])
                ->first();
            
            // If not found and code looks like UP-XXXX-XXXX format, try without dashes
            if (!$token && strlen($normalizedCode) === 10) {
                $token = UserPromoToken::query()
                    ->whereRaw('REPLACE(UPPER(code), "-", "") = ?', [$normalizedCode])
                    ->with(['user', 'qrCode.promotion'])
                    ->first();
            }

            if (!$token || !$token->qrCode || !$token->qrCode->promotion) {
                $msg = 'Customer promo code not found';
                if ($isInertiaRequest) {
                    return back()->withErrors(['message' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 404);
            }

            if ((int)$token->business_id !== (int)$businessId) {
                $msg = 'This customer promo belongs to a different business';
                if ($isInertiaRequest) {
                    return back()->withErrors(['message' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            $qrCode = $token->qrCode;
            $promotion = $qrCode->promotion;
            $customerIdentifier = $token->code;
        } else {
            // Traditional flow: staff has a promo QR code and must provide the customer's per-user promo code.
            $qrCode = QRCode::where('code', $code)
                ->where('business_id', $businessId)
                ->with('promotion')
                ->first();

            if (!$qrCode) {
                $msg = 'QR code not found or does not belong to your business';
                if ($isInertiaRequest) {
                    return back()->withErrors(['message' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 404);
            }

            if (!$qrCode->promotion) {
                $msg = 'This QR code does not have a promotion attached';
                if ($isInertiaRequest) {
                    return back()->withErrors(['message' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 400);
            }

            $promotion = $qrCode->promotion;

            if ($customerIdentifier === '') {
                $msg = 'Customer Promo Code is required.';
                if ($isInertiaRequest) {
                    return back()->withErrors(['message' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 400);
            }

            $token = UserPromoToken::query()
                ->where('code', $customerIdentifier)
                ->with('user')
                ->first();

            if (!$token) {
                $msg = 'Customer Promo Code not found.';
                if ($isInertiaRequest) {
                    return back()->withErrors(['message' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 400);
            }

            if ((int)$token->qr_code_id !== (int)$qrCode->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This customer promo code does not match this promotion.',
                ], 400);
            }
        }

        // Resolve customer user via token (always required for reliable portal linkage)
        $customerUser = $token?->user;
        if (!$customerUser) {
            return response()->json([
                'success' => false,
                'message' => 'Customer Promo Code is required.',
            ], 400);
        }

        // Prevent staff from redeeming their own promo / punching their own card.
        // Business owners are allowed to redeem for themselves (testing / demo).
        if ($customerUser->id === $user->id && $user->role !== 'business') {
            $msg = 'You cannot redeem a promotion for yourself.';
            if ($isInertiaRequest) {
                return back()->withErrors(['message' => $msg])->withInput();
            }
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 403);
        }

        // Check if token has already been redeemed
        // In the "Scan Every Time" model, every scan generates a new token which is one-time use.
        if ($promotion->discount_type !== Promotion::TYPE_PUNCH_CARD && $token->redeemed_at) {
            $msg = 'This customer promo has already been redeemed. Please scan again for a new code.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 400);
            }
            return back()->withErrors(['message' => $msg]);
        }

        $isInertiaRequest = $request->header('X-Inertia');

        // Check if promotion can be redeemed (enforces per-customer limits using token code)
        // Note: Daily limit check will be re-checked inside transaction with lock
        $redeemOptions = $this->getMerchReferralRedeemOptions($qrCode);

        // Leaderboard prize tokens: per-user limits were already enforced per-period
        // at award time (PrizeService), so bypass the global per-user limit here.
        if (($qrCode->intended_use ?? null) === QRCode::INTENDED_USE_LEADERBOARD_PRIZE) {
            $redeemOptions['force_max_redemptions_per_user'] = 9999;
        }

        $canRedeem = $promotion->canRedeem($customerIdentifier, $customerUser->id, $redeemOptions);

        if (!$canRedeem['allowed']) {
            if ($isInertiaRequest) {
                return back()->withErrors(['message' => $canRedeem['reason']])->withInput();
            }
            return response()->json([
                'success' => false,
                'message' => $canRedeem['reason'],
            ], 400);
        }

        // Calculate discount
        // For promotions that need a calculator, require original_amount to be provided
        $originalAmount = (float) $request->input('original_amount', 0);
        $quantity = (int) $request->input('quantity', 1);
        
        // For punch cards: only require price when redeeming the final prize (card completed)
        // For regular punches, no price is needed
        $isPunchCard = ($promotion->discount_type === Promotion::TYPE_PUNCH_CARD);
        $isFinalPrize = false;
        
        if ($isPunchCard) {
            // Check if this redemption will complete the card
            $punchCard = $promotion->punchCards()
                ->where('user_id', $customerUser->id)
                ->first();
            
            $currentPunches = $punchCard ? $punchCard->punches : 0;
            // If the card is already full, this redemption is the prize (no new punch)
            $isFinalPrize = ($currentPunches >= $promotion->punches_required);
            
            // Only require price for final prize redemption
            if ($isFinalPrize && $originalAmount <= 0) {
                $msg = 'Purchase amount is required when redeeming the final prize. Please enter the value of the free item.';
                if ($isInertiaRequest) {
                    return back()->withErrors(['original_amount' => $msg])->withInput();
                }
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 400);
            }
        } else {
            // For non-punch-card promotions
            // If promotion needs calculator, require original_amount
            if ($promotion->needsCalculator()) {
                if ($originalAmount <= 0) {
                    $msg = 'Purchase amount is required for this promotion type. Please enter the customer\'s purchase amount.';
                    if ($isInertiaRequest) {
                        return back()->withErrors(['original_amount' => $msg])->withInput();
                    }
                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                    ], 400);
                }
            } else {
                // Promotion doesn't need calculator (has original_price set)
                // If original_amount is 0 or not provided, use original_price from promotion
                if ($originalAmount <= 0 && $promotion->original_price && $promotion->original_price > 0) {
                    $originalAmount = (float) $promotion->original_price;
                } elseif ($originalAmount <= 0) {
                    // No original_amount and no original_price - this shouldn't happen but handle gracefully
                    $msg = 'Purchase amount is required. Please enter the customer\'s purchase amount.';
                    if ($isInertiaRequest) {
                        return back()->withErrors(['original_amount' => $msg])->withInput();
                    }
                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                    ], 400);
                }
            }
        }
        
        $discountResult = $promotion->calculateDiscount($originalAmount, $quantity, [
            'item_prices' => $request->input('item_prices'),
            'is_final_prize' => $isFinalPrize,
        ]);

        // Log for debugging if discount is 0 but shouldn't be
        if ($discountResult['discount'] <= 0 && $originalAmount > 0) {
            \Log::warning('Discount calculated as 0 for non-zero amount', [
                'promotion_id' => $promotion->id,
                'discount_type' => $promotion->discount_type,
                'discount_value' => $promotion->discount_value,
                'original_amount' => $originalAmount,
                'calculated_discount' => $discountResult['discount'],
            ]);
        }
        
        // Ensure discount was calculated (should be > 0 for valid redemptions)
        // Skip this check for punch card regular punches (not final prize)
        if (!$isPunchCard || $isFinalPrize) {
            // If discount is 0 but we have an original amount, something went wrong
            if ($discountResult['discount'] <= 0 && $originalAmount > 0) {
                \Log::error('Discount calculation returned 0 for non-zero amount', [
                    'promotion_id' => $promotion->id,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                    'original_amount' => $originalAmount,
                    'calculated_discount' => $discountResult['discount'],
                ]);
                
                // For percentage discounts, ensure we're calculating correctly
                if ($promotion->discount_type === Promotion::TYPE_PERCENTAGE && $promotion->discount_value > 0) {
                    // Recalculate manually to ensure it works
                    if ($promotion->discount_value <= 1) {
                        $discountResult['discount'] = round($originalAmount * $promotion->discount_value, 2);
                    } else {
                        $discountResult['discount'] = round($originalAmount * ($promotion->discount_value / 100), 2);
                    }
                    $discountResult['final_amount'] = round($originalAmount - $discountResult['discount'], 2);
                }
            }
            
            // If still 0 and needs calculator, check if original amount was also 0 (invalid input)
            // But allow $0 discount if it was a valid calculation (e.g. original price matched promo price)
            if ($discountResult['discount'] <= 0 && $promotion->needsCalculator() && $originalAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid purchase amount. Please check the amount and try again.',
                ], 400);
            }
        }

        $txResult = DatabaseHelper::transactionWithRetry(function () use ($request, $promotion, $qrCode, $businessId, $employeeId, $user, $customerUser, $token, $customerIdentifier, $discountResult, $originalAmount) {
            // Lock promotion row to prevent daily limit race condition
            $lockedPromotion = Promotion::where('id', $promotion->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedPromotion) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Promotion not found',
                ];
            }

            // Re-check total redemption limit with lock held to prevent race conditions
            $isPunchCard = $promotion->discount_type === Promotion::TYPE_PUNCH_CARD;
            $isMerchReferral = ($qrCode->type ?? null) === 'merch_referral';
            $respectLimitsForPunchCards = $lockedPromotion->rules['respect_punch_limits'] ?? false;

            if ((!$isPunchCard || $respectLimitsForPunchCards) && !$isMerchReferral) {
                $maxTotal = (int) ($lockedPromotion->rules['max_redemptions_total'] ?? 0);
                if ($maxTotal > 0 && $lockedPromotion->total_redemptions >= $maxTotal) {
                    return [
                        'success' => false,
                        'status' => 400,
                        'message' => 'Maximum redemptions reached for this offer',
                    ];
                }
            }

            // Re-check daily limit with lock held to prevent race conditions
            // Skip daily limit check for punch cards - they allow multiple punches per day
            if (!$isPunchCard && !$isMerchReferral && isset($lockedPromotion->rules['max_per_day'])) {
                $todayCount = Redemption::where('promotion_id', $lockedPromotion->id)
                    ->whereDate('redeemed_at', today())
                    ->count();

                if ($todayCount >= $lockedPromotion->rules['max_per_day']) {
                    return [
                        'success' => false,
                        'status' => 400,
                        'message' => 'Daily limit reached',
                    ];
                }
            }

            $punchCard = null;
            if ($isPunchCard) {
                // Fetch (and lock) existing punch card for this user/identifier
                $punchCard = PunchCard::where('promotion_id', $lockedPromotion->id)
                    ->where(function ($q) use ($customerUser, $customerIdentifier) {
                        $q->where('user_id', $customerUser->id);
                        if ($customerIdentifier) {
                            $q->orWhere('customer_identifier', $customerIdentifier);
                        }
                    })
                    ->lockForUpdate()
                    ->first();

                // Enforce daily punch cap (counts only punch-adding redemptions)
                // Skip limit if card is already full (this redemption is the free prize).
                $cardIsFull = $punchCard && $lockedPromotion->punches_required
                    && ($punchCard->punches >= $lockedPromotion->punches_required);
                if ($lockedPromotion->punch_card_max_punches_per_day && !$cardIsFull) {
                    $todayPunches = Redemption::where('promotion_id', $lockedPromotion->id)
                        ->where('customer_user_id', $customerUser->id)
                        ->whereDate('redeemed_at', today())
                        ->where('punches_added', '>', 0)
                        ->count();

                    if ($todayPunches >= $lockedPromotion->punch_card_max_punches_per_day) {
                        return [
                            'success' => false,
                            'status' => 400,
                            'message' => 'Daily punch limit reached. Come back tomorrow!',
                        ];
                    }
                }

                $needsNewCard = !$punchCard;

                // Enforce total card availability when starting a new card
                if ($needsNewCard && $lockedPromotion->punch_card_total_cards_limit) {
                    $totalCardsIssued = PunchCard::where('promotion_id', $lockedPromotion->id)->count();
                    if ($totalCardsIssued >= $lockedPromotion->punch_card_total_cards_limit) {
                        return [
                            'success' => false,
                            'status' => 400,
                            'message' => 'This punch card is no longer available.',
                        ];
                    }
                }

                // Enforce per-customer card limit (completed cards + active card)
                if ($lockedPromotion->punch_card_max_cards_per_user) {
                    $userCardsUsed = 0;
                    if ($punchCard) {
                        $userCardsUsed = ($punchCard->completed_cards ?? 0);
                        if (($punchCard->punches ?? 0) > 0) {
                            $userCardsUsed += 1;
                        }
                    }

                    // If starting a new card (no record yet), count it
                    if ($needsNewCard) {
                        $userCardsUsed += 1;
                    } else {
                        // If card is empty (fresh cycle), starting punches would consume another card allotment
                        if (($punchCard->punches ?? 0) === 0 && $userCardsUsed >= $lockedPromotion->punch_card_max_cards_per_user) {
                            return [
                                'success' => false,
                                'status' => 400,
                                'message' => 'You have reached the punch card limit for this offer.',
                            ];
                        }
                    }

                    if ($userCardsUsed > $lockedPromotion->punch_card_max_cards_per_user) {
                        return [
                            'success' => false,
                            'status' => 400,
                            'message' => 'You have reached the punch card limit for this offer.',
                        ];
                    }
                }

                // Create card now if needed (after limits)
                if (!$punchCard) {
                    $punchCard = $lockedPromotion->punchCards()->create([
                        'user_id' => $customerUser->id,
                        'customer_identifier' => $customerIdentifier,
                        'punches' => 0,
                        'completed_cards' => 0,
                    ]);
                }

                // If the card was already completed/reset, force a new scan/card before next use
                if ($punchCard->punches === 0 && $punchCard->completed_cards > 0 && !$needsNewCard) {
                    return [
                        'success' => false,
                        'status' => 400,
                        'message' => 'This punch card is completed. Please scan again to start a new card.',
                    ];
                }

                // If card is already full in the locked view, this redemption is the prize
                $isFinalPrizeLocked = ($punchCard->punches >= $lockedPromotion->punches_required);
                if ($isFinalPrizeLocked && $originalAmount <= 0) {
                    return [
                        'success' => false,
                        'status' => 400,
                        'message' => 'Purchase amount is required when redeeming the final prize. Please enter the value of the free item.',
                    ];
                }

                // If the card was already reset (punches == 0) after a prize, force a new scan/card
                if ($punchCard->punches === 0 && $punchCard->completed_cards > 0 && !$needsNewCard) {
                    return [
                        'success' => false,
                        'status' => 400,
                        'message' => 'This punch card is completed. Please scan again to start a new card.',
                    ];
                }
            }

            // If this redemption is the final prize for a punch card, ensure discount/final amounts are correct.
            // Sometimes upstream calculations or prefilled amounts can leave final_amount non-zero; enforce semantics:
            // - original_amount = value of free item (reward_value fallback)
            // - discount = original_amount
            // - final_amount = 0
            if ($isPunchCard) {
                $isFinalPrizeLocked = ($punchCard->punches >= $lockedPromotion->punches_required);
                if ($isFinalPrizeLocked) {
                    $effectiveValue = $discountResult['original_amount'] ?? $originalAmount;
                    if (($effectiveValue <= 0 || $effectiveValue === null) && $lockedPromotion->reward_value !== null) {
                        $effectiveValue = (float) $lockedPromotion->reward_value;
                    }
                    $effectiveValue = round((float) $effectiveValue, 2);
                    $discountResult['original_amount'] = $effectiveValue;
                    $discountResult['discount'] = $effectiveValue;
                    $discountResult['final_amount'] = 0.00;
                }
            }

            // For non-punch-card promos, mark token as redeemed if it's the first use
            if (!$isPunchCard && !$token->redeemed_at) {
                // First-time redemption: mark token as redeemed atomically
                $updated = UserPromoToken::query()
                    ->where('id', $token->id)
                    ->whereNull('redeemed_at')
                    ->update([
                        'redeemed_at' => now(),
                    ]);

                if ($updated !== 1) {
                    return [
                        'success' => false,
                        'status' => 400,
                        'message' => 'This customer promo has already been redeemed.',
                    ];
                }
            }
            // If token->redeemed_at is already set but canReuseToken is true,
            // we allow the redemption to proceed (token stays marked as redeemed but can be used again)

            // Try to find existing scan for this user and QR code to link it.
            // For merch_referral promos, prefer scans that carry a merch_tag_id
            // so the merch owner gets proper attribution and rewards.
            $isMerchReferral = ($qrCode->type ?? null) === 'merch_referral';

            $scan = Scan::where('qr_code_id', $qrCode->id)
                ->where('user_id', $customerUser->id)
                ->whereDoesntHave('redemption')
                ->when($isMerchReferral, fn ($q) => $q->orderByRaw('merch_tag_id IS NOT NULL DESC'))
                ->orderByDesc('scanned_at')
                ->first();

            // If still no scan found, look for ANY latest scan within last 24 hours
            if (!$scan) {
                $scan = Scan::where('qr_code_id', $qrCode->id)
                    ->where('user_id', $customerUser->id)
                    ->where('scanned_at', '>=', now()->subHours(24))
                    ->when($isMerchReferral, fn ($q) => $q->orderByRaw('merch_tag_id IS NOT NULL DESC'))
                    ->orderByDesc('scanned_at')
                    ->first();
            }

            // Create redemption record
            $redemption = Redemption::create([
                'promotion_id' => $promotion->id,
                'qr_code_id' => $qrCode->id,
                'scan_id' => $scan?->id, // Link to scan only if it exists
                'business_id' => $businessId,
                'employee_id' => $employeeId, // null if redeemed by business owner
                'redeemed_by_user_id' => $user->id, // Track who actually did it
                'customer_user_id' => $customerUser->id,
                // Punch card stamp redemptions are repeatable, so do NOT bind a unique token ID here.
                'user_promo_token_id' => $isPunchCard ? null : $token->id,
                'customer_identifier' => $customerIdentifier,
                'customer_name' => $request->input('customer_name'),
                'customer_email' => $request->input('customer_email'),
                'customer_phone' => null,
                'original_amount' => $discountResult['original_amount'] ?? $originalAmount,
                'discount_amount' => $discountResult['discount'],
                'final_amount' => $discountResult['final_amount'],
                'notes' => $request->input('notes'),
                'redeemed_at' => now(),
            ]);

            // Update promotion stats
            $promotion->increment('total_redemptions');
            $promotion->increment('total_savings', $discountResult['discount']);

            // Update user's total savings when they redeem a promotion
            if ($customerUser && $discountResult['discount'] > 0) {
                $customerUser->increment('total_savings', $discountResult['discount']);
            }

            if ($isPunchCard) {
                $this->handlePunchCard($lockedPromotion, $customerUser, $customerIdentifier, $redemption, $punchCard);
                // Award XP only when punch card is completed (final redemption)
                if ($redemption->card_completed && $customerUser) {
                    app(\App\Services\XpService::class)->awardForRedemption($customerUser, $redemption, $promotion);
                    if (in_array($customerUser->role, ['user', 'customer'], true)) {
                        $customerUser->notify(new PortalPunchCardCompleted($promotion));
                    }
                }
            }

            $punchCardState = null;
            if ($isPunchCard && $punchCard) {
                $punchCard->refresh();
                $punchCardState = [
                    'punches' => $punchCard->punches,
                    'completed_cards' => $punchCard->completed_cards,
                    'punches_required' => $promotion->punches_required,
                ];
            } else {
                // Award XP for non-punch-card redemptions (scaled by savings)
                if ($customerUser) {
                    app(\App\Services\XpService::class)->awardForRedemption($customerUser, $redemption, $promotion);
                }
                
                // Link token -> redemption (only if not already linked)
                if ($token->id && !$token->redemption_id) {
                    UserPromoToken::query()
                        ->where('id', $token->id)
                        ->update([
                            'redemption_id' => $redemption->id,
                        ]);
                }

                // Mark any associated GameReward as redeemed so it no longer
                // appears as an active/claimed reward in the customer portal.
                if ($customerUser && $promotion->id) {
                    GameReward::where('user_id', $customerUser->id)
                        ->where('promotion_id', $promotion->id)
                        ->whereIn('status', [GameReward::STATUS_CLAIMED, GameReward::STATUS_AVAILABLE])
                        ->update([
                            'status' => GameReward::STATUS_REDEEMED,
                            'redeemed_at' => now(),
                        ]);
                }

                // Only remove from portal Saved if this is a single-use promotion
                // (i.e., max_redemptions_per_user is 1 or not set)
                $rules = $promotion->rules ?? [];
                $isSingleUse = !isset($rules['max_redemptions_per_user']) || 
                               $rules['max_redemptions_per_user'] === 1;
                
                if ($isSingleUse) {
                    SavedQRCode::where('user_id', $customerUser->id)
                        ->where('qr_code_id', $qrCode->id)
                        ->delete();
                }

                // Note: do NOT remove punch-card promos from SavedQRCode here.
                // We only remove non-punch-card single-use promos above. Punch-card
                // appearance in 'My Promotions' is controlled on the Portal scans page.
            }

            // Ambassador XP: award merch owner when a merch-referred redemption happens.
            if ($scan && $scan->merch_tag_id) {
                $tag = MerchTag::with('owner')
                    ->where('id', $scan->merch_tag_id)
                    ->where('is_active', true)
                    ->first();

                if ($tag && $tag->owner && (!$customerUser || $tag->owner->id !== $customerUser->id)) {
                    app(\App\Services\XpService::class)->awardForMerchRedemption($tag->owner, $redemption);
                }
            }

            // Ambassador reward: issue repeatable merch referral rewards on unique thresholds.
            app(MerchReferralRewardService::class)->evaluateForRedemption($redemption, $scan);

            return [
                'success' => true,
                'redemption' => $redemption,
                'punch_card_state' => $punchCardState,
            ];
        });

        if (!($txResult['success'] ?? false)) {
            $msg = $txResult['message'] ?? 'Redemption failed.';
            if ($isInertiaRequest) {
                return back()->withErrors(['message' => $msg]);
            }
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], (int) ($txResult['status'] ?? 400));
            }
            return back()->withErrors(['message' => $msg]);
        }

        // Refresh user's total_savings to ensure it's up to date
        $customerUser->refresh();

        // Update CRM customer cache (owned customers only)
        try {
            app(BusinessCustomerService::class)->recordRedemption($txResult['redemption']);
        } catch (\Throwable $e) {
            // Non-blocking: redemption must succeed even if CRM caching fails
            \Log::warning('Failed to update business customer cache after redemption', [
                'redemption_id' => $txResult['redemption']->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
        
        $responseData = [
            'id' => $txResult['redemption']->id,
            'promotion_name' => $promotion->name,
            'original_amount' => $txResult['redemption']->original_amount,
            'discount_amount' => $discountResult['discount'],
            'final_amount' => $discountResult['final_amount'],
            'details' => $discountResult['details'],
            'user_total_savings' => $customerUser->total_savings,
            'card_completed' => (bool) $txResult['redemption']->card_completed,
            'punches_added' => (int) ($txResult['redemption']->punches_added ?? 0),
            'punch_card_state' => $txResult['punch_card_state'] ?? null,
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Redemption successful!',
                'redemption' => $responseData,
            ]);
        }

        return back()->with('success', 'Redemption successful!')->with('redemption', $responseData);
    }

    protected function handlePunchCard(Promotion $promotion, User $customer, string $customerIdentifier, Redemption $redemption, PunchCard $punchCard)
    {
        // If card already full: redeem the prize, reset punches, mark completed (no new punch)
        if ($punchCard->punches >= $promotion->punches_required) {
            $punchCard->update([
                'punches' => 0,
                'completed_cards' => $punchCard->completed_cards + 1,
                'last_punch_at' => now(),
            ]);
            $redemption->update([
                'punches_added' => 0,
                'card_completed' => true,
            ]);
            return;
        }

        // Otherwise add a punch
        $punchCard->increment('punches');
        $punchCard->update(['last_punch_at' => now()]);
        $redemption->update(['punches_added' => 1]);
    }

    public function activity(Request $request)
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return Inertia::render('Employee/NoEmployer');
        }

        $redemptions = Redemption::where('employee_id', $employee->id)
            ->with(['promotion:id,name,discount_type', 'qrCode:id,name,code'])
            ->orderByDesc('redeemed_at')
            ->paginate(20);

        $stats = [
            'total_redemptions' => Redemption::where('employee_id', $employee->id)->count(),
            'total_savings' => Redemption::where('employee_id', $employee->id)->sum('discount_amount'),
            'this_week' => Redemption::where('employee_id', $employee->id)
                ->where('redeemed_at', '>=', now()->startOfWeek())
                ->count(),
        ];

        return Inertia::render('Employee/Activity', [
            'redemptions' => $redemptions,
            'stats' => $stats,
        ]);
    }

    /**
     * Quick redeem via reward code (customer shows this to staff).
     */
    public function quickRedeemReward(Request $request, string $rewardCode)
    {
        $user = $request->user();

        // Must be logged in
        if (!$user) {
            return redirect()->route('login', ['redirect' => "/redeem/reward/{$rewardCode}"]);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $ownedBusiness = $user->business;
        $isBusinessOwner = ($user->role === 'business' && $ownedBusiness);

        if (!$employee && !$isBusinessOwner) {
            return Inertia::render('Employee/NoEmployer', [
                'message' => 'You must be registered as an employee to redeem rewards.',
            ]);
        }

        if ($employee && !$employee->can_redeem) {
            return Inertia::render('Employee/NoEmployer', [
                'message' => 'Your account does not have permission to redeem rewards.',
            ]);
        }

        $reward = GameReward::where('reward_code', $rewardCode)
            ->with(['business:id,name,logo_path,primary_color', 'promotion', 'user'])
            ->first();

        if (!$reward) {
            return Inertia::render('Public/ScanError', [
                'message' => 'Reward code not found',
            ]);
        }

        $business = $reward->business;

        // Verify this reward belongs to the user's business (employee or owner)
        $userBusinessId = $employee ? $employee->business_id : $ownedBusiness?->id;
        if ($reward->business_id !== $userBusinessId) {
            $yourBusinessName = $employee
                ? $employee->business->name
                : ($ownedBusiness?->name ?? 'Your Business');

            return Inertia::render('Employee/WrongBusiness', [
                'message' => 'This reward belongs to a different business.',
                'qrBusiness' => $business->name,
                'yourBusiness' => $yourBusinessName,
            ]);
        }

        // Check if reward can be redeemed
        $canRedeem = $reward->status === GameReward::STATUS_CLAIMED || $reward->status === GameReward::STATUS_AVAILABLE;
        $redeemMessage = null;

        if (!$canRedeem) {
            if ($reward->status === GameReward::STATUS_REDEEMED) {
                $redeemMessage = 'This reward has already been redeemed.';
            } else {
                $redeemMessage = 'This reward is no longer available.';
            }
        }

        return Inertia::render('Employee/QuickRedeemReward', [
            'reward' => [
                'id' => $reward->id,
                'reward_code' => $reward->reward_code,
                'description' => $reward->description,
                'reward_type' => $reward->reward_type,
                'discount_value' => $reward->discount_value,
                'display_value' => $reward->getDisplayValue(),
                'status' => $reward->status,
            ],
            'business' => [
                'name' => $business->name,
                'logo_url' => $business->logo_url,
            ],
            'customer' => $reward->user ? [
                'id' => $reward->user->id,
                'name' => $reward->user->name,
                'email' => $reward->user->email,
            ] : null,
            'employee' => [
                'id' => $employee?->id,
                'name' => $user->name,
            ],
            'canRedeem' => $canRedeem,
            'redeemMessage' => $redeemMessage,
        ]);
    }

    /**
     * Process reward redemption
     */
    public function redeemReward(Request $request, string $rewardCode)
    {
        $user = $request->user();
        $businessId = null;
        $employeeId = null;

        // Check if user is a business owner
        $ownedBusiness = $user->business;
        if ($user->role === 'business' && $ownedBusiness) {
            $businessId = $ownedBusiness->id;
        } else {
            // Check if user is an employee
            $employee = Employee::where('user_id', $user->id)->first();

            if (!$employee || !$employee->can_redeem) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to redeem rewards',
                ], 403);
            }

            $businessId = $employee->business_id;
            $employeeId = $employee->id;
        }

        $reward = GameReward::where('reward_code', $rewardCode)
            ->where('business_id', $businessId)
            ->first();

        if (!$reward) {
            return response()->json([
                'success' => false,
                'message' => 'Reward not found or does not belong to your business',
            ], 404);
        }

        if ($reward->status !== GameReward::STATUS_CLAIMED && $reward->status !== GameReward::STATUS_AVAILABLE) {
            return response()->json([
                'success' => false,
                'message' => 'This reward cannot be redeemed',
            ], 400);
        }

        // Check if the reward has expired before attempting redemption
        $isExpired = ($reward->expires_at && $reward->expires_at->isPast())
            || ($reward->valid_until && $reward->valid_until->isPast());

        if ($isExpired) {
            $reward->update(['status' => GameReward::STATUS_EXPIRED]);
            return response()->json([
                'success' => false,
                'message' => 'This reward has expired and can no longer be redeemed',
            ], 400);
        }

        // Redeem the reward (pass user ID for tracking who redeemed it)
        $reward->redeem($employeeId, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Reward redeemed successfully',
            'reward' => [
                'id' => $reward->id,
                'status' => $reward->status,
                'display_value' => $reward->getDisplayValue(),
            ],
        ]);
    }
}

