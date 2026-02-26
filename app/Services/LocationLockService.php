<?php

namespace App\Services;

use App\Models\QRCode;
use App\Models\Business;

class LocationLockService
{
    /**
     * Verify location based on QR code settings
     */
    public function verify(QRCode $qrCode, array $locationData): bool
    {
        return match ($qrCode->location_lock_type) {
            'gps' => $this->verifyGPS($qrCode, $locationData),
            'wifi' => $this->verifyWiFi($qrCode, $locationData),
            'nfc' => $this->verifyNFC($qrCode, $locationData),
            'none' => true,
            default => true,
        };
    }

    /**
     * Verify GPS location is within radius of business
     */
    public function verifyGPS(QRCode $qrCode, array $locationData): bool
    {
        if (!isset($locationData['latitude']) || !isset($locationData['longitude'])) {
            return false;
        }

        $business = $qrCode->business;
        
        if (!$business || !$business->latitude || !$business->longitude) {
            // If business has no coordinates, fall back to a generous check
            return true;
        }

        $userLat = (float) $locationData['latitude'];
        $userLon = (float) $locationData['longitude'];
        $businessLat = (float) $business->latitude;
        $businessLon = (float) $business->longitude;

        // Calculate distance in meters
        $distance = $this->calculateDistance($userLat, $userLon, $businessLat, $businessLon);

        // Get radius (default 100 meters if not set)
        $radius = $qrCode->location_radius ?? 100;

        // Allow some tolerance for GPS accuracy
        $accuracy = $locationData['accuracy'] ?? 0;
        $effectiveRadius = $radius + min($accuracy, 50); // Max 50m bonus for accuracy

        return $distance <= $effectiveRadius;
    }

    /**
     * Verify WiFi SSID matches business network
     */
    public function verifyWiFi(QRCode $qrCode, array $locationData): bool
    {
        if (!$qrCode->wifi_ssid) {
            return true; // No SSID configured, allow
        }

        if (!isset($locationData['wifi_ssid'])) {
            return false; // SSID required but not provided
        }

        // Case-insensitive comparison
        return strtolower($locationData['wifi_ssid']) === strtolower($qrCode->wifi_ssid);
    }

    /**
     * Verify NFC tag ID matches
     */
    public function verifyNFC(QRCode $qrCode, array $locationData): bool
    {
        if (!$qrCode->nfc_tag_id) {
            return true; // No NFC tag configured, allow
        }

        if (!isset($locationData['nfc_tag'])) {
            return false; // NFC required but not provided
        }

        return $locationData['nfc_tag'] === $qrCode->nfc_tag_id;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in meters
     */
    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get human-readable distance string
     */
    public function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . 'm';
        }

        return round($meters / 1000, 1) . 'km';
    }

    /**
     * Check if user is within any business location
     */
    public function findNearbyBusinesses(
        float $latitude,
        float $longitude,
        int $radiusMeters = 500
    ): \Illuminate\Database\Eloquent\Collection {
        $businesses = Business::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_active', true)
            ->get();

        return $businesses->filter(function ($business) use ($latitude, $longitude, $radiusMeters) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                (float) $business->latitude,
                (float) $business->longitude
            );
            return $distance <= $radiusMeters;
        })->each(function ($business) use ($latitude, $longitude) {
            $business->distance = $this->calculateDistance(
                $latitude,
                $longitude,
                (float) $business->latitude,
                (float) $business->longitude
            );
        })->sortBy('distance');
    }

    /**
     * Get location lock requirements for a QR code
     */
    public function getRequirements(QRCode $qrCode): array
    {
        return [
            'type' => $qrCode->location_lock_type,
            'gps_required' => $qrCode->location_lock_type === 'gps',
            'wifi_required' => $qrCode->location_lock_type === 'wifi',
            'nfc_required' => $qrCode->location_lock_type === 'nfc',
            'radius' => $qrCode->location_radius,
            'wifi_ssid' => $qrCode->location_lock_type === 'wifi' ? $qrCode->wifi_ssid : null,
            'business_location' => $qrCode->location_lock_type === 'gps' ? [
                'latitude' => $qrCode->business->latitude,
                'longitude' => $qrCode->business->longitude,
                'name' => $qrCode->business->name,
            ] : null,
        ];
    }

    /**
     * Generate a verification token for NFC
     */
    public function generateNFCToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}

