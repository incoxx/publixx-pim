<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TypesenseService;
use Illuminate\Console\Command;

class SetupTypesenseCommand extends Command
{
    protected $signature = 'typesense:setup {--force : Drop und neu erstellen}';

    protected $description = 'Typesense Collection für PDF-Seitensuche anlegen';

    public function handle(TypesenseService $typesense): int
    {
        $force = $this->option('force');

        if ($force) {
            $this->warn('Collection wird gelöscht und neu erstellt...');
        }

        try {
            $typesense->createCollection($force);
            $this->info('Typesense Collection "pdf_pages" erfolgreich angelegt.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
