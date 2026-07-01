<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use Illuminate\Console\Command;

/**
 * Bereinigt Datenreste in product_attribute_values: Wird ein Attribut nachträglich
 * von "sprachneutral" auf "sprachabhängig" umgestellt, bleiben bestehende language=NULL-Zeilen
 * erhalten und existieren fortan parallel zu neu angelegten sprachspezifischen Zeilen
 * (der Unique-Index behandelt NULL als eigenständigen Wert und verhindert das nicht).
 *
 * - Existiert bereits eine sprachspezifische Zeile für dieselbe Kombination, wird die
 *   language=NULL-Zeile (Duplikat) gelöscht.
 * - Existiert noch keine sprachspezifische Zeile, wird die language=NULL-Zeile auf die
 *   Standardsprache migriert, damit der Wert erhalten bleibt.
 */
class CleanupTranslatableAttributeValues extends Command
{
    protected $signature = 'attributes:cleanup-translatable-values
        {--dry-run : Zeigt Änderungen an, ohne sie zu übernehmen}
        {--default-language=de : Zielsprache für language=NULL-Zeilen ohne sprachspezifisches Pendant}';

    protected $description = 'Bereinigt product_attribute_values-Altlasten bei nachträglich auf "sprachabhängig" umgestellten Attributen';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $defaultLanguage = (string) $this->option('default-language');

        $translatableAttributeIds = Attribute::where('is_translatable', true)->pluck('id');

        if ($translatableAttributeIds->isEmpty()) {
            $this->info('Keine sprachabhängigen Attribute gefunden.');
            return self::SUCCESS;
        }

        $nullRows = ProductAttributeValue::whereIn('attribute_id', $translatableAttributeIds)
            ->whereNull('language')
            ->get();

        if ($nullRows->isEmpty()) {
            $this->info('Keine language=NULL-Zeilen bei sprachabhängigen Attributen gefunden. Nichts zu tun.');
            return self::SUCCESS;
        }

        $this->info("{$nullRows->count()} language=NULL-Zeile(n) bei sprachabhängigen Attributen gefunden.");

        $deleted = 0;
        $migrated = 0;

        foreach ($nullRows as $row) {
            $hasLanguageSibling = ProductAttributeValue::where('product_id', $row->product_id)
                ->where('attribute_id', $row->attribute_id)
                ->where('multiplied_index', $row->multiplied_index)
                ->where('output_hierarchy_id', $row->output_hierarchy_id)
                ->whereNotNull('language')
                ->exists();

            if ($hasLanguageSibling) {
                $action = $dryRun ? 'DRY-RUN: würde löschen (Duplikat)' : 'GELÖSCHT (Duplikat)';
                $this->line("  [{$action}] product={$row->product_id} attribute={$row->attribute_id} multiplied_index={$row->multiplied_index}");
                if (!$dryRun) {
                    $row->delete();
                }
                $deleted++;
            } else {
                $action = $dryRun ? "DRY-RUN: würde migrieren zu '{$defaultLanguage}'" : "MIGRIERT zu '{$defaultLanguage}'";
                $this->line("  [{$action}] product={$row->product_id} attribute={$row->attribute_id} multiplied_index={$row->multiplied_index}");
                if (!$dryRun) {
                    $row->update(['language' => $defaultLanguage]);
                }
                $migrated++;
            }
        }

        $verb = $dryRun ? 'würden' : 'wurden';
        $this->info("Fertig: {$deleted} Duplikat(e) {$verb} gelöscht, {$migrated} Einzel-Zeile(n) {$verb} auf '{$defaultLanguage}' migriert.");

        if ($dryRun) {
            $this->comment('Dies war ein Dry-Run. Zum tatsächlichen Ausführen den Befehl ohne --dry-run erneut aufrufen.');
        }

        return self::SUCCESS;
    }
}
