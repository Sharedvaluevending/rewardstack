<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserXpEvent extends Model
{
    use HasFactory;

    protected $table = 'user_xp_events';

    protected $fillable = [
        'user_id',
        'source',
        'amount',
        'promotion_id',
        'qr_code_id',
        'game_id',
        'badge_id',
        'context',
        'occurred_on',
    ];

    protected $casts = [
        'amount' => 'integer',
        'promotion_id' => 'integer',
        'qr_code_id' => 'integer',
        'game_id' => 'integer',
        'badge_id' => 'integer',
        'context' => 'array',
        'occurred_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

