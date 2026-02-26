<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'best_score',
        'total_score',
        'attempts_used',
        'rank',
        'is_qualified',
        'entry_paid',
        'payment_id',
        'registered_at',
        'last_attempt_at',
    ];

    protected $casts = [
        'best_score' => 'integer',
        'total_score' => 'integer',
        'attempts_used' => 'integer',
        'rank' => 'integer',
        'is_qualified' => 'boolean',
        'entry_paid' => 'boolean',
        'registered_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    // Relationships
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helpers
    public function canPlay(): bool
    {
        $tournament = $this->tournament;

        if (!$tournament->isPlayable()) {
            return false;
        }

        if ($tournament->has_entry_fee && !$this->entry_paid) {
            return false;
        }

        if ($tournament->max_attempts && $this->attempts_used >= $tournament->max_attempts) {
            return false;
        }

        return true;
    }

    public function getAttemptsRemaining(): ?int
    {
        if (!$this->tournament->max_attempts) {
            return null;
        }

        return max(0, $this->tournament->max_attempts - $this->attempts_used);
    }

    public function getPrize(): ?array
    {
        $prizes = $this->tournament->prizes ?? [];
        
        if (!$this->rank || empty($prizes)) {
            return null;
        }

        return $prizes[$this->rank] ?? null;
    }

    public function isWinner(): bool
    {
        return $this->rank === 1;
    }

    public function isPodium(): bool
    {
        return $this->rank && $this->rank <= 3;
    }
}

