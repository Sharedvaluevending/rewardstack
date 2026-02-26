<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPromoToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'qr_code_id',
        'promotion_id',
        'business_id',
        'code',
        'qr_image_path',
        'redeemed_at',
        'redemption_id',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    protected $appends = ['qr_image_url'];

    public function getQrImageUrlAttribute(): ?string
    {
        if (!$this->qr_image_path) {
            return null;
        }
        return asset('storage/' . $this->qr_image_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id')->withTrashed();
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function redemption()
    {
        return $this->belongsTo(Redemption::class);
    }
}


