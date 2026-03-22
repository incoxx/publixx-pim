<?php

declare(strict_types=1);

namespace App\Services\Etim;

use App\Models\Etim\EtimClass;
use App\Models\Etim\EtimClassFeature;
use App\Models\Etim\EtimFeature;
use App\Models\Etim\EtimFeatureValue;
use App\Models\Etim\EtimGroup;
use App\Models\Etim\EtimUnit;
use App\Models\Etim\EtimValue;
use App\Models\Etim\EtimVersion;
use Illuminate\Support\Facades\DB;

/**
 * Importiert ETIM-Klassifikationsdaten aus dem offiziellen CSV-Paket.
 *
 * Erwartete CSV-Dateien im Verzeichnis:
 *   - groups.csv       (EG-Codes)
 *   - classes.csv      (EC-Codes)
 *   - features.csv     (EF-Codes)
 *   - values.csv       (EV-Codes)
 *   - units.csv        (EU-Codes)
 *   - class_features.csv   (Klasse-Feature-Zuordnung)
 *   - feature_values.csv   (Feature-Value-Zuordnung)
 */
class EtimImportService
{
    private EtimVersion $version;

    /** @var array<string, string> code → id Lookup-Caches */
    private array $groupIdMap = [];
    private array $classIdMap = [];
    private array $featureIdMap = [];
    private array $valueIdMap = [];
    private array $unitIdMap = [];

    private int $importedGroups = 0;
    private int $importedClasses = 0;
    private int $importedFeatures = 0;
    private int $importedValues = 0;
    private int $importedUnits = 0;
    private int $importedClassFeatures = 0;
    private int $importedFeatureValues = 0;

    /**
     * Importiert ETIM-Daten aus einem Verzeichnis mit CSV-Dateien.
     *
     * @return array{version_id: string, stats: array}
     */
    public function import(string $versionNumber, string $directory): array
    {
        $directory = rtrim($directory, '/');

        $this->version = EtimVersion::updateOrCreate(
            ['version' => $versionNumber],
            ['name' => "ETIM {$versionNumber}", 'imported_at' => now()]
        );

        DB::transaction(function () use ($directory) {
            // Reihenfolge beachten wegen Fremdschlüssel
            $this->importUnits($directory);
            $this->importGroups($directory);
            $this->importClasses($directory);
            $this->importFeatures($directory);
            $this->importValues($directory);
            $this->importClassFeatures($directory);
            $this->importFeatureValues($directory);
        });

        return [
            'version_id' => $this->version->id,
            'stats' => $this->getStats(),
        ];
    }

    public function getStats(): array
    {
        return [
            'groups' => $this->importedGroups,
            'classes' => $this->importedClasses,
            'features' => $this->importedFeatures,
            'values' => $this->importedValues,
            'units' => $this->importedUnits,
            'class_features' => $this->importedClassFeatures,
            'feature_values' => $this->importedFeatureValues,
        ];
    }

    private function importGroups(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'groups');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $code = $row['code'] ?? $row['Code'] ?? $row['EG_CODE'] ?? null;
            if (!$code) {
                continue;
            }

            $group = EtimGroup::updateOrCreate(
                ['etim_version_id' => $this->version->id, 'code' => $code],
                [
                    'name_de' => $row['name_de'] ?? $row['Description_DE'] ?? $row['NAME_DE'] ?? $code,
                    'name_en' => $row['name_en'] ?? $row['Description_EN'] ?? $row['NAME_EN'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? $row['SORT_ORDER'] ?? 0),
                ]
            );

