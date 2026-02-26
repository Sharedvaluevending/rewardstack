<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMerchUnlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'merch_unlock_rule_id',
        'status',
        'activated_at',
        'expires_at',
        'printful_order_id',
        'tracking_number',
        'delivered_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function merchUnlockRule()
    {
        return $this->belongsTo(MerchUnlockRule::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // Helpers
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function activate(): void
    {
        $rule = $this->merchUnlockRule;
        
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
            'expires_at' => $rule->calculateExpiryDate(),
        ]);
    }

    public function expire(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function markDelivered(): void
    {
        $this->update(['delivered_at' => now()]);
        
        // Auto-activate on delivery
        if ($this->status === self::STATUS_PENDING) {
            $this->activate();
        }
    }

    public function getDaysRemaining(): ?int
    {
        if (!$this->expires_at || !$this->isActive()) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at));
    }
}

