<?php

namespace Tests\Feature\Console;

use App\Models\QRCode;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateQRCodesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_regenerates_all_qr_codes_and_sets_generated_path(): void
    {
        $qrA = QRCode::factory()->create([
            'design' => null,
        ]);
        $qrB = QRCode::factory()->create([
            'design' => ['size' => 500],
        ]);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')
                ->andReturn('qrcodes/generated.png');
        });

        $this->artisan('qr-codes:regenerate --all')
            ->assertExitCode(0);

        $qrA->refresh();
        $qrB->refresh();

        $this->assertSame('qrcodes/generated.png', $qrA->design['generated_path'] ?? null);
        $this->assertSame('qrcodes/generated.png', $qrB->design['generated_path'] ?? null);
    }

    public function test_command_continues_when_a_qr_code_fails_to_regenerate(): void
    {
        $qrFail = QRCode::factory()->create(['code' => 'FAIL1234']);
        $qrOk = QRCode::factory()->create(['code' => 'OKOK1234']);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')
                ->andReturnUsing(function (string $data, array $design) {
                    if (str_contains($data, '/s/FAIL1234')) {
                        throw new \RuntimeException('boom');
                    }
                    return 'qrcodes/generated.png';
                });
        });

        $this->artisan('qr-codes:regenerate --all')
            ->assertExitCode(0);

        $qrFail->refresh();
        $qrOk->refresh();

        $this->assertTrue(empty($qrFail->design['generated_path'] ?? null), 'Failed QR should not have generated_path set');
        $this->assertSame('qrcodes/generated.png', $qrOk->design['generated_path'] ?? null);
    }
}

