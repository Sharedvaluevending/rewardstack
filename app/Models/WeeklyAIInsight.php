<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyAIInsight extends Model
{
    protected $table = 'weekly_ai_insights';

    protected $fillable = [
        'business_id',
        'type',
        'period',
        'payload',
        'generated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'generated_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the latest insight for a business, type, and period.
     */
    public static function getLatest(int $businessId, string $type, int $period): ?self
    {
        return static::where('business_id', $businessId)
            ->where('type', $type)
            ->where('period', $period)
            ->orderByDesc('generated_at')
            ->first();
    }

    /**
     * Store or update the weekly insight (upsert by business_id, type, period).
     */
    public static function store(int $businessId, string $type, int $period, array $payload): self
    {
        return static::updateOrCreate(
            [
                'business_id' => $businessId,
                'type' => $type,
                'period' => $period,
            ],
            [
                'payload' => $payload,
                'generated_at' => now(),
            ]
        );
    }
}