            $this->groupIdMap[$code] = $group->id;
            $this->importedGroups++;
        }
    }

    private function importClasses(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'classes');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $code = $row['code'] ?? $row['Code'] ?? $row['EC_CODE'] ?? null;
            if (!$code) {
                continue;
            }

            $groupCode = $row['group_code'] ?? $row['EG_CODE'] ?? $row['GROUP_CODE'] ?? null;
            $groupId = $groupCode ? ($this->groupIdMap[$groupCode] ?? null) : null;

            $class = EtimClass::updateOrCreate(
                ['etim_version_id' => $this->version->id, 'code' => $code],
                [
                    'etim_group_id' => $groupId,
                    'name_de' => $row['name_de'] ?? $row['Description_DE'] ?? $row['NAME_DE'] ?? $code,
                    'name_en' => $row['name_en'] ?? $row['Description_EN'] ?? $row['NAME_EN'] ?? null,
                    'description_de' => $row['description_de'] ?? $row['DESCRIPTION_DE'] ?? null,
                    'description_en' => $row['description_en'] ?? $row['DESCRIPTION_EN'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? $row['SORT_ORDER'] ?? 0),
                    'status' => $row['status'] ?? 'active',
                ]
            );

            $this->classIdMap[$code] = $class->id;
            $this->importedClasses++;
        }
    }

    private function importFeatures(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'features');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $code = $row['code'] ?? $row['Code'] ?? $row['EF_CODE'] ?? null;
            if (!$code) {
                continue;
            }

            $unitCode = $row['unit_code'] ?? $row['EU_CODE'] ?? $row['UNIT_CODE'] ?? null;
            $unitId = $unitCode ? ($this->unitIdMap[$unitCode] ?? null) : null;

            $dataType = $this->normalizeDataType(
                $row['data_type'] ?? $row['TYPE'] ?? $row['DATA_TYPE'] ?? 'alphanumeric'
            );

            $feature = EtimFeature::updateOrCreate(
                ['etim_version_id' => $this->version->id, 'code' => $code],
                [
                    'name_de' => $row['name_de'] ?? $row['Description_DE'] ?? $row['NAME_DE'] ?? $code,
                    'name_en' => $row['name_en'] ?? $row['Description_EN'] ?? $row['NAME_EN'] ?? null,
                    'description_de' => $row['description_de'] ?? $row['DESCRIPTION_DE'] ?? null,
                    'description_en' => $row['description_en'] ?? $row['DESCRIPTION_EN'] ?? null,
                    'data_type' => $dataType,
                    'etim_unit_id' => $unitId,
                ]
            );

            $this->featureIdMap[$code] = $feature->id;
            $this->importedFeatures++;
        }
    }

    private function importValues(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'values');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $code = $row['code'] ?? $row['Code'] ?? $row['EV_CODE'] ?? null;
            if (!$code) {
                continue;
            }

            $value = EtimValue::updateOrCreate(
                ['etim_version_id' => $this->version->id, 'code' => $code],
                [
                    'name_de' => $row['name_de'] ?? $row['Description_DE'] ?? $row['NAME_DE'] ?? $code,
                    'name_en' => $row['name_en'] ?? $row['Description_EN'] ?? $row['NAME_EN'] ?? null,
                ]
            );

            $this->valueIdMap[$code] = $value->id;
            $this->importedValues++;
        }
    }

    private function importUnits(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'units');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $code = $row['code'] ?? $row['Code'] ?? $row['EU_CODE'] ?? null;
            if (!$code) {
                continue;
            }

            $unit = EtimUnit::updateOrCreate(
                ['etim_version_id' => $this->version->id, 'code' => $code],
                [
                    'name_de' => $row['name_de'] ?? $row['Description_DE'] ?? $row['NAME_DE'] ?? $code,
                    'name_en' => $row['name_en'] ?? $row['Description_EN'] ?? $row['NAME_EN'] ?? null,
                    'abbreviation' => $row['abbreviation'] ?? $row['ABBREVIATION'] ?? $row['SYMBOL'] ?? null,
                    'description' => $row['description'] ?? $row['DESCRIPTION'] ?? null,
                ]
            );

            $this->unitIdMap[$code] = $unit->id;
            $this->importedUnits++;
        }
    }

    private function importClassFeatures(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'class_features');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $classCode = $row['class_code'] ?? $row['EC_CODE'] ?? $row['CLASS_CODE'] ?? null;
            $featureCode = $row['feature_code'] ?? $row['EF_CODE'] ?? $row['FEATURE_CODE'] ?? null;

            $classId = $classCode ? ($this->classIdMap[$classCode] ?? null) : null;
            $featureId = $featureCode ? ($this->featureIdMap[$featureCode] ?? null) : null;

            if (!$classId || !$featureId) {
                continue;
            }

            EtimClassFeature::updateOrCreate(
                ['etim_class_id' => $classId, 'etim_feature_id' => $featureId],
                [
                    'sort_order' => (int) ($row['sort_order'] ?? $row['SORT_ORDER'] ?? 0),
                    'is_mandatory' => filter_var($row['is_mandatory'] ?? $row['MANDATORY'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ]
            );

            $this->importedClassFeatures++;
        }
    }

    private function importFeatureValues(string $directory): void
    {
        $file = $this->findCsvFile($directory, 'feature_values');
        if (!$file) {
            return;
        }

        foreach ($this->readCsv($file) as $row) {
            $featureCode = $row['feature_code'] ?? $row['EF_CODE'] ?? $row['FEATURE_CODE'] ?? null;
            $valueCode = $row['value_code'] ?? $row['EV_CODE'] ?? $row['VALUE_CODE'] ?? null;

            $featureId = $featureCode ? ($this->featureIdMap[$featureCode] ?? null) : null;
            $valueId = $valueCode ? ($this->valueIdMap[$valueCode] ?? null) : null;

            if (!$featureId || !$valueId) {
                continue;
            }

            EtimFeatureValue::updateOrCreate(
                ['etim_feature_id' => $featureId, 'etim_value_id' => $valueId],
                [
                    'sort_order' => (int) ($row['sort_order'] ?? $row['SORT_ORDER'] ?? 0),
                ]
            );

            $this->importedFeatureValues++;
        }
    }

    /**
     * Sucht eine CSV-Datei anhand eines Basisnamens im Verzeichnis.
     */
    private function findCsvFile(string $directory, string $baseName): ?string
    {
        $candidates = [
            "{$directory}/{$baseName}.csv",
            "{$directory}/{$baseName}.CSV",
            "{$directory}/" . strtoupper($baseName) . '.csv',
            "{$directory}/" . strtoupper($baseName) . '.CSV',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        // Glob-Suche als Fallback
        $matches = glob("{$directory}/*{$baseName}*.csv", GLOB_NOSORT) ?: [];
        $matchesUpper = glob("{$directory}/*{$baseName}*.CSV", GLOB_NOSORT) ?: [];
        $all = array_merge($matches, $matchesUpper);

        return $all[0] ?? null;
    }

    /**
     * Liest eine CSV-Datei und gibt Zeilen als assoziative Arrays zurück.
     *
     * @return \Generator<array<string, string>>
     */
    private function readCsv(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return;
        }

        // Delimiter erkennen (Semikolon ist bei ETIM-CSVs üblich)
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = str_contains($firstLine, ';') ? ';' : ',';

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return;
        }

        // BOM entfernen
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            yield array_combine($headers, $row);
        }

        fclose($handle);
    }

    private function normalizeDataType(string $type): string
    {
        $type = strtolower(trim($type));

        return match (true) {
            in_array($type, ['n', 'numeric', 'num', 'number']) => 'numeric',
            in_array($type, ['l', 'logical', 'log', 'boolean', 'bool']) => 'logical',
            in_array($type, ['r', 'range']) => 'range',
            default => 'alphanumeric',
        };
    }
}
