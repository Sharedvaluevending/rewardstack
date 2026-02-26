<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Notifications\CrossPromoExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoExpiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $crossPromo = CrossPromotion::create([
            'code' => 'EXP' . uniqid(),
            'name' => 'Expiring Chain',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'expires_at' => now()->addDays(7),
        ]);
        $notification = new CrossPromoExpiring($crossPromo);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }
}
