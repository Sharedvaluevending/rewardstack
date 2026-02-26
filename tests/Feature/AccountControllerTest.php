<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\User;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_password_requires_authentication(): void
    {
        $this->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('login'));
    }

    public function test_update_password_succeeds_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpass123'),
        ]);
        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'oldpass123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHas('success', 'Password updated successfully.')
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_update_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpass'),
        ]);
        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'wrongpass',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasErrors('current_password');

        $user->refresh();
        $this->assertTrue(Hash::check('correctpass', $user->password));
    }

    public function test_update_password_validates_password_confirmation_and_min_length(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors(['password']);

        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
