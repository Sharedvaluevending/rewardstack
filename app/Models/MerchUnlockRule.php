<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchUnlockRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'unlock_type',
        'game_id',
        'game_pack_id',
        'badge_id',
        'duration_type',
        'duration_days',
        'valid_until',
        'reward_multiplier',
        'extra_plays_per_day',
        'is_active',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'valid_until' => 'date',
        'reward_multiplier' => 'decimal:2',
        'extra_plays_per_day' => 'integer',
        'is_active' => 'boolean',
    ];

    const UNLOCK_GAME = 'game';
    const UNLOCK_GAME_PACK = 'game_pack';
    const UNLOCK_DOUBLE_REWARDS = 'double_rewards';
    const UNLOCK_EXCLUSIVE_BADGE = 'exclusive_badge';

    const DURATION_PERMANENT = 'permanent';
    const DURATION_DAYS = 'days';
    const DURATION_UNTIL_DATE = 'until_date';

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePack()
    {
        return $this->belongsTo(GamePack::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function userMerchUnlocks()
    {
        return $this->hasMany(UserMerchUnlock::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function getUnlockDescription(): string
    {
        $description = match ($this->unlock_type) {
            self::UNLOCK_GAME => "Unlocks: " . ($this->game?->name ?? 'Game'),
            self::UNLOCK_GAME_PACK => "Unlocks: " . ($this->gamePack?->name ?? 'Game Pack'),
            self::UNLOCK_DOUBLE_REWARDS => "Double Rewards",
            self::UNLOCK_EXCLUSIVE_BADGE => "Exclusive Badge: " . ($this->badge?->name ?? 'Badge'),
            default => 'Special Unlock',
        };

        if ($this->duration_type === self::DURATION_DAYS) {
            $description .= " for {$this->duration_days} days";
        } elseif ($this->duration_type === self::DURATION_UNTIL_DATE && $this->valid_until) {
            $description .= " until " . $this->valid_until->format('M j, Y');
        }

        return $description;
    }

    public function calculateExpiryDate(): ?\Carbon\Carbon
    {
        return match ($this->duration_type) {
            self::DURATION_PERMANENT => null,
            self::DURATION_DAYS => now()->addDays($this->duration_days),
            self::DURATION_UNTIL_DATE => $this->valid_until?->endOfDay(),
            default => null,
        };
    }
}

