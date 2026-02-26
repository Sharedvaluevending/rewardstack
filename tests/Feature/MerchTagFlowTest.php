<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MerchTag;
use App\Models\QRCode;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchTagFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function merch_tag_scan_redirects_and_sets_session()
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'static',
            'destination_url' => 'https://example.com',
        ]);

        $tag = MerchTag::create([
            'code' => 'TESTTAG1',
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'is_active' => true,
        ]);

        $response = $this->get("/m/{$tag->code}");

        $response->assertRedirect(route('merch.claim.view', $tag->code));
        $response->assertSessionMissing('merch_tag_id');
    }

    /** @test */
    public function merch_tag_id_is_attached_to_scan()
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'static',
            'destination_url' => 'https://example.com',
        ]);

        $tag = MerchTag::create([
            'code' => 'TESTTAG2',
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'is_active' => true,
        ]);

        $response = $this->withSession(['merch_tag_id' => $tag->id])
            ->get("/s/{$qrCode->code}");

        $response->assertRedirect('https://example.com');

        $scan = Scan::latest('id')->first();
        $this->assertNotNull($scan);
        $this->assertEquals($tag->id, $scan->merch_tag_id);
    }
}
