<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailUnsubscribe extends Model
{
    use HasFactory;

    protected $table = 'email_unsubscribes';

    protected $fillable = [
        'email',
        'user_id',
        'business_id',
        'reason',
        'source',
        'unsubscribed_at',
    ];

    protected $casts = [
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
}

