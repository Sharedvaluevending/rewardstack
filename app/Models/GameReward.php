<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\RewardCodeService;
use App\Notifications\PortalRewardWon;

class GameReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'reward_code',
        'qr_image_path',
        'game_play_id',
        'leaderboard_entry_id',
        'user_id',
        'business_id',
        'promotion_id',
        'reward_type',
        'tier',
        'discount_value',
        'free_item',
        'description',
        'status',
        'claimed_at',
        'redeemed_at',
        'expires_at',
        'redeemed_by_employee_id',
        'next_visit_only',
        'valid_from',
        'valid_until',
    ];

    protected $appends = ['qr_image_url'];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'claimed_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
        'next_visit_only' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_CLAIMED = 'claimed';
    const STATUS_REDEEMED = 'redeemed';
    const STATUS_EXPIRED = 'expired';

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';
    const TYPE_FREE_ITEM = 'free_item';
    const TYPE_BADGE = 'badge';
    const TYPE_MYSTERY = 'mystery';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reward) {
            if (empty($reward->reward_code)) {
                $reward->reward_code = app(RewardCodeService::class)->generateUniqueCode();
            }
        });

        // Increment total_rewards_won when a reward is created (not when claimed)
        static::created(function ($reward) {
            if ($reward->user_id && $reward->status === self::STATUS_AVAILABLE) {
                $reward->user->increment('total_rewards_won');
                if ($reward->reward_type !== self::TYPE_BADGE && !$reward->leaderboard_entry_id) {
                    if (in_array($reward->user?->role, ['user', 'customer'], true)) {
                        $reward->user?->notify(new PortalRewardWon($reward));
                    }
                }
            }
        });
    }

    // Relationships
    public function gamePlay()
    {
        return $this->belongsTo(GamePlay::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function redeemedByEmployee()
    {
        return $this->belongsTo(Employee::class, 'redeemed_by_employee_id');
    }

    public function leaderboardEntry()
    {
        return $this->belongsTo(LeaderboardEntry::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helpers
    public function isAvailable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }

    public function claim(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CLAIMED,
            'claimed_at' => now(),
        ]);

        return true;
    }

    public function redeem(?int $employeeId = null, ?int $redeemedByUserId = null): bool
    {
        if ($this->status !== self::STATUS_CLAIMED && $this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        // Check expiry: a reward that has passed its expires_at or valid_until
        // should not be redeemable, even if it was claimed in time.
        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->update(['status' => self::STATUS_EXPIRED]);
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            $this->update(['status' => self::STATUS_EXPIRED]);
            return false;
        }

        $this->update([
            'status' => self::STATUS_REDEEMED,
            'redeemed_at' => now(),
            'redeemed_by_employee_id' => $employeeId,
        ]);

        // Update user stats
        if ($this->user) {
            $this->user->increment('total_rewards_redeemed');
            if ($this->discount_value) {
                $this->user->increment('total_savings', $this->discount_value);
            }
            // Award XP for redemption (scaled by effective savings)
            app(\App\Services\XpService::class)->awardForRewardRedemption($this->user, $this, $this->promotion);
        }

        // Create redemption record for business analytics
        // Only create if reward has a promotion_id (required by redemptions table)
        if ($this->promotion_id && $this->business_id) {
            try {
                // Ensure relationships are loaded
                if (!$this->relationLoaded('gamePlay')) {
                    $this->load('gamePlay');
                }
                if (!$this->relationLoaded('user')) {
                    $this->load('user');
                }
                if (!$this->relationLoaded('promotion')) {
                    $this->load('promotion');
                }

                // Get QR code from game play if available
                $qrCodeId = null;
                if ($this->gamePlay && $this->gamePlay->qr_code_id) {
                    $qrCodeId = $this->gamePlay->qr_code_id;
                }

                // Get customer identifier
                $customerIdentifier = null;
                if ($this->user) {
                    // Try to get customer code from user
                    $customerCodeService = app(\App\Services\CustomerCodeService::class);
                    $customerIdentifier = $customerCodeService->getOrCreate($this->user);
                }

                Redemption::create([
                    'promotion_id' => $this->promotion_id,
                    'qr_code_id' => $qrCodeId,
                    'business_id' => $this->business_id,
                    'employee_id' => $employeeId,
                    'redeemed_by_user_id' => $redeemedByUserId,
                    'customer_user_id' => $this->user_id,
                    'customer_identifier' => $customerIdentifier,
                    'customer_name' => $this->user?->name,
                    'customer_email' => $this->user?->email,
                    'discount_amount' => $this->discount_value ?? 0,
                    'redeemed_at' => now(),
                ]);

                // Update promotion stats if promotion exists
                // Use atomic increment on a locked row to stay consistent with total limit checks
                if ($this->promotion) {
                    $promo = Promotion::where('id', $this->promotion_id)->lockForUpdate()->first();
                    if ($promo) {
                        $rules = is_array($promo->rules) ? $promo->rules : [];
                        $maxTotal = (int) ($rules['max_redemptions_total'] ?? 0);

                        // If a total limit is set and already reached, skip incrementing
                        // (the reward is still redeemed, but stats won't exceed the cap)
                        if ($maxTotal <= 0 || $promo->total_redemptions < $maxTotal) {
                            $promo->increment('total_redemptions');
                        }
                        if ($this->discount_value) {
                            $promo->increment('total_savings', $this->discount_value);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail redemption
                \Log::error('Failed to create redemption record for reward', [
                    'reward_id' => $this->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return true;
    }

    public function getDisplayValue(): string
    {
        return match ($this->reward_type) {
            self::TYPE_PERCENTAGE => "{$this->discount_value}% Off",
            self::TYPE_FIXED => "\${$this->discount_value} Off",
            self::TYPE_FREE_ITEM => "Free: {$this->free_item}",
            self::TYPE_BADGE => "Badge Earned!",
            self::TYPE_MYSTERY => "Mystery Reward!",
            default => $this->description ?? 'Reward',
        };
    }

    public function getQrImageUrlAttribute(): ?string
    {
        if (!$this->qr_image_path) {
            return null;
        }
        return asset('storage/' . $this->qr_image_path);
    }
}

