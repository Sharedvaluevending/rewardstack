<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GamePack extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'category',
        'price_monthly',
        'price_yearly',
        'price_one_time',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
        'stripe_product_id',
        'thumbnail',
        'features',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'price_one_time' => 'decimal:2',
        'features' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_ONE_TIME = 'one_time';

    const CATEGORY_STARTER = 'starter';
    const CATEGORY_PRO = 'pro';
    const CATEGORY_FAMILY = 'family';
    const CATEGORY_FITNESS = 'fitness';
    const CATEGORY_SEASONAL = 'seasonal';

    // Relationships
    public function games()
    {
        return $this->belongsToMany(Game::class, 'game_pack_game');
    }

    public function businessGamePacks()
    {
        return $this->hasMany(BusinessGamePack::class);
    }

    public function merchUnlockRules()
    {
        return $this->hasMany(MerchUnlockRule::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSubscription($query)
    {
        return $query->where('type', self::TYPE_SUBSCRIPTION);
    }

    public function scopeOneTime($query)
    {
        return $query->where('type', self::TYPE_ONE_TIME);
    }

    // Helpers
    public function getPrice(string $interval = 'monthly'): ?float
    {
        return match ($interval) {
            'monthly' => $this->price_monthly,
            'yearly' => $this->price_yearly,
            'one_time' => $this->price_one_time,
            default => $this->price_monthly,
        };
    }

    public function getGameCount(): int
    {
        return $this->games()->count();
    }
}

