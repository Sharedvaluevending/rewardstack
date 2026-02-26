<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmAiRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'type',
        'payload',
        'status',
        'applied_at',
        'dismissed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'applied_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}

