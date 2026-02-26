<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CustomerCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_or_create_returns_existing_customer_code(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'preferences' => ['customer_code' => 'CABCD1234'],
        ]);

        $service = new CustomerCodeService();
        $code = $service->getOrCreate($user);

        $this->assertSame('CABCD1234', $code);
    }
}
