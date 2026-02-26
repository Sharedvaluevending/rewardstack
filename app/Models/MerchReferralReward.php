<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchReferralReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'qr_code_id',
        'reward_promotion_id',
        'reward_qr_code_id',
        'reward_type',
        'reward_value',
        'reward_item_value',
        'reward_description',
        'redemptions_required',
        'is_active',
    ];

    protected $casts = [
        'reward_value' => 'decimal:2',
        'reward_item_value' => 'decimal:2',
        'redemptions_required' => 'integer',
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

    public function rewardPromotion()
    {
        return $this->belongsTo(Promotion::class, 'reward_promotion_id');
    }

    public function rewardQrCode()
    {
        return $this->belongsTo(QRCode::class, 'reward_qr_code_id')->withTrashed();
    }

    public function awards()
    {
        return $this->hasMany(MerchReferralAward::class, 'reward_id');
    }
}
