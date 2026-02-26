<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\QRCodeGame;

class Leaderboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'business_id',
        'game_id',
        'business_ids',
        'reset_frequency',
        'reset_day',
        'current_period_start',
        'current_period_end',
        'last_reset_at',
        'max_entries',
        'score_type',
        'show_score',
        'show_games_played',
        'prize_config',
        'promotion_id',
        'qr_code_id',
        'skin',
        'sponsor_info',
        'is_premium',
        'is_active',
    ];

    protected $casts = [
        'business_ids' => 'array',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'last_reset_at' => 'datetime',
        'max_entries' => 'integer',
        'show_score' => 'boolean',
        'show_games_played' => 'boolean',
        'prize_config' => 'array',
        'sponsor_info' => 'array',
        'is_premium' => 'boolean',
        'is_active' => 'boolean',
    ];

    const TYPE_LOCATION = 'location';
    const TYPE_MULTI_LOCATION = 'multi_location';
    const TYPE_GAME_SPECIFIC = 'game_specific';
    const TYPE_GLOBAL = 'global';
    const TYPE_SEASONAL = 'seasonal';
    const TYPE_AGE_BASED = 'age_based';
    const TYPE_FAMILY_TEAM = 'family_team';

    const RESET_NEVER = 'never';
    const RESET_DAILY = 'daily';
    const RESET_WEEKLY = 'weekly';
    const RESET_MONTHLY = 'monthly';
    const RESET_SEASONAL = 'seasonal';

    const SCORE_HIGHEST = 'highest';
    const SCORE_CUMULATIVE = 'cumulative';
    const SCORE_AVERAGE = 'average';

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function entries()
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class);
    }

    /**
     * QR code games explicitly linked to this leaderboard.
     */
    public function qrCodeGames()
    {
        return $this->hasMany(QRCodeGame::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->orWhereJsonContains('business_ids', $businessId);
        });
    }

    // Helpers
    public function getCurrentPeriodKey(): string
    {
        return match ($this->reset_frequency) {
            self::RESET_DAILY => now()->format('Y-m-d'),
            self::RESET_WEEKLY => now()->format('o-\WW'),
            self::RESET_MONTHLY => now()->format('Y-m'),
            default => 'all-time',
        };
    }

    public function getTopEntries(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->entries()
            ->where('period_key', $this->getCurrentPeriodKey())
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->with('user')
            ->get();
    }

    public function getUserRank(User $user): ?int
    {
        $entry = $this->entries()
            ->where('user_id', $user->id)
            ->where('period_key', $this->getCurrentPeriodKey())
            ->first();

        return $entry?->rank;
    }

    public function updateUserScore(User $user, int $score, int $gamesPlayed = 1, bool $isWin = false): LeaderboardEntry
    {
        $periodKey = $this->getCurrentPeriodKey();

        $entry = LeaderboardEntry::firstOrNew([
            'leaderboard_id' => $this->id,
            'user_id' => $user->id,
            'period_key' => $periodKey,
        ]);

        $entry->business_id = $this->business_id;
        $entry->games_played += $gamesPlayed;

        if ($isWin) {
            $entry->wins++;
        }

        switch ($this->score_type) {
            case self::SCORE_HIGHEST:
                $entry->score = max($entry->score, $score);
                break;
            case self::SCORE_CUMULATIVE:
                $entry->score += $score;
                break;
            case self::SCORE_AVERAGE:
                $totalScore = ($entry->score * ($entry->games_played - 1)) + $score;
                $entry->score = round($totalScore / $entry->games_played);
                break;
        }

        $entry->best_score = max($entry->best_score, $score);
        $entry->win_rate = $entry->games_played > 0 
            ? round(($entry->wins / $entry->games_played) * 100, 2) 
            : 0;

        $entry->save();

        // Recalculate rankings
        $this->recalculateRankings($periodKey);

        return $entry->fresh();
    }

    public function recalculateRankings(string $periodKey): void
    {
        $entries = $this->entries()
            ->where('period_key', $periodKey)
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $rank = 1;
        foreach ($entries as $entry) {
            $previousRank = $entry->rank;
            $entry->previous_rank = $previousRank;
            $entry->rank = $rank;
            $entry->rank_change = $previousRank ? $previousRank - $rank : 0;
            $entry->save();
            $rank++;
        }
    }

    public function needsReset(): bool
    {
        if ($this->reset_frequency === self::RESET_NEVER) {
            return false;
        }

        if (!$this->current_period_end) {
            return true;
        }

        return now()->gte($this->current_period_end);
    }

    public function reset(): void
    {
        $now = now();

        // Advance by exactly one period from the current period end, rather than
        // jumping to the current calendar period. This ensures that if the scheduler
        // missed multiple periods, the prize command catches up one period at a time
        // on subsequent hourly runs instead of silently skipping intermediate periods.
        $base = $this->current_period_end ? $this->current_period_end->copy()->addSecond() : $now;

        switch ($this->reset_frequency) {
            case self::RESET_DAILY:
                $this->current_period_start = $base->copy()->startOfDay();
                $this->current_period_end = $base->copy()->endOfDay();
                break;
            case self::RESET_WEEKLY:
                $this->current_period_start = $base->copy()->startOfWeek();
                $this->current_period_end = $base->copy()->endOfWeek();
                break;
            case self::RESET_MONTHLY:
                $this->current_period_start = $base->copy()->startOfMonth();
                $this->current_period_end = $base->copy()->endOfMonth();
                break;
        }

        $this->last_reset_at = $now;
        $this->save();
    }

    /**
     * Sync this leaderboard's prize settings to QRCodeGame rows
     * for qrcade_leaderboard QR codes in the same business.
     */
    public function syncPrizeToQRCodeGames(): void
    {
        $query = QRCodeGame::where('business_id', $this->business_id)
            ->whereHas('qrCode', fn ($q) => $q->where('type', 'qrcade_leaderboard'));

        if ($this->type === self::TYPE_GAME_SPECIFIC && $this->game_id) {
            $query->where('game_id', $this->game_id);
        }

        $prizeConfig = is_array($this->prize_config) ? $this->prize_config : [];
        $prizeConfig = array_merge([
            'leaderboard_position' => (int) ($prizeConfig['leaderboard_position'] ?? 3),
        ], $prizeConfig);

        $query->update([
            'leaderboard_id' => $this->id,
            'promotion_id' => $this->promotion_id,
            'win_mode' => QRCodeGame::WIN_MODE_LEADERBOARD,
            'prize_config' => $prizeConfig,
        ]);
    }
}

