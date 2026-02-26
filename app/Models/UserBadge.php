<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBadge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'badge_id',
        'business_id',
        'game_play_id',
        'progress',
        'progress_max',
        'is_complete',
        'earned_at',
        'is_featured',
        'is_new',
    ];

    protected $casts = [
        'progress' => 'integer',
        'progress_max' => 'integer',
        'is_complete' => 'boolean',
        'earned_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function gamePlay()
    {
        return $this->belongsTo(GamePlay::class);
    }

    // Scopes
    public function scopeComplete($query)
    {
        return $query->where('is_complete', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeNew($query)
    {
        return $query->where('is_new', true);
    }

    // Helpers
    public function getProgressPercentage(): int
    {
        if (!$this->progress_max || $this->progress_max === 0) {
            return $this->is_complete ? 100 : 0;
        }

        return min(100, round(($this->progress / $this->progress_max) * 100));
    }

    public function markAsSeen(): void
    {
        $this->update(['is_new' => false]);
    }

    public function toggleFeatured(): void
    {
        // Max 3 featured badges
        if (!$this->is_featured) {
            $featuredCount = self::where('user_id', $this->user_id)
                ->where('is_featured', true)
                ->count();

            if ($featuredCount >= 3) {
                // Remove oldest featured
                self::where('user_id', $this->user_id)
                    ->where('is_featured', true)
                    ->oldest()
                    ->first()
                    ?->update(['is_featured' => false]);
            }
        }

        $this->update(['is_featured' => !$this->is_featured]);
    }
}

