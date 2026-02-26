<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameAnalyticsDaily extends Model
{
    use HasFactory;

    protected $table = 'game_analytics_daily';

    protected $fillable = [
        'business_id',
        'game_id',
        'date',
        'total_sessions',
        'total_plays',
        'unique_players',
        'new_players',
        'returning_players',
        'total_play_time_seconds',
        'avg_session_duration',
        'avg_plays_per_session',
        'completion_rate',
        'total_score',
        'avg_score',
        'high_score',
        'rewards_given',
        'rewards_redeemed',
        'reward_value',
        'location_verified',
        'location_failed',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sessions' => 'integer',
        'total_plays' => 'integer',
        'unique_players' => 'integer',
        'new_players' => 'integer',
        'returning_players' => 'integer',
        'total_play_time_seconds' => 'integer',
        'avg_session_duration' => 'decimal:2',
        'avg_plays_per_session' => 'decimal:2',
        'completion_rate' => 'decimal:2',
        'total_score' => 'integer',
        'avg_score' => 'decimal:2',
        'high_score' => 'integer',
        'rewards_given' => 'integer',
        'rewards_redeemed' => 'integer',
        'reward_value' => 'decimal:2',
        'location_verified' => 'integer',
        'location_failed' => 'integer',
    ];

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    // Scopes
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeForGame($query, $gameId)
    {
        return $query->where('game_id', $gameId);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    // Helpers
    public static function aggregateForBusiness(int $businessId, string $startDate, string $endDate): array
    {
        $data = self::where('business_id', $businessId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return [
            'total_sessions' => $data->sum('total_sessions'),
            'total_plays' => $data->sum('total_plays'),
            'unique_players' => $data->sum('unique_players'),
            'total_play_time' => $data->sum('total_play_time_seconds'),
            'avg_score' => $data->avg('avg_score'),
            'high_score' => $data->max('high_score'),
            'rewards_given' => $data->sum('rewards_given'),
            'rewards_redeemed' => $data->sum('rewards_redeemed'),
            'reward_value' => $data->sum('reward_value'),
            'completion_rate' => $data->avg('completion_rate'),
        ];
    }

    public static function recordDaily(int $businessId, ?int $gameId, string $date): self
    {
        $plays = GamePlay::where('business_id', $businessId)
            ->when($gameId, fn($q) => $q->where('game_id', $gameId))
            ->whereDate('created_at', $date)
            ->get();

        $sessions = GameSession::where('business_id', $businessId)
            ->when($gameId, fn($q) => $q->where('game_id', $gameId))
            ->whereDate('created_at', $date)
            ->get();

        $rewards = GameReward::where('business_id', $businessId)
            ->whereDate('created_at', $date)
            ->get();

        return self::updateOrCreate(
            [
                'business_id' => $businessId,
                'game_id' => $gameId,
                'date' => $date,
            ],
            [
                'total_sessions' => $sessions->count(),
                'total_plays' => $plays->count(),
                'unique_players' => $plays->pluck('user_id')->unique()->count(),
                'total_play_time_seconds' => $plays->sum('duration_seconds'),
                'avg_session_duration' => $plays->avg('duration_seconds') ?? 0,
                'avg_plays_per_session' => $sessions->count() > 0 
                    ? $plays->count() / $sessions->count() 
                    : 0,
                'completion_rate' => $plays->count() > 0 
                    ? ($plays->whereNotNull('completed_at')->count() / $plays->count()) * 100 
                    : 0,
                'total_score' => $plays->sum('score'),
                'avg_score' => $plays->avg('score') ?? 0,
                'high_score' => $plays->max('score') ?? 0,
                'rewards_given' => $rewards->count(),
                'rewards_redeemed' => $rewards->where('status', 'redeemed')->count(),
                'reward_value' => $rewards->sum('discount_value'),
                'location_verified' => $sessions->where('location_status', 'verified')->count(),
                'location_failed' => $sessions->where('location_status', 'failed')->count(),
            ]
        );
    }
}

