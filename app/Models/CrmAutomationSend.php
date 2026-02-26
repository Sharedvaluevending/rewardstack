<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmAutomationSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'automation_id',
        'user_id',
        'dedupe_key',
        'last_sent_at',
        'send_count',
    ];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'send_count' => 'integer',
    ];
}

