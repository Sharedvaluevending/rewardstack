<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaderboardEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'leaderboard_id',
        'user_id',
        'business_id',
        'score',
        'games_played',
        'wins',
        'best_score',
        'win_rate',
        'current_streak',
        'best_streak',
        'rank',
        'previous_rank',
        'rank_change',
        'period_key',
    ];

    protected $casts = [
        'score' => 'integer',
        'games_played' => 'integer',
        'wins' => 'integer',
        'best_score' => 'integer',
        'win_rate' => 'decimal:2',
        'current_streak' => 'integer',
        'best_streak' => 'integer',
        'rank' => 'integer',
        'previous_rank' => 'integer',
        'rank_change' => 'integer',
    ];

    // Relationships
    public function leaderboard()
    {
        return $this->belongsTo(Leaderboard::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function gameRewards()
    {
        return $this->hasMany(GameReward::class);
    }

    // Helpers
    public function getRankChangeIcon(): string
    {
        if ($this->rank_change > 0) {
            return '↑';
        } elseif ($this->rank_change < 0) {
            return '↓';
        }
        return '−';
    }

    public function getRankChangeClass(): string
    {
        if ($this->rank_change > 0) {
            return 'text-green-500';
        } elseif ($this->rank_change < 0) {
            return 'text-red-500';
        }
        return 'text-gray-400';
    }

    public function isTopThree(): bool
    {
        return $this->rank <= 3;
    }

    public function getMedalEmoji(): ?string
    {
        return match ($this->rank) {
            1 => '🥇',
            2 => '🥈',
            3 => '🥉',
            default => null,
        };
    }
}

