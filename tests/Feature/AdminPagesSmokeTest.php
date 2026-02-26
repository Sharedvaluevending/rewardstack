<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_load(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index'));

        $this->actingAs($admin)
            ->get('/admin/businesses')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Businesses/Index'));

        $this->actingAs($admin)
            ->get('/admin/analytics')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Analytics/Index'));

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Settings/Index'));

        $this->actingAs($admin)
            ->get('/admin/crm')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/CRM/Index'));

        $this->actingAs($admin)
            ->get('/admin/referrals')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Referrals/Index'));

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Products/Index'));
    }
}

