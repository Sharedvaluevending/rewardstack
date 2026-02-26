<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_id',
        'business_id',
        'business_user_id',
        'referral_code',
        'commission_rate',
        'status',
        'converted_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'converted_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The user who referred this business
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * The business that was referred
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The business owner user
     */
    public function businessUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_user_id');
    }

    /**
     * Commission records for this referral
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    /**
     * Scope for active referrals
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Calculate total earned from this referral
     */
    public function getTotalEarnedAttribute(): float
    {
        if ($this->relationLoaded('commissions')) {
            return (float) $this->commissions->where('status', 'paid')->sum('commission_amount');
        }
        return (float) $this->commissions()->where('status', 'paid')->sum('commission_amount');
    }

    /**
     * Calculate pending earnings from this referral
     */
    public function getPendingEarningsAttribute(): float
    {
        if ($this->relationLoaded('commissions')) {
            return (float) $this->commissions->where('status', 'pending')->sum('commission_amount');
        }
        return (float) $this->commissions()->where('status', 'pending')->sum('commission_amount');
    }
}
