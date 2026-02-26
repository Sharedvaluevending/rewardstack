<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'business_id',
        'qr_code_id',
        'order_id',
        'order_item_id',
        'qr_image_path',
        'owner_user_id',
        'claimed_at',
        'is_active',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scans()
    {
        return $this->hasMany(Scan::class, 'merch_tag_id');
    }

    public function referralAwards()
    {
        return $this->hasMany(MerchReferralAward::class, 'merch_tag_id');
    }

    /**
     * Generate a unique 8-char code for merch tags. Used by order flow and by "add tag" testing.
     */
    public static function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
