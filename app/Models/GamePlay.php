<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserBadge;

class GamePlay extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'user_id',
        'game_id',
        'business_id',
        'qr_code_id',
        'score',
        'duration_seconds',
        'difficulty',
        'level_reached',
        'game_data',
        'result',
        'reward_tier',
        'is_high_score',
        'is_personal_best',
        'is_practice',
        'is_suspicious',
        'suspicious_reason',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'duration_seconds' => 'integer',
        'level_reached' => 'integer',
        'game_data' => 'array',
        'is_high_score' => 'boolean',
        'is_personal_best' => 'boolean',
        'is_practice' => 'boolean',
        'is_suspicious' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const RESULT_WIN = 'win';
    const RESULT_LOSE = 'lose';
    const RESULT_COMPLETE = 'complete';

    const TIER_GOLD = 'gold';
    const TIER_SILVER = 'silver';
    const TIER_BRONZE = 'bronze';
    const TIER_NONE = 'none';

    // Relationships
    public function gameSession()
    {
        return $this->belongsTo(GameSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id');
    }

    public function gameReward()
    {
        return $this->hasOne(GameReward::class);
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    // Scopes
    public function scopeWins($query)
    {
        return $query->where('result', self::RESULT_WIN);
    }

    public function scopeHighScores($query)
    {
        return $query->where('is_high_score', true);
    }

    public function scopeRanked($query)
    {
        return $query->where('is_practice', false);
    }

    public function scopePractice($query)
    {
        return $query->where('is_practice', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForGame($query, $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    // Helpers
    public function getDuration(): string
    {
        if (!$this->duration_seconds) {
            return '0:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function checkForHighScore(): bool
    {
        // Practice runs don't qualify for high scores
        if ($this->is_practice) {
            return false;
        }

        // Check if this is the highest score for this game at this business
        $highScore = self::where('game_id', $this->game_id)
            ->where('business_id', $this->business_id)
            ->where('id', '!=', $this->id)
            ->where('is_practice', false)
            ->max('score');

        return $this->score > ($highScore ?? 0);
    }

    public function checkForPersonalBest(): bool
    {
        if (!$this->user_id || $this->is_practice) {
            return false;
        }

        $personalBest = self::where('game_id', $this->game_id)
            ->where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->where('is_practice', false)
            ->max('score');

        return $this->score > ($personalBest ?? 0);
    }
}

