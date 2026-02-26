<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessSettingsLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_loads_and_logo_upload_remove_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Storage::fake('public');

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->get('/business/settings')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Settings'));

        $upload = $this->actingAs($owner)->postJson('/business/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);
        $upload->assertStatus(200)->assertJson(['success' => true]);

        $business->refresh();
        $this->assertNotNull($business->logo_path);
        Storage::disk('public')->assertExists($business->logo_path);

        $remove = $this->actingAs($owner)->deleteJson('/business/settings/logo');
        $remove->assertStatus(200)->assertJson(['success' => true]);

        $oldPath = $business->logo_path;
        $business->refresh();
        $this->assertNull($business->logo_path);
        if ($oldPath) {
            Storage::disk('public')->assertMissing($oldPath);
        }
    }
}

