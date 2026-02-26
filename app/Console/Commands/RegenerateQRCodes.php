<?php

namespace App\Console\Commands;

use App\Models\QRCode;
use App\Services\QRGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RegenerateQRCodes extends Command
{
    protected $signature = 'qr-codes:regenerate {--all : Regenerate all QR codes}';
    protected $description = 'Regenerate QR code images';

    protected QRGeneratorService $qrService;

    public function __construct(QRGeneratorService $qrService)
    {
        parent::__construct();
        $this->qrService = $qrService;
    }

    public function handle()
    {
        if (!$this->option('all')) {
            $this->info('Regenerating QR codes that are missing images...');
            $qrCodes = QRCode::where(function($q) {
                $q->whereNull('design->generated_path')
                  ->orWhereRaw("JSON_EXTRACT(design, '$.generated_path') IS NULL");
            })->get();
        } else {
            $this->info('Regenerating ALL QR codes (forcing regeneration)...');
            $qrCodes = QRCode::all();
        }
        $this->info("Found {$qrCodes->count()} QR code(s) to regenerate.");

        $bar = $this->output->createProgressBar($qrCodes->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($qrCodes as $qrCode) {
            try {
                $path = $this->qrService->generateFile(
                    $qrCode->getScanUrl(),
                    $qrCode->getDesignWithDefaults()
                );

                $design = $qrCode->design ?? [];
                $design['generated_path'] = $path;
                $qrCode->updateQuietly(['design' => $design]);
                
                $success++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to regenerate QR code {$qrCode->id} ({$qrCode->name}): {$e->getMessage()}");
                $failed++;
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Completed! Success: {$success}, Failed: {$failed}");
        
        return Command::SUCCESS;
    }
}
