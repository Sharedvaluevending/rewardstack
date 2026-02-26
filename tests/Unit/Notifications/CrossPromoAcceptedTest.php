<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Notifications\CrossPromoAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoAcceptedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();
        $promo1 = Promotion::factory()->create(['business_id' => $business1->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id]);
        $crossPromo = CrossPromotion::create([
            'code' => 'TEST' . uniqid(),
            'name' => 'Test Chain',
            'requested_by_business_id' => $business1->id,
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
        ]);
        $notification = new CrossPromoAccepted($crossPromo);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }
}
