<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_loads_and_profile_update_persists_preferences(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'old@example.com',
            'preferences' => [],
        ]);

        $this->actingAs($customer)
            ->get('/portal/profile')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Profile'));

        $this->actingAs($customer)
            ->put('/portal/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'default_city' => 'Toronto',
                'default_region' => 'ON',
            ])
            ->assertStatus(302);

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new@example.com', $customer->email);
        $prefs = is_array($customer->preferences) ? $customer->preferences : [];
        $this->assertSame('Toronto', $prefs['default_city']);
        $this->assertSame('ON', $prefs['default_region']);
    }

    public function test_avatar_upload_and_remove(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Storage::fake('public');

        $customer = User::factory()->create(['role' => 'customer']);

        $upload = $this->actingAs($customer)->post('/portal/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
        ]);
        $upload->assertStatus(302);

        $customer->refresh();
        $this->assertNotEmpty($customer->avatar_path);
        Storage::disk('public')->assertExists($customer->avatar_path);

        $remove = $this->actingAs($customer)->delete('/portal/profile/avatar');
        $remove->assertStatus(302);

        $oldPath = $customer->avatar_path;
        $customer->refresh();
        $this->assertNull($customer->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
    }
}

