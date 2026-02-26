<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'campaign_id',
        'user_id',
        'email',
        'status',
        'sendgrid_message_id',
        'last_event_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'unsubscribed_at',
        'last_error',
        'custom_args',
        'promo_link_token',
        'promo_link_used_at',
    ];

    protected $casts = [
        'custom_args' => 'array',
        'promo_link_used_at' => 'datetime',
        'last_event_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function campaign()
    {
        return $this->belongsTo(CrmCampaign::class, 'campaign_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(CrmMessageEvent::class, 'crm_message_id');
    }
}

