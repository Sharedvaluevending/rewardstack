<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchReferralAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'merch_tag_id',
        'owner_user_id',
        'reward_id',
        'user_promo_token_id',
        'redemptions_count_snapshot',
        'awarded_at',
    ];

    protected $casts = [
        'redemptions_count_snapshot' => 'integer',
        'awarded_at' => 'datetime',
    ];

    public function merchTag()
    {
        return $this->belongsTo(MerchTag::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reward()
    {
        return $this->belongsTo(MerchReferralReward::class, 'reward_id');
    }

    public function userPromoToken()
    {
        return $this->belongsTo(UserPromoToken::class, 'user_promo_token_id');
    }
}
