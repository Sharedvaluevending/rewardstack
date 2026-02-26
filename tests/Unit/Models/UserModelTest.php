<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_constants_are_defined(): void
    {
        $this->assertSame('admin', User::ROLE_ADMIN);
        $this->assertSame('business', User::ROLE_BUSINESS);
        $this->assertSame('employee', User::ROLE_EMPLOYEE);
        $this->assertSame('customer', User::ROLE_CUSTOMER);
    }

    public function test_is_admin_returns_true_when_role_admin(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_when_role_customer(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->assertFalse($user->isAdmin());
    }

    public function test_is_business_returns_true_when_role_business(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $this->assertTrue($user->isBusiness());
    }

    public function test_is_employee_returns_true_when_role_employee(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $this->assertTrue($user->isEmployee());
    }

    public function test_is_customer_returns_true_when_role_customer(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $this->assertTrue($user->isCustomer());
    }

    public function test_avatar_url_is_null_when_no_avatar_path(): void
    {
        $user = User::factory()->create(['avatar_path' => null]);
        $this->assertNull($user->avatar_url);
    }

    public function test_avatar_url_returns_storage_path_when_avatar_path_set(): void
    {
        $user = User::factory()->create(['avatar_path' => 'avatars/foo.png']);
        $this->assertStringContainsString('storage/avatars/foo.png', $user->avatar_url);
    }
}
