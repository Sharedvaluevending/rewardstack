<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_code_id',
        'merch_tag_id',
        'business_id',
        'user_id',
        'scan_type',
        'session_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'latitude',
        'longitude',
        'city',
        'region',
        'country',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'scanned_at',
    ];

    // Scan categories used for analytics.
    // NOTE: scans.scan_type is a string column (not enum) so we can safely expand values over time.
    const TYPE_PROMOTION = 'promotion'; // promotion + level_exclusive
    const TYPE_QRCADE_GAME = 'qrcade_game'; // QRcade play-to-win + game challenges (non-leaderboard)
    const TYPE_QRCADE_LEADERBOARD = 'qrcade_leaderboard'; // QRcade leaderboard challenge
    const TYPE_INFO = 'info'; // static/dynamic redirect + generic destination QRs
    const TYPE_STACKABLE = 'stackable'; // stackable pool scan
    const TYPE_CROSS_PROMO = 'cross_promo'; // cross-promo scan
    const TYPE_PUNCH_CARD = 'punch_card'; // punch-card promo scan (subset of promotion)
    const TYPE_BLOCKED = 'blocked'; // limit-reached attempt (recorded for demand insight, excluded from main scan counts)

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'scanned_at' => 'datetime',
    ];

    public function qrCode()
    {
        return $this->belongsTo(QRCode::class, 'qr_code_id');
    }

    public function merchTag()
    {
        return $this->belongsTo(MerchTag::class, 'merch_tag_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function redemption()
    {
        return $this->hasOne(Redemption::class, 'scan_id');
    }
}
