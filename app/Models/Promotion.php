<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // When a promotion is soft-deleted, deactivate any cross-promotions that reference it.
        // This prevents users from seeing dead cross-promo offers.
        static::deleting(function (self $promotion) {
            if ($promotion->isForceDeleting()) {
                return; // Hard deletes are handled by FK cascades
            }

            CrossPromotion::where(function ($q) use ($promotion) {
                $q->where('promotion_1_id', $promotion->id)
                  ->orWhere('promotion_2_id', $promotion->id);
            })
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'status' => CrossPromotion::STATUS_DECLINED,
            ]);
        });
    }

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'terms',
        'discount_type',
        'discount_value',
        'buy_quantity',
        'get_quantity',
        'for_price',
        'punches_required',
        'reward_value',
        'punch_icon',
        'tiers',
        'original_price',
        'minimum_purchase',
        'maximum_discount',
        'rules',
        'starts_at',
        'ends_at',
        'total_views',
        'total_redemptions',
        'total_savings',
        'is_active',
        'is_stackable',
        'is_featured',
        // Punch-card caps
        'punch_card_max_cards_per_user',
        'punch_card_total_cards_limit',
        'punch_card_max_punches_per_day',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'buy_quantity' => 'integer',
        'get_quantity' => 'integer',
        'for_price' => 'decimal:2',
        'punches_required' => 'integer',
        'reward_value' => 'decimal:2',
        'tiers' => 'array',
        'original_price' => 'decimal:2',
        'minimum_purchase' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'rules' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'total_views' => 'integer',
        'total_redemptions' => 'integer',
        'total_savings' => 'decimal:2',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'is_featured' => 'boolean',
        'punch_card_max_cards_per_user' => 'integer',
        'punch_card_total_cards_limit' => 'integer',
        'punch_card_max_punches_per_day' => 'integer',
    ];

    // Discount type constants
    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED_AMOUNT = 'fixed_amount';
    const TYPE_BOGO = 'bogo';
    const TYPE_BUY_X_GET_Y = 'buy_x_get_y';
    const TYPE_BUY_X_FOR_Y = 'buy_x_for_y';
    const TYPE_PUNCH_CARD = 'punch_card';
    const TYPE_TIERED = 'tiered';
    const TYPE_BUNDLE = 'bundle';
    const TYPE_HAPPY_HOUR = 'happy_hour';
    const TYPE_FIRST_TIME = 'first_time';
    const TYPE_LOYALTY = 'loyalty_milestone';

    public static function discountTypes(): array
    {
        return [
            self::TYPE_PERCENTAGE => 'Percentage Off',
            self::TYPE_FIXED_AMOUNT => 'Fixed Amount Off',
            self::TYPE_BOGO => 'Buy One Get One',
            self::TYPE_BUY_X_GET_Y => 'Buy X Get Y Free',
            self::TYPE_BUY_X_FOR_Y => 'Buy X for $Y',
            self::TYPE_PUNCH_CARD => 'Punch Card',
            self::TYPE_TIERED => 'Tiered Discount',
            self::TYPE_BUNDLE => 'Bundle Deal',
            self::TYPE_HAPPY_HOUR => 'Happy Hour',
            self::TYPE_FIRST_TIME => 'First Time Customer',
            self::TYPE_LOYALTY => 'Loyalty Milestone',
        ];
    }

    /**
     * Available icons for punch card boxes
     */
    public static function punchIconOptions(): array
    {
        return [
            // Food & Drink
            '☕' => 'Coffee',
            '🍕' => 'Pizza',
            '🍔' => 'Burger',
            '🌮' => 'Taco',
            '🍩' => 'Donut',
            '🍦' => 'Ice Cream',
            '🍺' => 'Beer',
            '🍷' => 'Wine',
            '🍸' => 'Cocktail',
            '🧁' => 'Cupcake',
            '🥤' => 'Drink',
            '🍜' => 'Noodles',
            '🥗' => 'Salad',
            '🥪' => 'Sandwich',
            
            // Services
            '💇' => 'Haircut',
            '💅' => 'Nail',
            '💆' => 'Spa/Massage',
            '🧴' => 'Beauty',
            '🏋️' => 'Gym/Fitness',
            '🚗' => 'Car Wash',
            '🧹' => 'Cleaning',
            '👕' => 'Laundry',
            
            // Shopping & Retail
            '🛍️' => 'Shopping Bag',
            '👟' => 'Shoes',
            '👗' => 'Clothing',
            '💎' => 'Jewelry',
            '📱' => 'Tech',
            '🎁' => 'Gift',
            
            // Generic
            '⭐' => 'Star',
            '❤️' => 'Heart',
            '✓' => 'Checkmark',
            '🎯' => 'Target',
            '🏆' => 'Trophy',
            '🎉' => 'Celebration',
            '👍' => 'Thumbs Up',
            '💰' => 'Money',
        ];
    }

    /**
     * Get the punch icon or default
     */
    public function getPunchIcon(): string
    {
        return $this->punch_icon ?? '⭐';
    }

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QRCode::class, 'promotion_id', 'id');
    }

    public function redemptions()
    {
        return $this->hasMany(Redemption::class, 'promotion_id', 'id');
    }

    public function punchCards()
    {
        return $this->hasMany(PunchCard::class, 'promotion_id', 'id');
    }

    // Helpers
    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function canRedeem(?string $customerIdentifier = null, ?int $customerUserId = null, array $options = []): array
    {
        $result = ['allowed' => true, 'reason' => null];

        if (!$this->is_active) {
            return ['allowed' => false, 'reason' => 'This promotion is no longer active'];
        }

        // Check if the business is still active
        if ($this->relationLoaded('business') ? !$this->business?->is_active : !Business::where('id', $this->business_id)->value('is_active')) {
            return ['allowed' => false, 'reason' => 'This business is currently unavailable'];
        }

        $ignoreTimeLimits = (bool) ($options['ignore_time_limits'] ?? false);
        $ignoreTotalLimit = (bool) ($options['ignore_total_limit'] ?? false);
        $ignoreDailyLimit = (bool) ($options['ignore_daily_limit'] ?? false);
        $ignoreTimeWindow = (bool) ($options['ignore_time_window'] ?? false);
        $forceMaxPerUser = (int) ($options['force_max_redemptions_per_user'] ?? 0);

        if (!$ignoreTimeLimits) {
            if ($this->starts_at && $this->starts_at->isFuture()) {
                return ['allowed' => false, 'reason' => 'This promotion has not started yet (starts ' . $this->starts_at->format('M d, Y') . ')'];
            }

            if ($this->ends_at && $this->ends_at->isPast()) {
                return ['allowed' => false, 'reason' => 'This promotion has expired'];
            }
        }

        $rules = $this->rules ?? [];
        $isPunchCard = ($this->discount_type === self::TYPE_PUNCH_CARD);
        $isFirstTime = ($this->discount_type === self::TYPE_FIRST_TIME);

        if ($isFirstTime && $customerUserId) {
            $currentQrId = $options['current_qr_code_id'] ?? null;
            $graceMinutes = (int) ($options['first_time_scan_grace_minutes'] ?? 2);

            $priorScanQuery = Scan::where('user_id', $customerUserId)
                ->where('business_id', $this->business_id)
                ->whereIn('scan_type', [Scan::TYPE_PROMOTION, Scan::TYPE_PUNCH_CARD])
                ->whereHas('qrCode', function ($q) {
                    $q->whereNotNull('promotion_id');
                });

            if ($currentQrId) {
                $priorScanQuery->where(function ($q) use ($currentQrId, $graceMinutes) {
                    $q->where('qr_code_id', '!=', $currentQrId)
                        ->orWhere('scanned_at', '<', now()->subMinutes($graceMinutes));
                });
            }

            if ($priorScanQuery->exists()) {
                return ['allowed' => false, 'reason' => 'You are not a new customer for this business.'];
            }
        }

        // Punch-card specific limits (always enforced)
        if ($isPunchCard) {
            $customerContextId = $customerUserId;
            $userCard = null;

            // Total cards available (caps how many customers can hold a card)
            if ($this->punch_card_total_cards_limit) {
                $totalCardsIssued = PunchCard::where('promotion_id', $this->id)->count();
                if ($totalCardsIssued >= $this->punch_card_total_cards_limit) {
                    return ['allowed' => false, 'reason' => 'This punch card is no longer available'];
                }
            }

            // Cards per customer (counts completed cards + an active in-progress card)
            if ($this->punch_card_max_cards_per_user && $customerContextId) {
                if (!$userCard) {
                    $userCard = PunchCard::where('promotion_id', $this->id)
                        ->where('user_id', $customerContextId)
                        ->first();
                }

                $hasActiveCard = false;
                $isInProgress = false;
                $isFullAwaitingPrize = false;
                $userCardsUsed = 0;

                if ($userCard) {
                    $hasActiveCard = ($userCard->punches ?? 0) > 0;
                    $isInProgress = $hasActiveCard && ($userCard->punches < ($this->punches_required ?? PHP_INT_MAX));
                    $isFullAwaitingPrize = $hasActiveCard && ($userCard->punches >= ($this->punches_required ?? PHP_INT_MAX));
                    
                    // Logic fix: completed_cards is count of PAST cards. 
                    // If we have an active card (in progress OR full waiting), that counts as +1 towards CURRENT holding limit.
                    // But typically 'max_cards_per_user' means 'lifetime limit' OR 'concurrent limit'?
                    // The field name 'punch_card_max_cards_per_user' usually implies LIFETIME limit in this context.
                    // Let's assume Lifetime limit based on variable name.
                    // So total used = completed + (1 if active).
                    $userCardsUsed = ($userCard->completed_cards ?? 0) + ($hasActiveCard ? 1 : 0);
                }

                // Allow continuing an active card (in-progress or full awaiting prize) even if max is 1
                // BUT if they are trying to start a NEW card (which canRedeem is usually checking), we block if limit reached.
                // canRedeem is often called before "add punch" or "issue card".
                
                // If the user HAS a card currently, are they trying to use THAT one?
                // The caller doesn't specify "which card".
                // If they have a card in progress, canRedeem returns true (to allow punching IT).
                // If they have a COMPLETED card (waiting for prize), canRedeem returns true (to allow redeeming prize).
                // If they have a COMPLETED card (prize redeemed -> punches=0), that card is "done" (unless we recycle the row).
                // Our logic recycles the row: `completed_cards + 1, punches = 0`.
                
                // So:
                // If punches > 0: Card is Active.
                // If punches == 0: Card is Inactive (waiting to start next one).
                
                // If Card Active: count = completed + 1.
                // If Card Inactive: count = completed.
                
                // If I have completed 1 card (limit 1), and punches=0:
                // userCardsUsed = 1. Limit = 1.
                // Result: 1 >= 1 -> BLOCK. Correct.
                
                // The test failure shows: punches=5 (active/full), completed=1.
                // userCardsUsed = 1 + 1 = 2.
                // Limit = 1.
                // 2 >= 1 -> Should BLOCK.
                
                // So why did it ALLOW?
                // "Allow continuing an active card..."
                // if (!($isInProgress || $isFullAwaitingPrize)) { ... check limit ... }
                // Here, isFullAwaitingPrize IS true (punches 5 >= 5).
                // So we skipped the limit check!
                
                // If I am at 5/5, I should be allowed to REDEEM the prize (canRedeem=true).
                // But I should NOT be allowed to start a *new* card (if that was the intent).
                // canRedeem returns "allowed" generally for the promotion.
                
                // The test is: "User tries to start a SECOND card".
                // But the user *already has* a card at 5/5.
                // canRedeem is correct to say "Yes" because they need to redeem that 5/5 card!
                
                // To test "Prevent Hoarding", we need the user to have FINISHED the card (redeemed prize) 
                // AND then try to start a new one.
                // If the row is recycled, punches would be 0.
                
                // Let's correct the test logic to match the "Lifecycle".
                // 1. User completes card 1. punches=0, completed=1.
                // 2. User tries to start card 2.
                // 3. System checks limit (1). 1 >= 1. Block.
                
                // The test set punches=5. That means the card is ACTIVE (waiting for prize).
                // Of course they can access the promo! To get their prize!
                
                if (!($isInProgress || $isFullAwaitingPrize)) {
                    if ($userCardsUsed >= $this->punch_card_max_cards_per_user) {
                        return ['allowed' => false, 'reason' => 'You have reached the punch card limit for this offer'];
                    }
                }
            }

            // Punches per day (per user; counts only stamp redemptions)
            if ($this->punch_card_max_punches_per_day && $customerContextId) {
                if (!$userCard) {
                    $userCard = PunchCard::where('promotion_id', $this->id)
                        ->where('user_id', $customerContextId)
                        ->first();
                }
                $cardIsFull = $userCard && $this->punches_required && ($userCard->punches >= $this->punches_required);
                if (!$cardIsFull) {
                    $todayPunches = Redemption::where('promotion_id', $this->id)
                        ->where('customer_user_id', $customerContextId)
                        ->whereDate('redeemed_at', today())
                        ->where('punches_added', '>', 0)
                        ->count();

                    if ($todayPunches >= $this->punch_card_max_punches_per_day) {
                        return ['allowed' => false, 'reason' => 'Daily punch limit reached. Come back tomorrow!'];
                    }
                }
            }
        }

        // 1. Check Limits (Respect limits for punch cards IF configured, otherwise skip for legacy reasons)
        $respectLimitsForPunchCards = $rules['respect_punch_limits'] ?? false;
        
        if (!$isPunchCard || $respectLimitsForPunchCards) {
            // Check total redemption limit
            if (!$ignoreTotalLimit && isset($rules['max_redemptions_total']) && $this->total_redemptions >= $rules['max_redemptions_total']) {
                return ['allowed' => false, 'reason' => 'Maximum redemptions reached for this offer'];
            }

            // Check per-user limit
            $maxPerUser = $forceMaxPerUser > 0 ? $forceMaxPerUser : (int) ($rules['max_redemptions_per_user'] ?? 0);
            if ($maxPerUser > 0) {
                $query = $this->redemptions();

                if ($customerUserId) {
                    $query->where('customer_user_id', $customerUserId);
                } elseif ($customerIdentifier) {
                    // Find the user ID for this identifier to check total user redemptions (across all scans)
                    $token = \App\Models\UserPromoToken::where('code', $customerIdentifier)->first();
                    if ($token && $token->user_id) {
                        $query->where('customer_user_id', $token->user_id);
                    } else {
                        $query->where('customer_identifier', $customerIdentifier);
                    }
                } else {
                    // No identifier available -> can't reliably enforce per-user limit here.
                    $query = null;
                }

                if ($query) {
                    $userRedemptions = $query->count();
                    if ($userRedemptions >= $maxPerUser) {
                        return ['allowed' => false, 'reason' => 'You have reached your redemption limit for this offer', 'limit_reached' => true];
                    }
                }
            }

            // Check daily limit
            if (!$ignoreDailyLimit && isset($rules['max_per_day']) && (int)$rules['max_per_day'] > 0) {
                $todayRedemptions = $this->redemptions()
                    ->whereDate('redeemed_at', today())
                    ->count();

                if ($todayRedemptions >= $rules['max_per_day']) {
                    return ['allowed' => false, 'reason' => 'Daily limit reached for this offer. Try again tomorrow!'];
                }
            }
        }

        // 2. Check Valid Days & Hours (handle overnight windows)
        // App timezone is America/Toronto so now() is already local time
        $now = now();
        $today = strtolower($now->format('l'));
        $yesterday = strtolower($now->copy()->subDay()->format('l'));

        $hasDayRestriction = isset($rules['valid_days']) && is_array($rules['valid_days']) && !empty($rules['valid_days']);
        $hasHourRestriction = isset($rules['valid_hours']) && is_array($rules['valid_hours']) && !empty($rules['valid_hours']['start']) && !empty($rules['valid_hours']['end']);

        if (!$ignoreTimeWindow && ($hasDayRestriction || $hasHourRestriction)) {
            $isTimeValid = true;

            if ($hasHourRestriction) {
                $startStr = $rules['valid_hours']['start'];
                $endStr = $rules['valid_hours']['end'];
                
                $start = $now->copy()->setTimeFromTimeString($startStr);
                $end = $now->copy()->setTimeFromTimeString($endStr);
                $isOvernight = $end->lessThanOrEqualTo($start);

                if ($isOvernight) {
                    $realEnd = $end->copy()->addDay();
                    
                    if ($now->between($start, $realEnd)) {
                        // Window started today
                        if ($hasDayRestriction && !in_array($today, $rules['valid_days'])) {
                            $isTimeValid = false;
                        }
                    } elseif ($now->between($start->copy()->subDay(), $end)) {
                        // Window started yesterday
                        if ($hasDayRestriction && !in_array($yesterday, $rules['valid_days'])) {
                            $isTimeValid = false;
                        }
                    } else {
                        $isTimeValid = false;
                    }
                } else {
                    // Same-day window
                    if (!$now->between($start, $end)) {
                        $isTimeValid = false;
                    } elseif ($hasDayRestriction && !in_array($today, $rules['valid_days'])) {
                        $isTimeValid = false;
                    }
                }

                if (!$isTimeValid) {
                    $displayStart = \Illuminate\Support\Carbon::parse($startStr)->format('g:i A');
                    $displayEnd = \Illuminate\Support\Carbon::parse($endStr)->format('g:i A');
                    $reason = "This promotion is only valid between {$displayStart} and {$displayEnd}";
                    if ($hasDayRestriction) {
                        $days = array_map('ucfirst', $rules['valid_days']);
                        $reason .= " on " . implode(', ', $days);
                    }

                    return ['allowed' => false, 'reason' => $reason];
                }
            } else {
                // Only day restriction
                if (!in_array($today, $rules['valid_days'])) {
                    $days = array_map('ucfirst', $rules['valid_days']);
                    $daysStr = implode(', ', $days);
                    return ['allowed' => false, 'reason' => "This promotion is only valid on: {$daysStr}"];
                }
            }
        }

        return $result;
    }

    /**
     * Reward-eligibility helper for QRcade:
     * Determine whether this user has reached the per-user redemption cap for this promotion.
     *
     * IMPORTANT: This intentionally ignores valid_days/valid_hours so users can win a reward now and redeem later.
     */
    public function hasReachedPerUserLimit(int $customerUserId): bool
    {
        $rules = $this->rules ?? [];
        $limit = (int) ($rules['max_redemptions_per_user'] ?? 0);
        if ($limit <= 0) {
            return false; // unlimited per-user
        }

        $redemptionCount = $this->redemptions()
            ->where('customer_user_id', $customerUserId)
            ->count();

        // Count unredeemed game rewards (won but not yet redeemed).
        $unredeemedRewardCount = \App\Models\GameReward::where('user_id', $customerUserId)
            ->where('promotion_id', $this->id)
            ->whereIn('status', [
                \App\Models\GameReward::STATUS_AVAILABLE,
                \App\Models\GameReward::STATUS_CLAIMED,
            ])
            ->count();

        // Count unredeemed play-to-win tokens (issued on win, not yet redeemed).
        $unredeemedTokenCount = \App\Models\UserPromoToken::where('user_id', $customerUserId)
            ->whereHas('qrCode', fn ($q) => $q->where('promotion_id', $this->id))
            ->whereNull('redeemed_at')
            ->count();

        return ($redemptionCount + $unredeemedRewardCount + $unredeemedTokenCount) >= $limit;
    }

    public function calculateDiscount(float $amount, int $quantity = 1, array $options = []): array
    {
        $discount = 0;
        $details = [];

        switch ($this->discount_type) {
            case self::TYPE_PERCENTAGE:
                // Handle both formats: whole number (15 = 15%) or decimal (0.15 = 15%)
                if ($this->discount_value <= 1 && $this->discount_value > 0) {
                    // Stored as decimal (0.15 = 15%)
                    $discount = $amount * $this->discount_value;
                    $details['description'] = ($this->discount_value * 100) . "% off";
                } else {
                    // Stored as whole number (15 = 15%)
                    $discount = $amount * ($this->discount_value / 100);
                    $details['description'] = "{$this->discount_value}% off";
                }
                break;

            case self::TYPE_FIXED_AMOUNT:
                $discount = min($this->discount_value, $amount);
                $details['description'] = "\${$this->discount_value} off";
                break;

            case self::TYPE_BOGO:
                // Simple 1:1 math for BOGO: Savings = Purchase Amount.
                // If staff enters $10 for the paid item, they get $10 off for the free item.
                $discount = $amount;
                // Correct the total amount to be Paid + Saved for tracking
                $amount = $amount + $discount;
                $details['description'] = "Buy 1 Get 1 Free";
                $details['free_items'] = 1;
                break;

            case self::TYPE_BUY_X_GET_Y:
                $itemPrices = $options['item_prices'] ?? null;
                
                if ($itemPrices && is_array($itemPrices)) {
                    $buyPrices = $itemPrices['buy'] ?? [];
                    $getPrices = $itemPrices['get'] ?? [];
                    
                    $paidAmount = array_sum(array_map('floatval', $buyPrices));
                    $savedAmount = array_sum(array_map('floatval', $getPrices));
                    
                    $discount = $savedAmount;
                    $amount = $paidAmount + $savedAmount;
                    
                    $details['description'] = "Buy " . count($buyPrices) . " Get " . count($getPrices) . " Free";
                    $details['free_items'] = count($getPrices);
                } else {
                    $setSize = $this->buy_quantity + $this->get_quantity;
                    if ($quantity >= $setSize) {
                        $sets = floor($quantity / $setSize);
                        $freeItems = $sets * $this->get_quantity;
                        
                        // Input amount represents what they paid. 
                        // To calculate savings, we find the price per item based on the paid amount.
                        $paidItems = $quantity - $freeItems;
                        $pricePerItem = $paidItems > 0 ? ($amount / $paidItems) : $amount;
                        $discount = $freeItems * $pricePerItem;
                        
                        // Correct the total amount to be Paid + Saved for tracking
                        $amount = $amount + $discount;

                        $details['description'] = "Buy {$this->buy_quantity} Get {$this->get_quantity} Free";
                        $details['free_items'] = $freeItems;
                    }
                }
                break;

            case self::TYPE_BUY_X_FOR_Y:
                $itemPrices = $options['item_prices'] ?? null;
                $promoPrice = (float) $this->for_price;

                if ($itemPrices && is_array($itemPrices) && !empty($itemPrices['buy'])) {
                    $originalTotal = array_sum(array_map('floatval', $itemPrices['buy']));
                    $discount = max(0, $originalTotal - $promoPrice);
                    $amount = $originalTotal; // Track total value
                    $details['description'] = "Buy " . count($itemPrices['buy']) . " for $" . number_format($promoPrice, 2);
                } elseif ($quantity >= $this->buy_quantity && $promoPrice > 0) {
                    $originalPrice = $amount;
                    // Ensure discount is not negative (customer shouldn't pay more than original)
                    $discount = max(0, $originalPrice - $promoPrice);
                    $details['description'] = "Buy {$this->buy_quantity} for \${$this->for_price}";
                }
                break;

            case self::TYPE_PUNCH_CARD:
                // Is this redemption for the final free prize?
                $isFinalPrize = $options['is_final_prize'] ?? false;
                
                if ($isFinalPrize) {
                    // For the free item, savings = entered value (or reward_value fallback), customer pays $0
                    $effectiveValue = $amount;
                    if ($effectiveValue <= 0 && $this->reward_value !== null) {
                        $effectiveValue = (float) $this->reward_value;
                    }
                    $discount = $effectiveValue;
                    $amount = $discount; // Total value is the value of the free item
                    $details['description'] = "Free item reward (Card Completed)";
                } else {
                    // For a regular punch, savings = $0, customer pays the full amount
                    $discount = 0;
                    $details['description'] = "Punch card stamp";
                }
                break;

            case self::TYPE_HAPPY_HOUR:
            case self::TYPE_LOYALTY:
                if ($this->discount_value) {
                    if ($this->discount_value <= 1) {
                        $discount = $amount * $this->discount_value;
                    } else {
                        $discount = $amount * ($this->discount_value / 100);
                    }
                    $details['description'] = "{$this->discount_value}% off";
                }
                break;
            case self::TYPE_FIRST_TIME:
                $mode = $this->rules['first_time_kind'] ?? 'percentage';
                if ($this->discount_value) {
                    if ($mode === 'fixed') {
                        $discount = min($this->discount_value, $amount);
                        $details['description'] = "\${$this->discount_value} off (First Time)";
                    } else {
                        if ($this->discount_value <= 1) {
                            $discount = $amount * $this->discount_value;
                        } else {
                            $discount = $amount * ($this->discount_value / 100);
                        }
                        $details['description'] = "{$this->discount_value}% off (First Time)";
                    }
                }
                break;

            case self::TYPE_TIERED:
                if ($this->tiers && is_array($this->tiers)) {
                    // Find the highest tier that applies
                    $applicableTier = null;
                    foreach ($this->tiers as $tier) {
                        if (isset($tier['min_spend']) && $amount >= $tier['min_spend']) {
                            if (!$applicableTier || $tier['min_spend'] > $applicableTier['min_spend']) {
                                $applicableTier = $tier;
                            }
                        }
                    }
                    if ($applicableTier && isset($applicableTier['discount'])) {
                        $discount = $amount * ($applicableTier['discount'] / 100);
                        $details['description'] = "{$applicableTier['discount']}% off (tiered)";
                    }
                }
                break;
        }

        // Apply maximum discount if set
        if ($this->maximum_discount && $discount > $this->maximum_discount) {
            $discount = $this->maximum_discount;
        }

        return [
            'discount' => round($discount, 2),
            'final_amount' => round($amount - $discount, 2),
            'original_amount' => round($amount, 2),
            'details' => $details,
        ];
    }

    /**
     * Determine if this promotion needs a calculator at redemption
     */
    public function needsCalculator(): bool
    {
        switch ($this->discount_type) {
            case self::TYPE_PERCENTAGE:
                // Needs calculator if no original_price set (variable amount)
                return !$this->original_price;
            
            case self::TYPE_FIXED_AMOUNT:
                // Usually doesn't need calculator, but check if there's minimum_purchase
                return false; // Can use discount_value directly
            
            case self::TYPE_BOGO:
            case self::TYPE_BUY_X_GET_Y:
            case self::TYPE_BUY_X_FOR_Y:
                // Always needs calculator - need purchase amount or individual item prices
                return true;
            
            case self::TYPE_PUNCH_CARD:
                // Always needs calculator - we want to track revenue on every punch
                return true;
            
            case self::TYPE_TIERED:
                // Needs calculator - depends on purchase amount
                return true;
            
            case self::TYPE_HAPPY_HOUR:
            case self::TYPE_FIRST_TIME:
            case self::TYPE_LOYALTY:
                // Needs calculator if percentage and no original_price
                return !$this->original_price;
            
            case self::TYPE_BUNDLE:
                // Usually fixed, but might need calculator
                return !$this->original_price;
            
            default:
                return false;
        }
    }

    /**
     * Get pre-filled calculator values for redemption screen
     */
    public function getCalculatorPrefills(): array
    {
        $prefills = [];
        
        switch ($this->discount_type) {
            case self::TYPE_PERCENTAGE:
            case self::TYPE_HAPPY_HOUR:
            case self::TYPE_FIRST_TIME:
            case self::TYPE_LOYALTY:
                if ($this->discount_value) {
                    $prefills['percentage'] = $this->discount_value;
                }
                if ($this->original_price) {
                    $prefills['purchase_amount'] = $this->original_price;
                }
                if ($this->minimum_purchase) {
                    $prefills['minimum_purchase'] = $this->minimum_purchase;
                }
                break;
            
            case self::TYPE_BOGO:
            case self::TYPE_BUY_X_GET_Y:
                // No pre-fills, need actual purchase amount
                break;
            
            case self::TYPE_TIERED:
                if ($this->tiers && is_array($this->tiers)) {
                    $prefills['tiers'] = $this->tiers;
                }
                break;
        }
        
        return $prefills;
    }

    public function getDisplayDescription(): string
    {
        switch ($this->discount_type) {
            case self::TYPE_PERCENTAGE:
                // Handle both formats: whole number (15 = 15%) or decimal (0.15 = 15%)
                if ($this->discount_value <= 1 && $this->discount_value > 0) {
                    return ($this->discount_value * 100) . "% Off";
                }
                return "{$this->discount_value}% Off";
            case self::TYPE_FIXED_AMOUNT:
                return "\${$this->discount_value} Off";
            case self::TYPE_BOGO:
                return "Buy One Get One Free";
            case self::TYPE_BUY_X_GET_Y:
                return "Buy {$this->buy_quantity} Get {$this->get_quantity} Free";
            case self::TYPE_BUY_X_FOR_Y:
                return "{$this->buy_quantity} for \${$this->for_price}";
            case self::TYPE_PUNCH_CARD:
                return "Punch Card: {$this->punches_required} punches for reward";
            default:
                return $this->name;
        }
    }

    /**
     * Get the final price for percentage and happy_hour discounts with original_price
     * Returns null if not applicable
     */
    public function getFinalPrice(): ?float
    {
        if (in_array($this->discount_type, [self::TYPE_PERCENTAGE, self::TYPE_HAPPY_HOUR]) 
            && $this->original_price && $this->discount_value) {
            $discount = $this->original_price * ($this->discount_value / 100);
            
            // Apply maximum discount if set
            if ($this->maximum_discount && $discount > $this->maximum_discount) {
                $discount = $this->maximum_discount;
            }
            
            return round($this->original_price - $discount, 2);
        }
        
        return null;
    }
}

