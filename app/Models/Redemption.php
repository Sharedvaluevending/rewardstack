<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Redemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'qr_code_id',
        'business_id',
        'employee_id',
        'redeemed_by_user_id',
        'scan_id',
        'customer_user_id',
        'user_promo_token_id',
        'customer_identifier',
        'customer_name',
        'customer_email',
        'customer_phone',
        'original_amount',
        'discount_amount',
        'final_amount',
        'punches_added',
        'card_completed',
        'notes',
        'latitude',
        'longitude',
        'redeemed_at',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'punches_added' => 'integer',
        'card_completed' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'redeemed_at' => 'datetime',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function scan()
    {
        return $this->belongsTo(Scan::class);
    }

    public function redeemedByUser()
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }

    public function customerUser()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function userPromoToken()
    {
        return $this->belongsTo(UserPromoToken::class, 'user_promo_token_id');
    }
}

