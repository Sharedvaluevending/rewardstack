<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QRCode extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     * Explicitly set to avoid Laravel deriving 'q_r_codes' from class name.
     */
    protected $table = 'qr_codes';

    /**
     * Get the foreign key name for relationships.
     * This ensures 'qr_code_id' is used instead of 'q_r_code_id'.
     */
    public function getForeignKey(): string
    {
        return 'qr_code_id';
    }

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'type',
        'intended_use',
        'destination_url',
        'promotion_id',
        'stackable_pool_id',
        'cross_promotion_id',
        'design',
        'placement_location',
        'placement_description',
        'total_scans',
        'unique_scans',
        'last_scanned_at',
        'is_active',
        'expires_at',
        'required_level',
        'is_level_exclusive',
    ];

    protected $casts = [
        'design' => 'array',
        'total_scans' => 'integer',
        'unique_scans' => 'integer',
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
        'expires_at' => 'datetime',
        'required_level' => 'integer',
        'is_level_exclusive' => 'boolean',
    ];

    const INTENDED_USE_PUBLIC = 'public';
    const INTENDED_USE_LEADERBOARD_PRIZE = 'leaderboard_prize';

    protected $appends = ['image_url'];

    /**
     * Scope for case-insensitive code lookup (handles trim + lowercase).
     */
    public function scopeByCode($query, string $code): void
    {
        $normalized = strtolower(trim($code));
        $query->whereRaw('LOWER(TRIM(code)) = ?', [$normalized]);
    }

    // Boot method to generate unique code
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($qrCode) {
            if (empty($qrCode->code)) {
                $qrCode->code = static::generateUniqueCode();
            }
        });

        // Clean up associated QRCodeGame records when QR code is deleted
        static::deleting(function ($qrCode) {
            $qrCode->qrCodeGames()->delete();
        });
    }

    public static function generateUniqueCode(): string
    {
        $attempts = 0;
        do {
            $code = Str::random(8);
            $attempts++;
            // Use withTrashed() since the unique DB constraint covers soft-deleted rows too
        } while (static::withTrashed()->where('code', $code)->exists() && $attempts < 10);

        // Fallback to a longer code to virtually guarantee uniqueness
        if ($attempts >= 10) {
            $code = Str::random(12);
        }

        return $code;
    }

    // Relationships
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function stackablePool()
    {
        return $this->belongsTo(StackablePool::class);
    }

    public function crossPromotion()
    {
        return $this->belongsTo(CrossPromotion::class, 'cross_promotion_id');
    }

    public function scans()
    {
        return $this->hasMany(Scan::class, 'qr_code_id', 'id');
    }

    public function savedByUsers()
    {
        return $this->hasMany(SavedQRCode::class, 'qr_code_id', 'id');
    }

    public function redemptions()
    {
        return $this->hasMany(Redemption::class, 'qr_code_id', 'id');
    }

    public function qrCodeGames()
    {
        return $this->hasMany(QRCodeGame::class, 'qr_code_id', 'id');
    }

    public function games()
    {
        return $this->belongsToMany(Game::class, 'qr_code_games', 'qr_code_id', 'game_id')
            ->withPivot(['is_active', 'promotion_id', 'win_mode', 'prize_config'])
            ->wherePivot('is_active', true);
    }

    public function merchReferralReward()
    {
        return $this->hasOne(MerchReferralReward::class, 'qr_code_id');
    }

    /**
     * Check if this QR code has any active games attached
     */
    public function hasActiveGames(): bool
    {
        return $this->qrCodeGames()->where('is_active', true)->exists();
    }

    /**
     * Get the first active game attached to this QR code
     */
    public function getActiveGame(): ?QRCodeGame
    {
        return $this->qrCodeGames()->where('is_active', true)->with('game')->first();
    }

    // Helpers
    public function getScanUrl(): string
    {
        return route('scan', $this->code);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Filter out internal QR codes that should not appear in business lists.
     */
    public function scopeVisibleToBusiness($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('intended_use')
                ->orWhere('intended_use', '!=', self::INTENDED_USE_LEADERBOARD_PRIZE);
        });
    }

    /**
     * Check if user meets the level requirement for this QR code
     */
    public function requiresLevel(?int $userLevel): bool
    {
        // If no level requirement, always allow access
        if (!$this->required_level) {
            return true;
        }

        // If user is not logged in, they don't meet the requirement
        if ($userLevel === null) {
            return false;
        }

        // User must be at or above the required level
        return $userLevel >= $this->required_level;
    }

    public function recordScan(string $sessionId = null): void
    {
        $this->increment('total_scans');

        if ($sessionId) {
            $existingScan = $this->scans()
                ->where('session_id', $sessionId)
                ->exists();

            if (!$existingScan) {
                $this->increment('unique_scans');
            }
        }

        $this->update(['last_scanned_at' => now()]);
    }

    // Get design with defaults
    public function getDesignWithDefaults(): array
    {
        $defaults = [
            'size' => 300,
            'margin' => 10,
            'error_correction' => 'H', // High error correction for better scanning reliability
            'module_shape' => 'square', // square, rounded, dots, diamond
            'finder_shape' => 'square', // square, rounded, circle
            'background_color' => '#FFFFFF',
            'background_gradient' => null, // {type, colors, angle}
            'module_color' => '#000000',
            'module_gradient' => null,
            'finder_color' => null, // Uses module_color if null
            'logo' => null, // {url, size, background}
            'text_top' => null, // {content, font, size, color}
            'text_bottom' => null,
            'border' => null, // {width, color, style, radius}
            'glow' => null, // {color, intensity, spread}
            'shadow' => null, // {color, opacity, blur, offsetX, offsetY}
            'effects' => [], // deprecated, use glow/shadow directly
        ];

        return array_merge($defaults, $this->design ?? []);
    }

    /**
     * Get the QR code image URL
     * Returns the URL if a generated path exists, null otherwise
     * Note: Skips file existence check for performance - frontend handles missing images
     */
    public function getImageUrlAttribute(): ?string
    {
        $path = $this->design['generated_path'] ?? null;
        
        if ($path) {
            return asset('storage/' . $path);
        }
        
        return null;
    }
}

