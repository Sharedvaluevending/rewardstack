<?php

namespace Tests\Unit\Models;

use App\Models\QRCode;
use App\Models\SavedQRCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedQRCodeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->create();
        $saved = SavedQRCode::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'saved_at' => now(),
        ]);
        $this->assertTrue($saved->user->is($user));
    }

    public function test_qr_code_relationship(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->create();
        $saved = SavedQRCode::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'saved_at' => now(),
        ]);
        $this->assertTrue($saved->qrCode->is($qr));
    }

    public function test_saved_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $qr = QRCode::factory()->create();
        $saved = SavedQRCode::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'saved_at' => now(),
        ]);
        $saved->refresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $saved->saved_at);
    }
}
