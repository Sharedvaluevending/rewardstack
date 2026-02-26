<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMerchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_merch_index_returns_products_and_stats(): void
    {
        Product::create([
            'name' => 'Test Shirt',
            'slug' => 'test-shirt-' . uniqid(),
            'category' => 't-shirt',
            'base_price' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merch'));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Merch/Index')
                ->has('products')
                ->has('stats')
                ->has('recentOrders')
            );
    }

    public function test_merch_orders_index_returns_200(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.merch.orders'));

        $response->assertStatus(200);
    }

    public function test_merch_order_show_returns_200(): void
    {
        $order = Order::factory()->create([
            'type' => 'merch',
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merch.orders.show', $order));

        $response->assertStatus(200);
    }
}
