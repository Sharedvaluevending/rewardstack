<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIInsight extends Model
{
    use HasFactory;

    protected $table = 'ai_insights';

    protected $fillable = [
        'business_id',
        'type',
        'category',
        'title',
        'description',
        'data',
        'priority',
        'action_url',
        'action_text',
        'is_read',
        'is_dismissed',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_dismissed' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_dismissed', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    public function dismiss()
    {
        $this->update(['is_dismissed' => true]);
    }
}
