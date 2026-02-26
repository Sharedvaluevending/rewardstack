<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrossPromotion extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Clamp revenue_share_percent to 0–100 to prevent invalid values
            if ($model->isDirty('revenue_share_percent')) {
                $model->revenue_share_percent = max(0, min(100, (float) $model->revenue_share_percent));
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'business_1_id',
        'business_2_id',
        'requested_by_business_id',
        'promotion_1_id',
        'promotion_2_id',
        'display_mode',
        'chain_mode',
        'primary_promotion_id',
        'revenue_share_percent',
        'cross_promo_rules',
        'rules_status',
        'starts_at',
        'expires_at',
        'usage_limit',
        'status',
        'accepted_at',
        'declined_at',
        'is_active',
    ];

    protected $casts = [
        'revenue_share_percent' => 'decimal:2',
        'cross_promo_rules' => 'array',
        'is_active' => 'boolean',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    const DISPLAY_SPLIT = 'split';
    const DISPLAY_ALTERNATING = 'alternating';
    const DISPLAY_RANDOM = 'random';
    
    // Chain Modes
    const CHAIN_OPEN = 'open'; // Both unlocked, redeem in any order
    const CHAIN_SEQUENTIAL = 'sequential'; // Redeem primary first to unlock secondary
    
    // Rules Status
    const RULES_USE_PROMOTION_RULES = 'use_promotion_rules'; // Each promotion uses its own rules (default)
    const RULES_PENDING_AGREEMENT = 'pending_agreement'; // Rules proposed, waiting for partner approval
    const RULES_AGREED = 'agreed'; // Both businesses agreed on shared rules
    const RULES_OVERRIDDEN = 'overridden'; // One business overrode with their own rules

    public static function displayModes(): array
    {
        return [
            self::DISPLAY_SPLIT => 'Split View (Both shown)',
            self::DISPLAY_ALTERNATING => 'Alternating (Rotate)',
            self::DISPLAY_RANDOM => 'Random (Pick one)',
        ];
    }

    public static function chainModes(): array
    {
        return [
            self::CHAIN_OPEN => 'Open Chain (Redeem in any order)',
            self::CHAIN_SEQUENTIAL => 'Sequential (Unlock 2nd deal)',
        ];
    }

    // Relationships
    public function business1()
    {
        return $this->belongsTo(Business::class, 'business_1_id');
    }

    public function business2()
    {
        return $this->belongsTo(Business::class, 'business_2_id');
    }

    public function promotion1()
    {
        return $this->belongsTo(Promotion::class, 'promotion_1_id');
    }

    public function promotion2()
    {
        return $this->belongsTo(Promotion::class, 'promotion_2_id');
    }

    public function primaryPromotion()
    {
        return $this->belongsTo(Promotion::class, 'primary_promotion_id');
    }

    public function qrCodes()
    {
        return $this->hasMany(QRCode::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', self::STATUS_ACCEPTED)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            });
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->where('business_1_id', $businessId)
              ->orWhere('business_2_id', $businessId);
        });
    }
    
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
    
    public function scopeNotStarted($query)
    {
        return $query->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    // Helpers
    public function involvesBusinesses(int $business1Id, int $business2Id): bool
    {
        return ($this->business_1_id === $business1Id && $this->business_2_id === $business2Id)
            || ($this->business_1_id === $business2Id && $this->business_2_id === $business1Id);
    }

    public function getPartnerBusiness(int $myBusinessId): ?Business
    {
        if ($this->business_1_id === $myBusinessId) {
            return $this->business2;
        }
        return $this->business1;
    }

    public function getMyPromotion(int $myBusinessId): ?Promotion
    {
        if ($this->business_1_id === $myBusinessId) {
            return $this->promotion1;
        }
        return $this->promotion2;
    }

    public function getPartnerPromotion(int $myBusinessId): ?Promotion
    {
        if ($this->business_1_id === $myBusinessId) {
            return $this->promotion2;
        }
        return $this->promotion1;
    }
    
    /**
     * Check if a specific promotion is currently locked for a user
     */
    public function isPromotionLocked(int $promotionId, ?int $userId = null): bool
    {
        if ($this->chain_mode !== self::CHAIN_SEQUENTIAL) {
            return false;
        }
        
        // If this is the primary promotion, it's never locked
        $primaryId = $this->primary_promotion_id ?: $this->promotion_1_id;
        if ($promotionId === $primaryId) {
            return false;
        }
        
        // If user is null (guest), the secondary is locked by default
        if (!$userId) {
            return true;
        }

        // If primary is no longer redeemable (expired/invalid/at-limit), do not lock secondary forever.
        if (!$this->isPrimaryRedeemable($userId)) {
            return false;
        }

        // Check if user has redeemed the primary promotion (UserPromoToken is the source of truth here)
        $primaryRedeemed = \App\Models\UserPromoToken::where('user_id', $userId)
            ->where('promotion_id', $primaryId)
            ->whereNotNull('redeemed_at')
            ->exists();

        return !$primaryRedeemed;
    }
    
    /**
     * Check if cross-promo is currently valid (not expired, started, active)
     */
    public function isValid(): bool
    {
        if (!$this->is_active || $this->status !== self::STATUS_ACCEPTED) {
            return false;
        }
        
        $now = now();
        
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if cross-promo has reached usage limit
     */
    public function hasReachedUsageLimit(): bool
    {
        if (!$this->usage_limit) {
            return false; // No limit set
        }
        
        // Count total claims (UserPromoTokens created for promotions in this cross-promo)
        $totalClaims = \App\Models\UserPromoToken::whereIn('promotion_id', [
            $this->promotion_1_id,
            $this->promotion_2_id,
        ])
        ->whereHas('qrCode', function ($q) {
            $q->where('cross_promotion_id', $this->id);
        })
        ->count();
        
        return $totalClaims >= $this->usage_limit;
    }
    
    /**
     * Get effective rules for a promotion (cross-promo rules override promotion rules)
     */
    public function getEffectiveRules(int $promotionId): array
    {
        $promotion = ($promotionId === $this->promotion_1_id) 
            ? $this->promotion1 
            : $this->promotion2;
            
        if (!$promotion) {
            return [];
        }
        
        // If cross-promo has agreed rules, use those
        if ($this->rules_status === self::RULES_AGREED && $this->cross_promo_rules) {
            return $this->cross_promo_rules;
        }
        
        // Otherwise, use promotion's own rules
        return $promotion->rules ?? [];
    }
    
    /**
     * Check if primary promotion is redeemable (not expired, not at limit, valid time)
     */
    public function isPrimaryRedeemable(?int $userId = null): bool
    {
        $primaryId = $this->primary_promotion_id ?: $this->promotion_1_id;
        $primaryPromo = ($primaryId === $this->promotion_1_id) 
            ? $this->promotion1 
            : $this->promotion2;
            
        if (!$primaryPromo) {
            return false;
        }
        
        // Check if promotion is valid
        if (!$primaryPromo->isCurrentlyValid()) {
            return false;
        }
        
        // Check cross-promo rules if applicable
        $rules = $this->getEffectiveRules($primaryId);
        
        // Check if promotion can be redeemed
        $canRedeem = $primaryPromo->canRedeem(null, $userId);
        if (!$canRedeem['allowed']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Auto-deactivate if expired
     */
    public function checkAndDeactivateIfExpired(): void
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->update(['is_active' => false]);
        }
    }
    
    /**
     * Get rules conflicts between two promotions
     */
    public static function detectRulesConflicts(Promotion $promo1, Promotion $promo2): array
    {
        $conflicts = [];
        $rules1 = $promo1->rules ?? [];
        $rules2 = $promo2->rules ?? [];
        
        // Check time window conflicts
        $hours1 = $rules1['valid_hours'] ?? null;
        $hours2 = $rules2['valid_hours'] ?? null;
        if ($hours1 && $hours2) {
            // Check if time windows overlap
            $start1 = $hours1['start'] ?? null;
            $end1 = $hours1['end'] ?? null;
            $start2 = $hours2['start'] ?? null;
            $end2 = $hours2['end'] ?? null;
            
            if ($start1 && $end1 && $start2 && $end2) {
                // Simple overlap check (doesn't handle overnight windows)
                if ($end1 <= $start2 || $end2 <= $start1) {
                    $conflicts[] = [
                        'type' => 'time_window',
                        'message' => 'Time windows do not overlap',
                        'promo1' => "{$start1} - {$end1}",
                        'promo2' => "{$start2} - {$end2}",
                    ];
                }
            }
        }
        
        // Check day restrictions conflicts
        $days1 = $rules1['valid_days'] ?? [];
        $days2 = $rules2['valid_days'] ?? [];
        if (!empty($days1) && !empty($days2)) {
            $overlap = array_intersect($days1, $days2);
            if (empty($overlap)) {
                $conflicts[] = [
                    'type' => 'day_restriction',
                    'message' => 'No overlapping valid days',
                    'promo1' => implode(', ', array_map('ucfirst', $days1)),
                    'promo2' => implode(', ', array_map('ucfirst', $days2)),
                ];
            }
        }
        
        // Check usage limit differences (warning, not blocking)
        $limit1 = $rules1['max_redemptions_per_user'] ?? null;
        $limit2 = $rules2['max_redemptions_per_user'] ?? null;
        if ($limit1 && $limit2 && $limit1 !== $limit2) {
            $conflicts[] = [
                'type' => 'usage_limit_difference',
                'severity' => 'warning',
                'message' => 'Different per-user limits',
                'promo1' => $limit1,
                'promo2' => $limit2,
            ];
        }
        
        return $conflicts;
    }
}
