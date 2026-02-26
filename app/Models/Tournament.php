<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tournament extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'business_id',
        'game_id',
        'type',
        'registration_opens',
        'registration_closes',
        'starts_at',
        'ends_at',
        'max_participants',
        'max_attempts',
        'target_score',
        'challenge_user_id',
        'rules',
        'prizes',
        'has_entry_fee',
        'entry_fee',
        'sponsor_name',
        'sponsor_logo',
        'status',
        'is_featured',
    ];

    protected $casts = [
        'registration_opens' => 'datetime',
        'registration_closes' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_participants' => 'integer',
        'max_attempts' => 'integer',
        'target_score' => 'integer',
        'rules' => 'array',
        'prizes' => 'array',
        'has_entry_fee' => 'boolean',
        'entry_fee' => 'decimal:2',
        'is_featured' => 'boolean',
    ];

    const TYPE_HIGH_SCORE = 'high_score';
    const TYPE_CUMULATIVE = 'cumulative';
    const TYPE_CHALLENGE = 'challenge';
    const TYPE_BRACKET = 'bracket';
    const TYPE_TIME_ATTACK = 'time_attack';

    const STATUS_DRAFT = 'draft';
    const STATUS_UPCOMING = 'upcoming';
    const STATUS_REGISTRATION = 'registration';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected static function booted(): void
    {
        static::created(function (Tournament $tournament) {
            if ($tournament->business_id) {
                $tournament->business?->completeOnboardingStep('setup_tournaments');
            }
        });
    }

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function challengeUser()
    {
        return $this->belongsTo(User::class, 'challenge_user_id');
    }

    public function participants()
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tournament_participants')
            ->withPivot(['best_score', 'total_score', 'attempts_used', 'rank'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [self::STATUS_UPCOMING, self::STATUS_REGISTRATION]);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Helpers
    public function isRegistrationOpen(): bool
    {
        if ($this->status !== self::STATUS_REGISTRATION) {
            return false;
        }

        $now = now();

        if ($this->registration_opens && $now->lt($this->registration_opens)) {
            return false;
        }

        if ($this->registration_closes && $now->gt($this->registration_closes)) {
            return false;
        }

        if ($this->max_participants && $this->participants()->count() >= $this->max_participants) {
            return false;
        }

        return true;
    }

    public function isPlayable(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();
        return $now->between($this->starts_at, $this->ends_at);
    }

    public function register(User $user): ?TournamentParticipant
    {
        if (!$this->isRegistrationOpen()) {
            return null;
        }

        // Check if already registered
        if ($this->participants()->where('user_id', $user->id)->exists()) {
            return null;
        }

        return TournamentParticipant::create([
            'tournament_id' => $this->id,
            'user_id' => $user->id,
            'registered_at' => now(),
            'entry_paid' => !$this->has_entry_fee,
        ]);
    }

    public function getLeaderboard(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->participants()
            ->orderBy('best_score', 'desc')
            ->limit($limit)
            ->with('user')
            ->get();
    }

    public function updateParticipantScore(User $user, int $score): void
    {
        $participant = $this->participants()
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            return;
        }

        $participant->attempts_used++;
        $participant->last_attempt_at = now();

        switch ($this->type) {
            case self::TYPE_HIGH_SCORE:
            case self::TYPE_CHALLENGE:
            case self::TYPE_TIME_ATTACK:
                $participant->best_score = max($participant->best_score, $score);
                break;
            case self::TYPE_CUMULATIVE:
                $participant->total_score += $score;
                $participant->best_score = $participant->total_score;
                break;
        }

        $participant->save();

        // Recalculate rankings
        $this->recalculateRankings();
    }

    public function recalculateRankings(): void
    {
        $participants = $this->participants()
            ->orderBy('best_score', 'desc')
            ->get();

        $rank = 1;
        foreach ($participants as $participant) {
            $participant->rank = $rank++;
            $participant->is_qualified = $this->type === self::TYPE_CHALLENGE 
                ? $participant->best_score >= $this->target_score
                : true;
            $participant->save();
        }
    }
}

