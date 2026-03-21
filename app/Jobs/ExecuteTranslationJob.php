<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ConnectorConnection;
use App\Models\TranslationJob;
use App\Services\Connectors\DeepL\DeepLTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public int $timeout = 1800;

    private const BATCH_SIZE = 50;
    private const BATCH_DELAY_SECONDS = 2;
    private const MAX_RETRIES_PER_BATCH = 3;

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
        $batches = $pendingItems->chunk(self::BATCH_SIZE);
        $batchIndex = 0;

        foreach ($batches as $batch) {
            // Delay between batches to avoid rate limiting
            if ($batchIndex > 0) {
                sleep(self::BATCH_DELAY_SECONDS);
            }
            $batchIndex++;

            $texts = $batch->pluck('source_text')->toArray();

            $results = $this->translateWithRetry(
                $deeplService,
                $apiKey,
                $texts,
                strtoupper($job->source_language),
                strtoupper($job->target_language),
                $job->id,
            );

            if ($results !== null) {
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
            } else {
                // Entire batch failed after retries
                foreach ($batch as $item) {
                    if ($item->status === 'pending') {
                        $item->update([
                            'status' => 'failed',
                            'error_message' => 'DeepL API nicht erreichbar nach mehreren Versuchen.',
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

    /**
     * Translate texts with retry and exponential backoff for 429 errors.
     */
    private function translateWithRetry(
        DeepLTranslationService $deeplService,
        string $apiKey,
        array $texts,
        string $sourceLang,
        string $targetLang,
        string $jobId,
    ): ?array {
        $delay = 5; // initial retry delay in seconds

        for ($attempt = 1; $attempt <= self::MAX_RETRIES_PER_BATCH; $attempt++) {
            try {
                return $deeplService->translateTexts($apiKey, $texts, $sourceLang, $targetLang);
            } catch (\Throwable $e) {
                $is429 = str_contains($e->getMessage(), '429');

                Log::warning('Translation batch attempt failed', [
                    'job_id' => $jobId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'is_rate_limit' => $is429,
                ]);

                if ($is429 && $attempt < self::MAX_RETRIES_PER_BATCH) {
                    sleep($delay);
                    $delay *= 2; // exponential backoff: 5s, 10s, 20s
                    continue;
                }

                // Non-429 error or last attempt - give up on this batch
                return null;
            }
        }

        return null;
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
