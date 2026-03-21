<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ConnectorConnection;
use App\Models\TranslationJob;
use App\Services\Connectors\DeepL\DeepLTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public int $timeout = 1800;

    public function __construct(
        public TranslationJob $translationJob,
    ) {
        $this->onQueue('default');
    }

    public function handle(DeepLTranslationService $deeplService): void
    {
        $job = $this->translationJob;

        $apiKey = ConnectorConnection::where('connector_type', 'deepl')
            ->where('is_active', true)
            ->value('access_token');

        if (!$apiKey) {
            $job->update(['status' => 'failed']);
            $job->items()->where('status', 'pending')->update([
                'status' => 'failed',
                'error_message' => 'Keine aktive DeepL-Verbindung gefunden.',
            ]);

            return;
        }

        $job->update(['status' => 'in_progress']);

        $pendingItems = $job->items()->where('status', 'pending')->get();

        // Process in batches of 50
        foreach ($pendingItems->chunk(50) as $batch) {
            $texts = $batch->pluck('source_text')->toArray();

            try {
                $results = $deeplService->translateTexts(
                    $apiKey,
                    $texts,
                    strtoupper($job->source_language),
                    strtoupper($job->target_language),
                );

                foreach ($batch->values() as $i => $item) {
                    if (isset($results[$i])) {
                        $item->update([
                            'translated_text' => $results[$i],
                            'status' => 'translated',
                        ]);
                        $job->increment('translated_items');
                    } else {
                        $item->update([
                            'status' => 'failed',
                            'error_message' => 'Keine Übersetzung erhalten.',
                        ]);
                        $job->increment('failed_items');
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Translation batch failed', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);

                foreach ($batch as $item) {
                    if ($item->status === 'pending') {
                        $item->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                        $job->increment('failed_items');
                    }
                }
            }
        }

        // Determine final status from actual item counts
        $job->refresh();
        $finalStatus = $job->translated_items === 0 && $job->failed_items > 0 ? 'failed' : 'completed';
        $job->update([
            'status' => $finalStatus,
            'completed_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteTranslationJob failed completely', [
            'job_id' => $this->translationJob->id,
            'error' => $exception->getMessage(),
        ]);

        $this->translationJob->update(['status' => 'failed']);
    }
}
