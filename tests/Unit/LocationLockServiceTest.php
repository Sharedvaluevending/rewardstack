<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\QRCode;
use App\Services\LocationLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationLockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LocationLockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationLockService();
    }

    public function test_verify_returns_true_when_location_lock_type_is_none(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'none';
        $qrCode->save();

        $this->assertTrue($this->service->verify($qrCode, []));
    }

    public function test_verify_wifi_returns_true_when_no_ssid_configured(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'wifi';
        $qrCode->wifi_ssid = null;
        $qrCode->save();

        $this->assertTrue($this->service->verifyWiFi($qrCode, []));
    }

    public function test_verify_wifi_returns_false_when_ssid_required_but_not_provided(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'wifi';
        $qrCode->wifi_ssid = 'OfficeNetwork';
        $qrCode->save();

        $this->assertFalse($this->service->verifyWiFi($qrCode, []));
    }

    public function test_verify_wifi_returns_true_when_ssid_matches_case_insensitive(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'wifi';
        $qrCode->wifi_ssid = 'OfficeNetwork';
        $qrCode->save();

        $this->assertTrue($this->service->verifyWiFi($qrCode, ['wifi_ssid' => 'officenetwork']));
    }

    public function test_verify_nfc_returns_true_when_no_tag_configured(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'nfc';
        $qrCode->nfc_tag_id = null;
        $qrCode->save();

        $this->assertTrue($this->service->verifyNFC($qrCode, []));
    }

    public function test_verify_nfc_returns_true_when_tag_matches(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'nfc';
        $qrCode->nfc_tag_id = 'tag-123';
        $qrCode->save();

        $this->assertTrue($this->service->verifyNFC($qrCode, ['nfc_tag' => 'tag-123']));
    }

    public function test_calculate_distance_returns_zero_for_same_point(): void
    {
        $distance = $this->service->calculateDistance(40.7128, -74.0060, 40.7128, -74.0060);
        $this->assertSame(0.0, round($distance, 2));
    }

    public function test_calculate_distance_returns_positive_for_different_points(): void
    {
        $distance = $this->service->calculateDistance(40.7128, -74.0060, 40.7228, -74.0060);
        $this->assertGreaterThan(0, $distance);
    }

    public function test_format_distance_returns_meters_when_under_1000(): void
    {
        $this->assertStringEndsWith('m', $this->service->formatDistance(500));
        $this->assertSame('500m', $this->service->formatDistance(500));
    }

    public function test_format_distance_returns_km_when_1000_or_more(): void
    {
        $result = $this->service->formatDistance(2500);
        $this->assertStringEndsWith('km', $result);
        $this->assertSame('2.5km', $result);
    }

    public function test_get_requirements_returns_structure_for_wifi_type(): void
    {
        $qrCode = QRCode::factory()->create();
        $qrCode->location_lock_type = 'wifi';
        $qrCode->wifi_ssid = 'TestSSID';
        $qrCode->save();

        $req = $this->service->getRequirements($qrCode);
        $this->assertSame('wifi', $req['type']);
        $this->assertTrue($req['wifi_required']);
        $this->assertSame('TestSSID', $req['wifi_ssid']);
    }

    public function test_generate_nfc_token_returns_32_character_hex_string(): void
    {
        $token = $this->service->generateNFCToken();
        $this->assertSame(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }
}
