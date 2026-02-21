<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductVersion;
use App\Services\ProductVersioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActivateScheduledVersions extends Command
{
    protected $signature = 'versions:activate-scheduled';

    protected $description = 'Activate product versions that are scheduled and due';

    public function handle(ProductVersioningService $versioningService): int
    {
        $versions = ProductVersion::scheduledAndDue()
            ->with('product')
            ->get();

        if ($versions->isEmpty()) {
            return self::SUCCESS;
        }

        $activated = 0;
        $failed = 0;

        foreach ($versions as $version) {
            try {
                $versioningService->activateVersion($version);
                $activated++;

                Log::info('Scheduled version activated', [
                    'version_id' => $version->id,
                    'product_id' => $version->product_id,
                    'version_number' => $version->version_number,
                ]);
            } catch (\Throwable $e) {
                $failed++;

                Log::error('Failed to activate scheduled version', [
                    'version_id' => $version->id,
                    'product_id' => $version->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Activated: {$activated}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
