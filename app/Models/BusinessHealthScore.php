<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHealthScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'date',
        'total_scans',
        'scans_this_week',
        'active_promotions',
        'qr_codes_count',
        'customers_count',
        'last_login_at',
        'health_status',
        'health_score',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'health_score' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
