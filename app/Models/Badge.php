<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'color',
        'category',
        'rarity',
        'requirements',
        'game_id',
        'business_id',
        'points',
        'is_hidden',
        'is_seasonal',
        'available_from',
        'available_until',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'requirements' => 'array',
        'points' => 'integer',
        'is_hidden' => 'boolean',
        'is_seasonal' => 'boolean',
        'available_from' => 'date',
        'available_until' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const CATEGORY_ACHIEVEMENT = 'achievement';
    const CATEGORY_MILESTONE = 'milestone';
    const CATEGORY_STREAK = 'streak';
    const CATEGORY_EXPLORER = 'explorer';
    const CATEGORY_CHAMPION = 'champion';
    const CATEGORY_SEASONAL = 'seasonal';
    const CATEGORY_SPECIAL = 'special';

    const RARITY_COMMON = 'common';
    const RARITY_UNCOMMON = 'uncommon';
    const RARITY_RARE = 'rare';
    const RARITY_EPIC = 'epic';
    const RARITY_LEGENDARY = 'legendary';

    public static function rarities(): array
    {
        return [
            self::RARITY_COMMON => ['name' => 'Common', 'color' => '#9CA3AF'],
            self::RARITY_UNCOMMON => ['name' => 'Uncommon', 'color' => '#10B981'],
            self::RARITY_RARE => ['name' => 'Rare', 'color' => '#3B82F6'],
            self::RARITY_EPIC => ['name' => 'Epic', 'color' => '#8B5CF6'],
            self::RARITY_LEGENDARY => ['name' => 'Legendary', 'color' => '#F59E0B'],
        ];
    }

    // Relationships
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot(['earned_at', 'is_featured'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeAvailable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('available_from')
                ->orWhere('available_from', '<=', now());
        })->where(function ($q) {
            $q->whereNull('available_until')
                ->orWhere('available_until', '>=', now());
        });
    }

    // Helpers
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->available_from && $this->available_from->isFuture()) {
            return false;
        }

        if ($this->available_until && $this->available_until->isPast()) {
            return false;
        }

        return true;
    }

    public function getRarityInfo(): array
    {
        return self::rarities()[$this->rarity] ?? self::rarities()[self::RARITY_COMMON];
    }

    public function checkRequirements(User $user, ?GamePlay $gamePlay = null): bool
    {
        if (!$this->requirements) {
            return false;
        }

        $req = $this->requirements;
        $type = $req['type'] ?? null;
        $value = $req['value'] ?? 0;

        return match ($type) {
            'games_played' => $user->total_games_played >= $value,
            'wins' => $user->total_wins >= $value,
            'score' => $gamePlay && $gamePlay->score >= $value,
            'streak' => $user->current_streak >= $value,
            'badges' => $user->total_badges >= $value,
            'perfect_game' => $gamePlay && ($gamePlay->game_data['perfect'] ?? false),
            'speed_run' => $gamePlay && $gamePlay->duration_seconds <= $value,
            'total_scans' => $this->getUserScanCount($user) >= $value,
            'total_redeemed' => $this->getUserRedemptionCount($user) >= $value,
            'total_savings' => $user->total_savings >= $value,
            'business_diversity_scans' => $this->getUserBusinessDiversity($user, 'scans') >= $value,
            'business_diversity_redemptions' => $this->getUserBusinessDiversity($user, 'redemptions') >= $value,
            'combo_power_user' => $this->getUserScanCount($user) >= ($req['scans'] ?? 0) && $this->getUserRedemptionCount($user) >= ($req['redemptions'] ?? 0),
            'combo_elite_member' => $user->total_savings >= ($req['savings'] ?? 0) && $this->getUserScanCount($user) >= ($req['scans'] ?? 0),
            default => false,
        };
    }

    // Helper methods for new badge requirements
    private function getUserScanCount(User $user): int
    {
        static $scanCounts = [];

        if (array_key_exists($user->id, $scanCounts)) {
            return $scanCounts[$user->id];
        }

        $scanCounts[$user->id] = \App\Models\Scan::where('user_id', $user->id)
            ->distinct('qr_code_id')
            ->count('qr_code_id');

        return $scanCounts[$user->id];
    }

    private function getUserRedemptionCount(User $user): int
    {
        static $redemptionCounts = [];

        if (array_key_exists($user->id, $redemptionCounts)) {
            return $redemptionCounts[$user->id];
        }

        // Count both game rewards redeemed and promotion redemptions
        $gameRewardsRedeemed = \App\Models\GameReward::where('user_id', $user->id)
            ->where('redeemed_at', '!=', null)
            ->count();

        $promoRedemptions = \App\Models\Redemption::where('customer_user_id', $user->id)->count();

        $redemptionCounts[$user->id] = $gameRewardsRedeemed + $promoRedemptions;

        return $redemptionCounts[$user->id];
    }

    private function getUserBusinessDiversity(User $user, string $type): int
    {
        static $diversityCounts = [];
        $key = $user->id . ':' . $type;

        if (array_key_exists($key, $diversityCounts)) {
            return $diversityCounts[$key];
        }

        if ($type === 'scans') {
            $diversityCounts[$key] = \App\Models\Scan::where('user_id', $user->id)
                ->join('qr_codes', 'scans.qr_code_id', '=', 'qr_codes.id')
                ->distinct('qr_codes.business_id')
                ->count('qr_codes.business_id');
            return $diversityCounts[$key];
        }

        if ($type === 'redemptions') {
            $diversityCounts[$key] = \App\Models\Redemption::where('customer_user_id', $user->id)
                ->distinct('business_id')
                ->count('business_id');
            return $diversityCounts[$key];
        }

        return 0;
    }

    public function awardTo(User $user, ?GamePlay $gamePlay = null, ?int $businessId = null): ?UserBadge
    {
        // Check if already earned
        $existing = UserBadge::where('user_id', $user->id)
            ->where('badge_id', $this->id)
            ->where('business_id', $businessId)
            ->first();

        if ($existing) {
            return null;
        }

        $userBadge = UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $this->id,
            'business_id' => $businessId,
            'game_play_id' => $gamePlay?->id,
            'earned_at' => now(),
            'is_complete' => true,
        ]);

        // Update user stats
        $user->increment('total_badges');
        $user->increment('badge_points', $this->points);
        app(\App\Services\XpService::class)->awardForBadge($user, $this, $businessId);

        return $userBadge;
    }
}

