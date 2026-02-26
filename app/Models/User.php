<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        // 'role' intentionally excluded — set via forceFill() to prevent privilege escalation
        'phone',
        'avatar_path',
        'timezone',
        'preferences',
        'is_active',
        // Game stats
        'total_games_played',
        'total_wins',
        'total_losses',
        'lifetime_score',
        'highest_score',
        'current_streak',
        'best_streak',
        'last_play_date',
        'favorite_business_id',
        'favorite_game_id',
        'total_rewards_won',
        'total_rewards_redeemed',
        'total_savings',
        'total_badges',
        'badge_points',
        'xp',
        'level',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferences' => 'array',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'last_play_date' => 'date',
        'total_games_played' => 'integer',
        'total_wins' => 'integer',
        'total_losses' => 'integer',
        'lifetime_score' => 'integer',
        'highest_score' => 'integer',
        'current_streak' => 'integer',
        'best_streak' => 'integer',
        'total_rewards_won' => 'integer',
        'total_rewards_redeemed' => 'integer',
        'total_savings' => 'decimal:2',
        'total_badges' => 'integer',
        'badge_points' => 'integer',
        'xp' => 'integer',
        'level' => 'integer',
    ];

    protected $appends = ['avatar_url'];

    /**
     * Get the user's avatar URL
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }
        return null;
    }

    // Role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_BUSINESS = 'business';
    const ROLE_EMPLOYEE = 'employee';
    const ROLE_CUSTOMER = 'customer';

    // Role checks
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isBusiness(): bool
    {
        return $this->role === self::ROLE_BUSINESS;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    // Relationships
    public function business()
    {
        return $this->hasOne(Business::class);
    }

    public function employments()
    {
        return $this->hasMany(Employee::class);
    }

    // Get the business this user works for (as employee)
    public function employerBusiness()
    {
        return $this->hasOneThrough(
            Business::class,
            Employee::class,
            'user_id',
            'id',
            'id',
            'business_id'
        );
    }

    // Game relationships
    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }

    public function gamePlays()
    {
        return $this->hasMany(GamePlay::class);
    }

    public function gameRewards()
    {
        return $this->hasMany(GameReward::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot(['earned_at', 'is_featured', 'is_new'])
            ->withTimestamps();
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function leaderboardEntries()
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    public function tournamentParticipations()
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function tournaments()
    {
        return $this->belongsToMany(Tournament::class, 'tournament_participants')
            ->withPivot(['best_score', 'rank', 'attempts_used'])
            ->withTimestamps();
    }

    public function merchUnlocks()
    {
        return $this->hasMany(UserMerchUnlock::class);
    }

    public function favoriteBusiness()
    {
        return $this->belongsTo(Business::class, 'favorite_business_id');
    }

    public function favoriteGame()
    {
        return $this->belongsTo(Game::class, 'favorite_game_id');
    }

    public function scans()
    {
        return $this->hasMany(Scan::class);
    }

    public function savedQRCodes()
    {
        return $this->hasMany(SavedQRCode::class);
    }

    public function punchCards()
    {
        return $this->hasMany(PunchCard::class);
    }

    // Game helpers
    public function getWinRate(): float
    {
        if ($this->total_games_played === 0) {
            return 0;
        }
        return round(($this->total_wins / $this->total_games_played) * 100, 1);
    }

    public function getLevelProgress(): int
    {
        // Be defensive: legacy rows or seeds may have null level/xp.
        $level = (int) ($this->level ?: 1);
        $xp = (int) ($this->xp ?: 0);

        $xpForNextLevel = $this->getXpForLevel($level + 1);
        $xpForCurrentLevel = $this->getXpForLevel($level);
        $xpInCurrentLevel = max(0, $xp - $xpForCurrentLevel);
        $xpNeeded = $xpForNextLevel - $xpForCurrentLevel;
        
        if ($xpNeeded <= 0) {
            return 100; // Already at max level or invalid calculation
        }
        
        $progress = round(($xpInCurrentLevel / $xpNeeded) * 100);
        return min(100, max(0, $progress));
    }

    public function getXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0; // Level 1 starts at 0 XP
        }
        // Exponential curve: 5000 * (level - 1)^1.5
        // Level 2: 5,000 XP, Level 3: 12,500 XP, Level 5: 31,250 XP, Level 10: 125,000 XP
        return (int) (5000 * pow($level - 1, 1.5));
    }

    public function addXp(int $amount): void
    {
        $this->xp = (int) ($this->xp ?: 0);
        $this->level = (int) ($this->level ?: 1);

        $this->xp += $amount;
        
        // Check for level up
        while ($this->xp >= $this->getXpForLevel($this->level + 1)) {
            $this->level++;
        }
        
        $this->save();
    }

    public function updateStreak(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $last = $this->last_play_date
            ? (method_exists($this->last_play_date, 'toDateString') ? $this->last_play_date->toDateString() : (string) $this->last_play_date)
            : null;

        if ($last === $yesterday) {
            $this->current_streak++;
            $this->best_streak = max($this->best_streak, $this->current_streak);
        } elseif ($last !== $today) {
            $this->current_streak = 1;
        }

        $this->last_play_date = $today;
        $this->save();
    }

    public function recordGamePlay(GamePlay $gamePlay): int
    {
        $this->total_games_played++;
        $this->lifetime_score += $gamePlay->score;
        $this->highest_score = max($this->highest_score, $gamePlay->score);

        if ($gamePlay->result === GamePlay::RESULT_WIN) {
            $this->total_wins++;
        } else {
            $this->total_losses++;
        }

        $this->updateStreak();

        // XP Gating: Prevent farming by only awarding XP for:
        // 1. First play of this specific game/location today
        // 2. OR a new Personal Best score (improvement)
        // 3. OR it's a win (optional: small win bonus? No, let's stick to first-play/PB for now to be safe)
        $playedToday = $this->gamePlays()
            ->where('business_id', $gamePlay->business_id)
            ->where('game_id', $gamePlay->game_id)
            ->whereDate('created_at', now()->toDateString())
            ->where('id', '!=', $gamePlay->id)
            ->exists();

        if (!$playedToday || $gamePlay->is_personal_best) {
            return app(\App\Services\XpService::class)->awardForGamePlay($this, $gamePlay);
        }

        return 0;
    }

    public function getActiveMerchUnlocks(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->merchUnlocks()->active()->with('merchUnlockRule')->get();
    }

    public function hasGameAccess(Game $game): bool
    {
        // Check if game is basic tier (always accessible)
        if ($game->tier === Game::TIER_BASIC) {
            return true;
        }

        // Check merch unlocks
        $merchUnlocks = $this->getActiveMerchUnlocks();
        foreach ($merchUnlocks as $unlock) {
            $rule = $unlock->merchUnlockRule;
            if ($rule->game_id === $game->id) {
                return true;
            }
            if ($rule->game_pack_id) {
                $pack = GamePack::with('games')->find($rule->game_pack_id);
                if ($pack && $pack->games()->where('games.id', $game->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getFeaturedBadges(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->userBadges()
            ->where('is_featured', true)
            ->with('badge')
            ->limit(3)
            ->get();
    }

    public function getRecentBadges(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->userBadges()
            ->with('badge')
            ->orderBy('earned_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getAvailableRewards(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->gameRewards()
            ->available()
            ->with(['business', 'promotion'])
            ->orderBy('expires_at')
            ->get();
    }
}
