<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'game_id',
        'is_enabled',
        'schedule',
        'reward_config',
        'max_plays_per_day',
        'max_plays_per_week',
        'cooldown_minutes',
        'custom_branding',
        'leaderboard_enabled',
        'staff_can_play',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'schedule' => 'array',
        'reward_config' => 'array',
        'max_plays_per_day' => 'integer',
        'max_plays_per_week' => 'integer',
        'cooldown_minutes' => 'integer',
        'custom_branding' => 'array',
        'leaderboard_enabled' => 'boolean',
        'staff_can_play' => 'boolean',
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

    // Helpers
    public function isAvailableNow(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        if (!$this->schedule) {
            return true;
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));

        if (!isset($this->schedule[$dayOfWeek])) {
            return false;
        }

        $daySchedule = $this->schedule[$dayOfWeek];
        
        if (isset($daySchedule['start']) && isset($daySchedule['end'])) {
            $start = \Carbon\Carbon::parse($daySchedule['start']);
            $end = \Carbon\Carbon::parse($daySchedule['end']);
            return $now->between($start, $end);
        }

        return true;
    }

    public function canUserPlay(User $user): array
    {
        $result = ['allowed' => true, 'reason' => null, 'cooldown_remaining' => 0];

        // Check daily limit
        if ($this->max_plays_per_day) {
            $todayPlays = GamePlay::where('business_id', $this->business_id)
                ->where('game_id', $this->game_id)
                ->where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->count();

            if ($todayPlays >= $this->max_plays_per_day) {
                return ['allowed' => false, 'reason' => 'Daily play limit reached', 'cooldown_remaining' => 0];
            }
        }

        // Check weekly limit
        if ($this->max_plays_per_week) {
            $weekPlays = GamePlay::where('business_id', $this->business_id)
                ->where('game_id', $this->game_id)
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();

            if ($weekPlays >= $this->max_plays_per_week) {
                return ['allowed' => false, 'reason' => 'Weekly play limit reached', 'cooldown_remaining' => 0];
            }
        }

        // Check cooldown
        if ($this->cooldown_minutes) {
            $lastPlay = GamePlay::where('business_id', $this->business_id)
                ->where('game_id', $this->game_id)
                ->where('user_id', $user->id)
                ->latest()
                ->first();

            if ($lastPlay) {
                $cooldownEnds = $lastPlay->created_at->addMinutes($this->cooldown_minutes);
                if (now()->lt($cooldownEnds)) {
                    return [
                        'allowed' => false,
                        'reason' => 'Cooldown active',
                        'cooldown_remaining' => now()->diffInSeconds($cooldownEnds),
                    ];
                }
            }
        }

        return $result;
    }
}

