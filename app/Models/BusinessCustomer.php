<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'first_seen_at',
        'last_seen_at',
        'last_redeemed_at',
        'scans_count',
        'saved_count',
        'redemptions_count',
        'game_plays_count',
        'rewards_won_count',
        'rewards_redeemed_count',
        'lifetime_savings',
        'level_at_last_seen',
        'xp_at_last_seen',
        'city',
        'region',
        'country',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_redeemed_at' => 'datetime',
        'scans_count' => 'integer',
        'saved_count' => 'integer',
        'redemptions_count' => 'integer',
        'game_plays_count' => 'integer',
        'rewards_won_count' => 'integer',
        'rewards_redeemed_count' => 'integer',
        'lifetime_savings' => 'decimal:2',
        'level_at_last_seen' => 'integer',
        'xp_at_last_seen' => 'integer',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

