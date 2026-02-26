<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmMessageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'campaign_id',
        'crm_message_id',
        'user_id',
        'email',
        'event',
        'event_at',
        'sg_event_id',
        'sg_message_id',
        'url',
        'ip',
        'user_agent',
        'payload',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'payload' => 'array',
    ];

    public function message()
    {
        return $this->belongsTo(CrmMessage::class, 'crm_message_id');
    }
}

