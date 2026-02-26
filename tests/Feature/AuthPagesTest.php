<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $this->get(route('login'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_register_page_loads(): void
    {
        $this->get(route('register'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_customer_register_page_loads(): void
    {
        $this->get(route('customer.register'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/CustomerRegister'));
    }
}
