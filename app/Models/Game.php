<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Game extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'tier',
        'category',
        'thumbnail',
        'icon',
        'config',
        'assets',
        'difficulty_levels',
        'min_score',
        'max_score',
        'time_limit',
        'is_branded',
        'is_seasonal',
        'season_start',
        'season_end',
        'setup_fee',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'assets' => 'array',
        'difficulty_levels' => 'array',
        'min_score' => 'integer',
        'max_score' => 'integer',
        'time_limit' => 'integer',
        'is_branded' => 'boolean',
        'is_seasonal' => 'boolean',
        'season_start' => 'date',
        'season_end' => 'date',
        'setup_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the route key for the model.
     * Use slug instead of ID for route model binding
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Game type constants
    const TYPE_MEMORY_MATCH = 'memory_match';
    const TYPE_WORD_SEARCH = 'word_search';
    const TYPE_SNAKE = 'snake';
    const TYPE_TAP_COUNTER = 'tap_counter';
    const TYPE_BRICK_BREAKER = 'brick_breaker';
    const TYPE_QR_DASH = 'qr_dash';
    const TYPE_CUPCAKE_CATCHER = 'cupcake_catcher';
    const TYPE_SLICE_SAVER = 'slice_saver';
    const TYPE_BARBER_CHOP = 'barber_chop';
    const TYPE_VAPE_CLOUD_POP = 'vape_cloud_pop';
    const TYPE_GYM_REP_RACE = 'gym_rep_race';
    const TYPE_COFFEE_RUSH = 'coffee_rush';

    // Tier constants
    const TIER_BASIC = 'basic';
    const TIER_PRO = 'pro';
    const TIER_PREMIUM = 'premium';
    const TIER_SEASONAL = 'seasonal';

    public static function gameTypes(): array
    {
        return [
            self::TYPE_MEMORY_MATCH => 'Memory Match',
            self::TYPE_WORD_SEARCH => 'Word Search',
            self::TYPE_SNAKE => 'Snake',
            self::TYPE_TAP_COUNTER => 'Tap Counter',
            self::TYPE_BRICK_BREAKER => 'Brick Breaker',
            self::TYPE_QR_DASH => 'QR Dash',
            self::TYPE_CUPCAKE_CATCHER => 'Cupcake Catcher',
            self::TYPE_SLICE_SAVER => 'Slice Saver',
            self::TYPE_BARBER_CHOP => 'Barber Chop',
            self::TYPE_VAPE_CLOUD_POP => 'Vape Cloud Pop',
            self::TYPE_GYM_REP_RACE => 'Gym Rep Race',
            self::TYPE_COFFEE_RUSH => 'Coffee Rush',
        ];
    }

    public static function tiers(): array
    {
        return [
            self::TIER_BASIC => 'Starter (Free)',
            self::TIER_PRO => 'Growth ($39.99/mo)',
            self::TIER_PREMIUM => 'Pro ($79.99/mo)',
        ];
    }

    // Relationships
    public function packs()
    {
        return $this->belongsToMany(GamePack::class, 'game_pack_game');
    }

    public function businessGames()
    {
        return $this->hasMany(BusinessGame::class);
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_games')
            ->withPivot(['is_enabled', 'schedule', 'reward_config'])
            ->withTimestamps();
    }

    public function qrCodeGames()
    {
        return $this->hasMany(QRCodeGame::class);
    }

    public function gamePlays()
    {
        return $this->hasMany(GamePlay::class);
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }

    public function leaderboards()
    {
        return $this->hasMany(Leaderboard::class);
    }

    public function badges()
    {
        return $this->hasMany(Badge::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeBasic($query)
    {
        return $query->where('tier', self::TIER_BASIC);
    }

    public function scopePro($query)
    {
        return $query->where('tier', self::TIER_PRO);
    }

    public function scopeSeasonal($query)
    {
        return $query->where('is_seasonal', true)
            ->where('season_start', '<=', now())
            ->where('season_end', '>=', now());
    }

    // Helpers
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_seasonal) {
            $now = now();
            return $this->season_start <= $now && $this->season_end >= $now;
        }

        return true;
    }

    public function getComponentName(): string
    {
        return match ($this->type) {
            self::TYPE_MEMORY_MATCH => 'MemoryMatch',
            self::TYPE_WORD_SEARCH => 'WordSearch',
            self::TYPE_SNAKE => 'Snake',
            self::TYPE_TAP_COUNTER => 'TapCounter',
            self::TYPE_BRICK_BREAKER => 'BrickBreaker',
            self::TYPE_QR_DASH => 'QRDash',
            self::TYPE_CUPCAKE_CATCHER => 'CupcakeCatcher',
            self::TYPE_SLICE_SAVER => 'SliceSaver',
            self::TYPE_VAPE_CLOUD_POP => 'VapeCloudPop',
            self::TYPE_COFFEE_RUSH => 'CoffeeRush',
            default => 'GenericGame',
        };
    }
}

