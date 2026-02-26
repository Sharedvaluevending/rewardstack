<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StackablePool extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'center_latitude',
        'center_longitude',
        'radius_miles',
        'city',
        'region',
        'max_entries',
        'entry_fee',
        'requires_approval',
        'created_by',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'center_latitude' => 'decimal:8',
        'center_longitude' => 'decimal:8',
        'radius_miles' => 'integer',
        'max_entries' => 'integer',
        'entry_fee' => 'decimal:2',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // Relationships
    public function entries()
    {
        return $this->hasMany(StackableEntry::class);
    }

    public function activeEntries()
    {
        return $this->hasMany(StackableEntry::class)->active();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qrCodes()
    {
        return $this->hasMany(QRCode::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeNearby($query, $latitude, $longitude, $radiusMiles = 50)
    {
        // Haversine formula for distance calculation
        return $query->whereNotNull('center_latitude')
            ->whereNotNull('center_longitude')
            ->selectRaw("
                *, 
                (3959 * acos(
                    cos(radians(?)) * 
                    cos(radians(center_latitude)) * 
                    cos(radians(center_longitude) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(center_latitude))
                )) AS distance
            ", [$latitude, $longitude, $latitude])
            ->havingRaw('distance <= ?', [$radiusMiles])
            ->orderBy('distance');
    }

    public function scopeInCity($query, $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    // Helpers
    public function hasRoom(): bool
    {
        if ($this->max_entries === null) {
            return true;
        }
        return $this->entries()->active()->count() < $this->max_entries;
    }

    public function hasBusinessEntry(int $businessId): bool
    {
        return $this->entries()->where('business_id', $businessId)->exists();
    }

    public function getBusinessEntry(int $businessId): ?StackableEntry
    {
        return $this->entries()->where('business_id', $businessId)->first();
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Get entries sorted by distance from user's location
     */
    public function getEntriesByDistance($userLat, $userLng)
    {
        return $this->entries()
            ->active()
            ->with(['business', 'promotion'])
            ->get()
            ->map(function ($entry) use ($userLat, $userLng) {
                $business = $entry->business;
                $distance = null;
                
                if ($business->latitude && $business->longitude && $userLat && $userLng) {
                    $distance = $this->calculateDistance(
                        $userLat, $userLng,
                        $business->latitude, $business->longitude
                    );
                }
                
                $entry->distance_miles = $distance;
                return $entry;
            })
            ->sortBy('distance_miles')
            ->values();
    }

    /**
     * Calculate distance in miles using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 3959; // miles

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        return $earthRadius * $c;
    }
}
