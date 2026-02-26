<?php

namespace Tests\Unit\Models;

use App\Models\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRCodeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_key_returns_qr_code_id(): void
    {
        $qr = new QRCode();
        $this->assertSame('qr_code_id', $qr->getForeignKey());
    }

    public function test_intended_use_constants_are_defined(): void
    {
        $this->assertSame('public', QRCode::INTENDED_USE_PUBLIC);
        $this->assertSame('leaderboard_prize', QRCode::INTENDED_USE_LEADERBOARD_PRIZE);
    }

    public function test_generate_unique_code_returns_8_char_string(): void
    {
        $code = QRCode::generateUniqueCode();
        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $code);
    }

    public function test_generate_unique_code_returns_different_codes(): void
    {
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = QRCode::generateUniqueCode();
        }
        $this->assertCount(5, array_unique($codes));
    }

    public function test_is_expired_returns_false_when_no_expires_at(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'is_active' => true,
            'expires_at' => null,
        ]);
        $this->assertFalse($qr->isExpired());
    }

    public function test_is_expired_returns_true_when_expires_at_past(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
        $this->assertTrue($qr->isExpired());
    }

    public function test_is_valid_returns_true_when_active_and_not_expired(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'is_active' => true,
            'expires_at' => null,
        ]);
        $this->assertTrue($qr->isValid());
    }

    public function test_requires_level_returns_true_when_no_required_level(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'required_level' => null,
        ]);
        $this->assertTrue($qr->requiresLevel(1));
        $this->assertTrue($qr->requiresLevel(null));
    }

    public function test_requires_level_returns_false_when_user_below_level(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'required_level' => 5,
        ]);
        $this->assertFalse($qr->requiresLevel(3));
        $this->assertFalse($qr->requiresLevel(null));
    }

    public function test_requires_level_returns_true_when_user_at_or_above_level(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'required_level' => 5,
        ]);
        $this->assertTrue($qr->requiresLevel(5));
        $this->assertTrue($qr->requiresLevel(10));
    }

    public function test_get_design_with_defaults_merges_design(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'design' => ['size' => 400, 'module_color' => '#FF0000'],
        ]);
        $design = $qr->getDesignWithDefaults();
        $this->assertSame(400, $design['size']);
        $this->assertSame('#FF0000', $design['module_color']);
        $this->assertSame(10, $design['margin']);
    }

    public function test_image_url_is_null_when_no_generated_path(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'design' => null,
        ]);
        $this->assertNull($qr->image_url);
    }

    public function test_image_url_returns_storage_path_when_generated_path_set(): void
    {
        $business = \App\Models\Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $business->id,
            'code' => QRCode::generateUniqueCode(),
            'name' => 'Test',
            'type' => 'promotion',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'design' => ['generated_path' => 'qrcodes/foo.png'],
        ]);
        $this->assertStringContainsString('storage/qrcodes/foo.png', $qr->image_url);
    }
}
