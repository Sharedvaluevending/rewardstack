<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Promotion;

class CrmCampaign extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'segment_id',
        'is_automation',
        'promotion_id',
        'name',
        'subject',
        'preheader',
        'content_html',
        'content_text',
        'from_name',
        'from_email',
        'reply_to',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'recipients_total',
        'sent_total',
        'delivered_total',
        'open_total',
        'click_total',
        'bounce_total',
        'spam_total',
        'unsubscribe_total',
        'failed_total',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_automation' => 'boolean',
        'promotion_id' => 'integer',
        'recipients_total' => 'integer',
        'sent_total' => 'integer',
        'delivered_total' => 'integer',
        'open_total' => 'integer',
        'click_total' => 'integer',
        'bounce_total' => 'integer',
        'spam_total' => 'integer',
        'unsubscribe_total' => 'integer',
        'failed_total' => 'integer',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function segment()
    {
        return $this->belongsTo(CrmSegment::class, 'segment_id');
    }

    public function messages()
    {
        return $this->hasMany(CrmMessage::class, 'campaign_id');
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}

