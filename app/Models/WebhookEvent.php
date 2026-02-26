<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    public const PROVIDER_SENDGRID = 'sendgrid';
    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_PRINTFUL = 'printful';

    protected $fillable = [
        'provider',
        'event_id',
        'type',
        'processed_data',
        'processed_at',
    ];

    protected $casts = [
        'processed_data' => 'array',
        'processed_at' => 'datetime',
    ];
}

