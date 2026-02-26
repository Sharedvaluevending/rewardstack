<?php

namespace App\Models;

use App\Models\GameReward;
use App\Models\GamePlay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QRCodeGame extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'qr_code_games';

    protected $fillable = [
        'qr_code_id',
        'game_id',
        'business_id',
        'leaderboard_id',
        'is_active',
        'promotion_id',
        'win_mode',
        'win_threshold_score',
        'win_probability',
        'prize_config',
        'tier_rewards',
        'score_tiers',
        'active_days',
        'active_start_time',
        'active_end_time',
        'total_wins',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'win_threshold_score' => 'integer',
        'win_probability' => 'integer',
        'prize_config' => 'array',
        'tier_rewards' => 'array',
        'score_tiers' => 'array',
        'active_days' => 'array',
        'active_start_time' => 'datetime:H:i',
        'active_end_time' => 'datetime:H:i',
        'total_wins' => 'integer',
    ];

    const WIN_MODE_ALWAYS = 'always';
    const WIN_MODE_SCORE = 'score';
    const WIN_MODE_TIME = 'time';
    const WIN_MODE_RANDOM = 'random';
    const WIN_MODE_LEADERBOARD = 'leaderboard';
    const WIN_MODE_SKILL = 'skill';
    const WIN_MODE_TIERED = 'tiered';

    // Relationships
    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id');
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function leaderboard()
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /**
     * Get the actual total wins count from GamePlay records
     * This ensures accuracy even if the column wasn't updated properly
     */
    public function getActualTotalWinsAttribute(): int
    {
        return GamePlay::where('qr_code_id', $this->qr_code_id)
            ->where('game_id', $this->game_id)
            ->where('result', GamePlay::RESULT_WIN)
            ->count();
    }

    /**
     * Sync total_wins with actual GamePlay records
     * Useful for backfilling or correcting the count
     */
    public function syncTotalWins(): void
    {
        $actualWins = $this->getActualTotalWinsAttribute();
        $this->update(['total_wins' => $actualWins]);
    }

    // Helpers
    public function isAvailableNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        // Check day of week
        if ($this->active_days && !empty($this->active_days)) {
            $today = strtolower($now->format('l'));
            if (!in_array($today, $this->active_days)) {
                return false;
            }
        }

        // Check time of day
        if ($this->active_start_time && $this->active_end_time) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $this->active_start_time || $currentTime > $this->active_end_time) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the attached prize promotion(s) have all expired.
     * Returns true if every promotion tied to this game config has ended.
     */
    public function isPrizeExpired(): bool
    {
        // Check the main promotion
        if ($this->promotion_id) {
            $promo = $this->relationLoaded('promotion') ? $this->promotion : Promotion::find($this->promotion_id);
            if ($promo && !$promo->isCurrentlyValid()) {
                return true;
            }
        }

        // For tiered mode, check if ALL tier promotions are expired
        if ($this->win_mode === self::WIN_MODE_TIERED && $this->tier_rewards) {
            $tierPromoIds = array_filter(array_values($this->tier_rewards));
            if (!empty($tierPromoIds)) {
                $validCount = Promotion::whereIn('id', $tierPromoIds)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->count();
                if ($validCount === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function determineReward(int $score, ?int $timeSeconds = null, ?int $leaderboardPosition = null, bool $trackWin = true): array
    {
        $result = [
            'won' => false,
            'tier' => null,
            'promotion_id' => null,
            'reason' => null,
        ];

        $config = $this->prize_config ?? [];

        // Fallback: if win_mode is not set but a promotion is attached, treat it as a simple play-to-win.
        $winMode = $this->win_mode ?: self::WIN_MODE_ALWAYS;

        // Check limits
        if ($this->hasReachedLimits()) {
            $result['reason'] = 'Prize limit reached';
            return $result;
        }

        // Check if the attached promotion has expired before awarding.
        // For tiered mode, individual tier promotions are checked below.
        if ($winMode !== self::WIN_MODE_TIERED && $this->promotion_id) {
            $promo = $this->relationLoaded('promotion') ? $this->promotion : Promotion::find($this->promotion_id);
            if ($promo && !$promo->isCurrentlyValid()) {
                $result['reason'] = 'Prize promotion has expired';
                return $result;
            }
        }

        switch ($winMode) {
            case self::WIN_MODE_ALWAYS:
                $result['won'] = true;
                $result['promotion_id'] = $this->promotion_id;
                $result['reason'] = 'Participation prize';
                break;

            case self::WIN_MODE_SCORE:
            case self::WIN_MODE_SKILL:
                $minScore = $config['min_score'] ?? $this->win_threshold_score ?? 0;
                if ($score >= $minScore) {
                    $result['won'] = true;
                    $result['promotion_id'] = $this->promotion_id;
                    $result['reason'] = "Scored {$score} (min: {$minScore})";
                }
                break;

            case self::WIN_MODE_TIME:
                $maxTime = $config['max_time'] ?? 60;
                if ($timeSeconds !== null && $timeSeconds <= $maxTime) {
                    $result['won'] = true;
                    $result['promotion_id'] = $this->promotion_id;
                    $result['reason'] = "Completed in {$timeSeconds}s (max: {$maxTime}s)";
                }
                break;

            case self::WIN_MODE_RANDOM:
                $probability = $config['win_probability'] ?? $this->win_probability ?? 100;
                // Ensure probability is treated as an integer
                $probability = (int) $probability;
                $roll = rand(1, 100);
                
                if ($roll <= $probability) {
                    $result['won'] = true;
                    $result['promotion_id'] = $this->promotion_id;
                    $result['reason'] = "Lucky roll! ({$roll}/{$probability})";
                }
                break;

            case self::WIN_MODE_LEADERBOARD:
                $maxPosition = $config['leaderboard_position'] ?? 3;
                if ($leaderboardPosition !== null && $leaderboardPosition <= $maxPosition) {
                    $result['won'] = true;
                    $result['promotion_id'] = $this->promotion_id;
                    $result['reason'] = "Leaderboard position #{$leaderboardPosition}";
                }
                break;

            case self::WIN_MODE_TIERED:
                if ($this->score_tiers && $this->tier_rewards) {
                    foreach (['gold', 'silver', 'bronze'] as $tier) {
                        if (isset($this->score_tiers[$tier]) && $score >= $this->score_tiers[$tier]) {
                            $promotionId = $this->tier_rewards[$tier] ?? null;
                            if ($promotionId) {
                                // Check if this tier's promotion is still valid
                                $tierPromo = Promotion::find($promotionId);
                                if ($tierPromo && !$tierPromo->isCurrentlyValid()) {
                                    $result['reason'] = ucfirst($tier) . " tier prize has expired";
                                    continue; // Try the next lower tier
                                }
                                $result['won'] = true;
                                $result['tier'] = $tier;
                                $result['promotion_id'] = $promotionId;
                                $result['reason'] = ucfirst($tier) . " tier achieved";
                                break;
                            }
                        }
                    }
                }
                break;
        }

        // Track win (optional: fun-only mode should not affect analytics)
        if ($trackWin && $result['won']) {
            $this->increment('total_wins');
        }

        return $result;
    }

    protected function hasReachedLimits(): bool
    {
        $config = $this->prize_config ?? [];

        // Check daily limit
        if (isset($config['daily_limit'])) {
            $todayWins = GamePlay::where('qr_code_id', $this->qr_code_id)
                ->where('game_id', $this->game_id)
                ->where('result', GamePlay::RESULT_WIN)
                ->whereDate('created_at', today())
                ->count();
            if ($todayWins >= $config['daily_limit']) {
                return true;
            }
        }

        // Check total limit
        if (isset($config['total_limit'])) {
            $totalWins = GamePlay::where('qr_code_id', $this->qr_code_id)
                ->where('game_id', $this->game_id)
                ->where('result', GamePlay::RESULT_WIN)
                ->count();
            if ($totalWins >= $config['total_limit']) return true;
        }

        return false;
    }
}
