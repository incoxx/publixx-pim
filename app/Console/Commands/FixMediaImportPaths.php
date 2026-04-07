<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixMediaImportPaths extends Command
{
    protected $signature = 'pim:fix-media-import-paths {--dry-run : Nur anzeigen, nicht ändern}';

    protected $description = 'Korrigiert Import-Medien: file_path-Fix + Duplikat-Zusammenführung';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        $this->info('=== Phase 1: Duplikate zusammenführen ===');
        $this->newLine();
        $this->mergeDuplicates($dryRun);

        $this->newLine();
        $this->info('=== Phase 2: Import-Pfade korrigieren ===');
        $this->newLine();
        $this->fixImportPaths($dryRun, $disk);

        if ($dryRun) {
            $this->newLine();
            $this->comment('(Dry-Run — keine Änderungen vorgenommen. Ohne --dry-run ausführen um zu fixen.)');
        }

        return self::SUCCESS;
    }

    /**
     * Findet Medien mit identischem file_name und führt sie zusammen.
     * Behält den Record mit der echten Datei (file_size > 0), verschiebt
     * alle Zuordnungen vom Duplikat zum Originial, löscht das Duplikat.
     */
    private function mergeDuplicates(bool $dryRun): void
    {
        // Finde file_names die mehrfach vorkommen
        $duplicates = DB::table('media')
            ->select('file_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('file_name')
            ->having('cnt', '>', 1)
            ->pluck('cnt', 'file_name');

        if ($duplicates->isEmpty()) {
            $this->info('Keine Duplikate gefunden.');
            return;
        }

        $this->info("Gefunden: {$duplicates->count()} Dateinamen mit Duplikaten");

        $merged = 0;
        $deleted = 0;

        foreach ($duplicates as $fileName => $count) {
            // Alle Records für diesen file_name laden
            $records = Media::where('file_name', $fileName)
                ->orderByDesc('file_size')  // Der mit echten Daten zuerst
                ->get();

            // Behalte den besten Record (größter file_size = echte Datei)
            $keeper = $records->first();
            $dupes = $records->slice(1);

            $this->line("  {$fileName} — {$count}x vorhanden, behalte ID {$keeper->id}");

            foreach ($dupes as $dupe) {
                if (!$dryRun) {
                    // Produkt-Zuordnungen: Finde product_ids die der Keeper schon hat
                    $keeperProductIds = DB::table('product_media_assignments')
                        ->where('media_id', $keeper->id)
                        ->pluck('product_id')
                        ->toArray();

                    // Übertragbare Zuordnungen (Keeper hat dieses Produkt noch nicht)
                    DB::table('product_media_assignments')
                        ->where('media_id', $dupe->id)
                        ->whereNotIn('product_id', $keeperProductIds)
                        ->update(['media_id' => $keeper->id]);

                    // Rest löschen (Keeper hatte diese Produkte schon)
                    DB::table('product_media_assignments')
                        ->where('media_id', $dupe->id)
                        ->delete();

                    // Hierarchie-Node-Zuordnungen: gleiche Logik
                    $keeperNodeIds = DB::table('hierarchy_node_media')
                        ->where('media_id', $keeper->id)
                        ->pluck('hierarchy_node_id')
                        ->toArray();

                    DB::table('hierarchy_node_media')
                        ->where('media_id', $dupe->id)
                        ->whereNotIn('hierarchy_node_id', $keeperNodeIds)
                        ->update(['media_id' => $keeper->id]);

                    DB::table('hierarchy_node_media')
                        ->where('media_id', $dupe->id)
                        ->delete();

                    // Attributwerte übertragen
                    DB::table('media_attribute_values')
                        ->where('media_id', $dupe->id)
                        ->update(['media_id' => $keeper->id]);

                    // Titel/Metadaten vom Duplikat übernehmen falls beim Keeper leer
                    $updates = [];
                    if (!$keeper->title_de && $dupe->title_de) $updates['title_de'] = $dupe->title_de;
                    if (!$keeper->title_en && $dupe->title_en) $updates['title_en'] = $dupe->title_en;
                    if (!$keeper->alt_text_de && $dupe->alt_text_de) $updates['alt_text_de'] = $dupe->alt_text_de;
                    if (!$keeper->language && $dupe->language) $updates['language'] = $dupe->language;
                    if (!$keeper->document_type && $dupe->document_type) $updates['document_type'] = $dupe->document_type;
                    if (!empty($updates)) {
                        $keeper->update($updates);
                    }

                    // Duplikat löschen
                    $dupe->delete();
                }

                $this->line("    → Duplikat {$dupe->id} gelöscht, Zuordnungen übertragen");
                $deleted++;
            }

            $merged++;
        }

        $this->newLine();
        $this->info("Zusammengeführt: {$merged} Dateinamen, {$deleted} Duplikate entfernt");
    }

    /**
     * Korrigiert file_path von "imports/..." zu "media/..." und setzt file_size.
     */
    private function fixImportPaths(bool $dryRun, $disk): void
    {
        $affected = Media::where('file_path', 'like', 'imports/%')->get();

        if ($affected->isEmpty()) {
            $this->info('Keine Medien mit imports/-Pfad gefunden.');
            return;
        }

        $this->info("Gefunden: {$affected->count()} Medien mit imports/-Pfad");

        $fixed = 0;
        $missing = 0;

        foreach ($affected as $media) {
            $correctedPath = 'media/' . $media->file_name;
            $fileExists = $disk->exists($correctedPath);

            if ($fileExists) {
                if (!$dryRun) {
                    $media->update([
                        'file_path' => $correctedPath,
                        'file_size' => $media->file_size ?: $disk->size($correctedPath),
                    ]);
                }
                $fixed++;
                $this->line("  ✓ {$media->file_name}");
            } else {
                $missing++;
                $this->warn("  ✗ {$media->file_name} — Datei fehlt auf Disk");
            }
        }

        $this->newLine();
        $this->info("Pfade korrigiert: {$fixed} | Datei fehlt: {$missing}");
    }
}
