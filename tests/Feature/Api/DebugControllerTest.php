<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class DebugControllerTest extends TestCase
{
    private array $filesToCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToCleanup as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function writeLogFile(string $name, string $content): string
    {
        $path = storage_path("logs/{$name}");
        file_put_contents($path, $content);
        $this->filesToCleanup[] = $path;

        return $path;
    }

    public function test_reads_single_file_channel_like_laravel(): void
    {
        $this->writeLogFile('laravel.log', "[2026-07-02 10:00:00] local.INFO: Hello from laravel.log\n");

        $response = $this->getJson('/api/v1/debug/logs?channel=laravel');

        $response->assertOk();
        $this->assertStringContainsString('Hello from laravel.log', $response->getContent());
    }

    public function test_reads_daily_rotated_channel_by_resolving_dated_filename(): void
    {
        // Der "import"-Channel nutzt driver=>daily und schreibt z.B. nach
        // import-2026-07-02.log, niemals nach import.log direkt.
        $this->writeLogFile('import-2026-07-02.log', "[2026-07-02 09:00:00] local.INFO: Import gestartet\n");

        $response = $this->getJson('/api/v1/debug/logs?channel=import');

        $response->assertOk();
        $this->assertStringContainsString('Import gestartet', $response->getContent());
    }

    public function test_picks_most_recent_dated_file_when_several_exist(): void
    {
        $this->writeLogFile('export-2026-06-30.log', "[2026-06-30 09:00:00] local.INFO: Alter Eintrag\n");
        $this->writeLogFile('export-2026-07-02.log', "[2026-07-02 09:00:00] local.INFO: Neuer Eintrag\n");

        $response = $this->getJson('/api/v1/debug/logs?channel=export');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Neuer Eintrag', $content);
        $this->assertStringNotContainsString('Alter Eintrag', $content);
    }

    public function test_parsed_logs_resolves_daily_rotated_file_too(): void
    {
        $this->writeLogFile('artisan-cockpit-2026-07-02.log', "[2026-07-02 11:00:00] local.INFO: Befehl ausgeführt\n");

        $response = $this->getJson('/api/v1/debug/logs/parsed?channel=artisan-cockpit');

        $response->assertOk();
        $response->assertJsonPath('meta.total_entries', 1);
        $response->assertJsonPath('entries.0.message', 'Befehl ausgeführt');
    }

    public function test_clear_truncates_the_resolved_dated_file(): void
    {
        $path = $this->writeLogFile('import-2026-07-02.log', "[2026-07-02 09:00:00] local.INFO: wird gelöscht\n");

        $this->deleteJson('/api/v1/debug/logs?channel=import')->assertOk();

        $this->assertSame('', file_get_contents($path));
    }

    public function test_missing_log_file_returns_empty_placeholder_not_error(): void
    {
        $response = $this->getJson('/api/v1/debug/logs?channel=export');

        $response->assertOk();
        $this->assertStringContainsString('empty', $response->getContent());
    }

    public function test_unknown_channel_is_rejected(): void
    {
        $this->getJson('/api/v1/debug/logs?channel=not-a-real-channel')->assertStatus(400);
    }
}
