<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCustomerSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'subscribed_at',
        'unsubscribed_at',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIsSubscribedAttribute(): bool
    {
        return $this->subscribed_at !== null && $this->unsubscribed_at === null;
    }
}

