<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\MediaRevision;
use App\Models\ProductMediaAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaDeduplicateCommand extends Command
{
    protected $signature = 'media:deduplicate
        {--dry-run : Nur anzeigen was bereinigt würde, nichts ändern}
        {--folder= : Nur bestimmten Ordner bereinigen (UUID)}';

    protected $description = 'Duplikate bereinigen: gleicher original_file_name im selben Ordner → neuestes behalten, ältere als Revisionen archivieren';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $folderId = $this->option('folder');

        if ($dryRun) {
            $this->warn('DRY-RUN Modus — keine Änderungen.');
        }

        // Duplikate finden: gleicher original_file_name + asset_folder_id
        $query = Media::select('original_file_name', 'asset_folder_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('original_file_name')
            ->groupBy('original_file_name', 'asset_folder_id')
            ->having('cnt', '>', 1);

        if ($folderId) {
            $query->where('asset_folder_id', $folderId);
        }

        $duplicateGroups = $query->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('Keine Duplikate gefunden.');
            return self::SUCCESS;
        }

        $this->info("Gefunden: {$duplicateGroups->count()} Duplikat-Gruppen.");

        $totalMerged = 0;
        $totalArchived = 0;
        $disk = Storage::disk('public');

        foreach ($duplicateGroups as $group) {
            // Alle Medien in dieser Gruppe laden, neuestes zuerst
            $media = Media::where('original_file_name', $group->original_file_name)
                ->where('asset_folder_id', $group->asset_folder_id)
                ->orderByDesc('last_uploaded_at')
                ->orderByDesc('created_at')
                ->get();

            $keeper = $media->first();
            $duplicates = $media->slice(1);

            $this->line('');
            $this->info("  {$group->original_file_name} (Ordner: " . ($group->asset_folder_id ?: 'Stammverzeichnis') . ')');
            $this->line("    Behalte: {$keeper->id} ({$keeper->file_name})");

            foreach ($duplicates as $dup) {
                $this->line("    Duplikat: {$dup->id} ({$dup->file_name})");

                if ($dryRun) {
                    $totalArchived++;
                    continue;
                }

                // 1. Duplikat-Datei als Revision des Keepers archivieren
                $nextRevision = ($keeper->revisions()->max('revision_number') ?? 0) + 1;
                $archivePath = 'media-revisions/' . $keeper->id . '/rev-' . $nextRevision . '-' . $dup->file_name;

                if ($disk->exists($dup->file_path)) {
                    $disk->copy($dup->file_path, $archivePath);
                }

                MediaRevision::create([
                    'media_id' => $keeper->id,
                    'revision_number' => $nextRevision,
                    'file_name' => $dup->file_name,
                    'file_path' => $archivePath,
                    'original_file_name' => $dup->original_file_name,
                    'mime_type' => $dup->mime_type,
                    'file_size' => $dup->file_size,
                    'width' => $dup->width,
                    'height' => $dup->height,
                    'replaced_at' => $dup->created_at ?? now(),
                    'reason' => 'Duplikat-Bereinigung via media:deduplicate',
                ]);

                // 2. Produkt-Zuordnungen auf Keeper übertragen
                $dupAssignments = ProductMediaAssignment::where('media_id', $dup->id)->get();
                foreach ($dupAssignments as $assignment) {
                    // Nur übertragen wenn Keeper noch keine Zuordnung zum gleichen Produkt + UsageType hat
                    $exists = ProductMediaAssignment::where('media_id', $keeper->id)
                        ->where('product_id', $assignment->product_id)
                        ->where('usage_type_id', $assignment->usage_type_id)
                        ->exists();

                    if (!$exists) {
                        $assignment->update(['media_id' => $keeper->id]);
                    } else {
                        $assignment->delete();
                    }
                }

                // 3. Duplikat-Datei löschen
                if ($disk->exists($dup->file_path)) {
                    $disk->delete($dup->file_path);
                }

                // 4. Duplikat-Record löschen
                $dup->delete();

                $totalArchived++;
            }

            $totalMerged++;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY-RUN: {$totalMerged} Gruppen, {$totalArchived} Duplikate würden bereinigt.");
        } else {
            $this->info("Fertig: {$totalMerged} Gruppen bereinigt, {$totalArchived} Duplikate archiviert.");
        }

        return self::SUCCESS;
    }
}
