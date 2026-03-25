<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PdfDocument;
use App\Services\TypesenseService;
use Illuminate\Console\Command;

class TypesenseReindexCommand extends Command
{
    protected $signature = 'typesense:reindex
        {--fresh : Collection vorher löschen und neu erstellen}';

    protected $description = 'Alle verarbeiteten PDF-Dokumente in Typesense neu indexieren';

    public function handle(TypesenseService $typesense): int
    {
        if ($this->option('fresh')) {
            $this->warn('Collection wird gelöscht und neu erstellt...');
            try {
                $typesense->createCollection(force: true);
                $this->info('Collection "pdf_pages" neu erstellt.');
            } catch (\Throwable $e) {
                $this->error('Collection-Erstellung fehlgeschlagen: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $total = PdfDocument::where('status', 'ready')->count();

        if ($total === 0) {
            $this->info('Keine verarbeiteten PDF-Dokumente gefunden.');
            return self::SUCCESS;
        }

        $this->info("Indexiere {$total} PDF-Dokumente...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $indexed = 0;
        $errors = 0;

        PdfDocument::where('status', 'ready')
            ->with(['pages', 'media.assetFolder'])
            ->chunk(50, function ($documents) use ($typesense, &$indexed, &$errors, $bar) {
                foreach ($documents as $document) {
                    try {
                        $typesense->upsertPages($document);
                        $indexed++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->newLine();
                        $this->warn("Fehler bei {$document->id}: {$e->getMessage()}");
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Fertig: {$indexed} indexiert, {$errors} Fehler.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
