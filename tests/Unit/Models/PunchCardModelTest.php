<?php

namespace Tests\Unit\Models;

use App\Models\Promotion;
use App\Models\PunchCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PunchCardModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_punch_card_belongs_to_promotion(): void
    {
        $promo = Promotion::factory()->create();
        $card = PunchCard::create([
            'promotion_id' => $promo->id,
            'customer_identifier' => 'cust-1',
            'punches' => 2,
            'completed_cards' => 0,
        ]);

        $this->assertInstanceOf(Promotion::class, $card->promotion);
        $this->assertEquals($promo->id, $card->promotion->id);
    }

    public function test_punch_card_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $promo = Promotion::factory()->create();
        $card = PunchCard::create([
            'promotion_id' => $promo->id,
            'user_id' => $user->id,
            'customer_identifier' => 'user-' . $user->id,
            'punches' => 1,
            'completed_cards' => 0,
        ]);

        $this->assertInstanceOf(User::class, $card->user);
        $this->assertEquals($user->id, $card->user->id);
    }
}
