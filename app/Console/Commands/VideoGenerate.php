<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class VideoGenerate extends Command
{
    protected $signature = 'pim:video-generate
        {--story= : Story-ID (z.B. 01-produktanlage)}
        {--all : Alle Stories generieren}
        {--lang=de : Sprache}
        {--force : Vorhandenes Video überschreiben}
        {--list : Alle verfügbaren Stories auflisten}
        {--preflight : Nur Systemcheck, kein Video}';

    protected $description = 'anyPIM Video-Engine: Lehr- und Demo-Videos aus YAML-Stories generieren';

    public function handle(): int
    {
        $engineDir = base_path('video-engine');
        $storiesDir = base_path('video-stories');
        $scriptsDir = $engineDir . '/scripts';

        // Prüfe ob Video-Engine existiert
        if (!is_dir($engineDir) || !file_exists($scriptsDir . '/generate-one.sh')) {
            $this->error('Video-Engine nicht gefunden. Bitte zuerst installieren:');
            $this->line('  cd video-engine && npm install');
            return self::FAILURE;
        }

        // --list: Stories auflisten (auch in Produktion erlaubt)
        if ($this->option('list')) {
            return $this->listStories($storiesDir);
        }

        // --preflight: Nur Systemcheck (auch in Produktion erlaubt)
        if ($this->option('preflight')) {
            return $this->runPreflight($engineDir, $this->option('story'));
        }

        // Produktionsschutz (nur für Video-Generierung, --force überspringt)
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Video-Generierung ist in der Produktionsumgebung nicht erlaubt.');
            $this->line('  Nutze --force um den Schutz zu überspringen.');
            return self::FAILURE;
        }

        // --all: Alle Stories
        if ($this->option('all')) {
            return $this->generateAll($scriptsDir);
        }

        // --story: Einzelne Story
        $storyId = $this->option('story');
        if (!$storyId) {
            $this->error('Bitte --story=<id>, --all, --list oder --preflight angeben.');
            $this->newLine();
            $this->listStories($storiesDir);
            return self::FAILURE;
        }

        return $this->generateOne($scriptsDir, $storyId);
    }

    private function listStories(string $storiesDir): int
    {
        $this->info('Verfügbare Stories:');
        $this->newLine();

        $stories = [];
        foreach (new \DirectoryIterator($storiesDir) as $entry) {
            if (!$entry->isDir() || $entry->isDot() || str_starts_with($entry->getFilename(), '_') || $entry->getFilename() === 'demo-assets') {
                continue;
            }

            $storyFile = $entry->getPathname() . '/story.yaml';
            if (!file_exists($storyFile)) {
                continue;
            }

            // Einfaches YAML-Parsing der Meta-Felder (ohne Symfony/YAML Dependency)
            $content = file_get_contents($storyFile);
            $meta = $this->parseYamlMeta($content);
            $stories[] = [
                'id' => $meta['id'] ?? $entry->getFilename(),
                'title' => $meta['title'] ?? '—',
                'version' => $meta['version'] ?? '—',
                'duration' => ($meta['duration_estimate'] ?? 0) . 's',
            ];
        }

        usort($stories, fn($a, $b) => $a['id'] <=> $b['id']);

        $this->table(['ID', 'Titel', 'Version', 'Dauer'], $stories);

        return self::SUCCESS;
    }

    private function runPreflight(string $engineDir, ?string $storyId): int
    {
        $this->info('Preflight-Check wird ausgeführt...');
        $this->newLine();

        $cmd = "cd {$engineDir} && npx tsx src/preflight.ts";
        if ($storyId) {
            $cmd .= ' ' . escapeshellarg($storyId);
        }

        $result = Process::timeout(60)->run($cmd);

        $this->line($result->output());
        if ($result->errorOutput()) {
            $this->line($result->errorOutput());
        }

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function generateOne(string $scriptsDir, string $storyId): int
    {
        $this->info("Story: {$storyId}");
        $this->newLine();

        $cmd = "bash {$scriptsDir}/generate-one.sh " . escapeshellarg($storyId);
        if ($this->option('force')) {
            $cmd .= ' --force';
        }

        // Live-Streaming der Ausgabe
        $result = Process::timeout(600)
            ->env($this->getVideoEnv())
            ->run($cmd, function (string $type, string $output) {
                $this->output->write($output);
            });

        $this->newLine();

        if ($result->successful()) {
            $this->info("✓ Video-Generierung abgeschlossen");
            return self::SUCCESS;
        }

        $this->error("✗ Video-Generierung fehlgeschlagen");
        return self::FAILURE;
    }

    private function generateAll(string $scriptsDir): int
    {
        $this->info('Alle Stories werden generiert...');
        $this->newLine();

        $cmd = "bash {$scriptsDir}/generate-all.sh";
        if ($this->option('force')) {
            $cmd .= ' --force';
        }

        $result = Process::timeout(1800) // 30 Minuten für alle Stories
            ->env($this->getVideoEnv())
            ->run($cmd, function (string $type, string $output) {
                $this->output->write($output);
            });

        $this->newLine();

        if ($result->successful()) {
            $this->info("✓ Alle Videos generiert");
            return self::SUCCESS;
        }

        $this->error("✗ Einige Videos fehlgeschlagen");
        return self::FAILURE;
    }

    /**
     * Einfaches Parsing der meta-Felder aus YAML ohne externe Dependency.
     */
    private function parseYamlMeta(string $content): array
    {
        $meta = [];
        $patterns = [
            'id' => '/^\s+id:\s*"?([^"\n]+)"?\s*$/m',
            'title' => '/^\s+title:\s*"?([^"\n]+)"?\s*$/m',
            'version' => '/^\s+version:\s*"?([^"\n]+)"?\s*$/m',
            'duration_estimate' => '/^\s+duration_estimate:\s*(\d+)/m',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $meta[$key] = trim($matches[1], '" ');
            }
        }

        return $meta;
    }

    private function getVideoEnv(): array
    {
        return array_filter([
            'ANYPIM_BASE_URL' => config('app.url', env('VIDEO_ENGINE_BASE_URL', 'http://localhost:8000')),
            'VIDEO_DEMO_USER_EMAIL' => env('VIDEO_DEMO_USER_EMAIL', 'demo@anypim.local'),
            'VIDEO_DEMO_USER_PASSWORD' => env('VIDEO_DEMO_USER_PASSWORD', 'demo1234'),
            'VIDEO_OUTPUT_DIR' => env('VIDEO_ENGINE_OUTPUT_DIR', 'public/videos'),
            'VIDEO_DISPLAY' => env('VIDEO_ENGINE_DISPLAY', ':99'),
            'VIDEO_FPS' => env('VIDEO_ENGINE_FPS', '30'),
            'VIDEO_QUALITY' => env('VIDEO_ENGINE_QUALITY', 'high'),
            'ELEVENLABS_API_KEY' => env('ELEVENLABS_API_KEY'),
            'ELEVENLABS_FALLBACK' => env('ELEVENLABS_FALLBACK', 'gtts'),
            'APP_ENV' => app()->environment(),
        ]);
    }
}
