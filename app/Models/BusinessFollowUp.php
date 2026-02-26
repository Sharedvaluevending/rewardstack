<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessFollowUp extends Model
{
    protected $fillable = [
        'business_id',
        'follow_up_type',
        'due_date',
        'status',
        'completed_at',
        'notes',
        'next_action',
        'next_action_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'next_action_date' => 'date',
    ];

    public const TYPE_LABELS = [
        'day_3_checkin' => 'Day 3 Check-in',
        'week_1_review' => 'Week 1 Review',
        'week_4_testimonial' => 'Week 4 Testimonial',
        'custom' => 'Custom',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDueOrOverdue($query)
    {
        return $query->where('status', 'pending')->where('due_date', '<=', today());
    }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->follow_up_type] ?? $this->follow_up_type;
    }
}
