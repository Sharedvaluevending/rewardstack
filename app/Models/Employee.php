<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'pin',
        'can_redeem',
        'can_view_analytics',
        'is_active',
    ];

    protected $casts = [
        'can_redeem' => 'boolean',
        'can_view_analytics' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'pin',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function redemptions()
    {
        return $this->hasMany(Redemption::class);
    }
}

