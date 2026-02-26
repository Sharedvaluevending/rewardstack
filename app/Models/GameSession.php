<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GameSession extends Model
{
    use HasFactory;

    /**
     * Use the session token UUID for route model binding (e.g. /api/play/session/{session}/score).
     */
    public function getRouteKeyName(): string
    {
        return 'session_token';
    }

    protected $fillable = [
        'session_token',
        'user_id',
        'qr_code_id',
        'game_id',
        'business_id',
        'location_status',
        'latitude',
        'longitude',
        'accuracy',
        'wifi_ssid_detected',
        'nfc_tag_detected',
        'location_verified_at',
        'ip_address',
        'user_agent',
        'device_type',
        'status',
        'fun_only',
        'fun_only_reason',
        'is_practice',
        'started_at',
        'expires_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'location_verified_at' => 'datetime',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'fun_only' => 'boolean',
        'is_practice' => 'boolean',
    ];

    const LOCATION_PENDING = 'pending';
    const LOCATION_VERIFIED = 'verified';
    const LOCATION_FAILED = 'failed';
    const LOCATION_EXPIRED = 'expired';

    const STATUS_ACTIVE = 'active';
    const STATUS_PLAYING = 'playing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_ABANDONED = 'abandoned';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->session_token)) {
                $session->session_token = Str::uuid();
            }
            if (empty($session->expires_at)) {
                $session->expires_at = now()->addHour();
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id');
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function gamePlays()
    {
        return $this->hasMany(GamePlay::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    public function scopeVerified($query)
    {
        return $query->where('location_status', self::LOCATION_VERIFIED);
    }

    // Helpers
    public function isValid(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PLAYING], true)
            && $this->expires_at->isFuture()
            && $this->location_status === self::LOCATION_VERIFIED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markAsPlaying(): void
    {
        $this->update([
            'status' => self::STATUS_PLAYING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function verifyLocation(): void
    {
        $this->update([
            'location_status' => self::LOCATION_VERIFIED,
            'location_verified_at' => now(),
        ]);
    }

    public function failLocation(string $reason = null): void
    {
        $this->update([
            'location_status' => self::LOCATION_FAILED,
        ]);
    }
}

