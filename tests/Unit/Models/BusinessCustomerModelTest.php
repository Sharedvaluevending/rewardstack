<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\BusinessCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_customer_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $bc = BusinessCustomer::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'scans_count' => 5,
        ]);

        $this->assertInstanceOf(Business::class, $bc->business);
        $this->assertEquals($business->id, $bc->business->id);
    }

    public function test_business_customer_belongs_to_user(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $bc = BusinessCustomer::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $bc->user);
        $this->assertEquals($user->id, $bc->user->id);
    }
}
