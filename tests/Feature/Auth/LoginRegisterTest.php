<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_200(): void
    {
        $this->get(route('login'))
            ->assertStatus(200);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_register_page_returns_200(): void
    {
        $this->get(route('register'))
            ->assertStatus(200);
    }

    public function test_register_succeeds_with_valid_data(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Business',
            'email' => 'newbusiness@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'business_name' => 'My New Business',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newbusiness@example.com']);
    }
}
