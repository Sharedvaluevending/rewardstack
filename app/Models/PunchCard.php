<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PunchCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'user_id',
        'customer_identifier',
        'punches',
        'completed_cards',
        'last_punch_at',
    ];

    protected $casts = [
        'punches' => 'integer',
        'completed_cards' => 'integer',
        'last_punch_at' => 'datetime',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}