<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MediaAssignmentHistory;
use App\Models\ProductMediaAssignment;
use Illuminate\Support\Facades\Log;

/**
 * Loggt gelöschte Produkt-Media-Zuordnungen in die History-Tabelle.
 * Ermöglicht automatisches Re-Linking wenn ein Asset mit gleichem Dateinamen
 * erneut hochgeladen wird.
 */
class ProductMediaAssignmentObserver
{
    public function deleting(ProductMediaAssignment $assignment): void
    {
        try {
            $media = $assignment->media;
            if (!$media) {
                return;
            }

            MediaAssignmentHistory::create([
                'product_id' => $assignment->product_id,
                'original_file_name' => $media->original_file_name ?? $media->file_name,
                'file_name' => $media->file_name,
                'asset_folder_id' => $media->asset_folder_id,
                'usage_type_id' => $assignment->usage_type_id,
                'sort_order' => $assignment->sort_order ?? 0,
                'is_primary' => $assignment->is_primary ?? false,
                'old_media_id' => $assignment->media_id,
                'deleted_by' => auth()->id(),
                'deleted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Nicht-kritisch — History-Logging darf Löschvorgang nicht blockieren
            Log::warning('ProductMediaAssignmentObserver: History logging failed', [
                'assignment_id' => $assignment->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
