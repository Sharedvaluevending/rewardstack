<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessGamePack extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'game_pack_id',
        'stripe_subscription_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_TRIAL = 'trial';

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function gamePack()
    {
        return $this->belongsTo(GamePack::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIAL]);
    }

    // Helpers
    public function isActive(): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            return !$this->ends_at || $this->ends_at->isFuture();
        }

        if ($this->status === self::STATUS_TRIAL) {
            return $this->trial_ends_at && $this->trial_ends_at->isFuture();
        }

        return false;
    }
}

