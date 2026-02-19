<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Ergebnis der Sheet-Validierung.
 */
readonly class ValidationResult
{
    public function __construct(
        /** @var string[] */
        public array $sheetsFound,
        /** @var array<string, array{total:int,valid:int,errors:int,creates:int,updates:int}> */
        public array $summary,
        /** @var array<array{sheet:string,row:int,column:string,field:string,value:mixed,error:string,suggestion:?string}> */
        public array $errors,
        public bool $hasErrors,
    ) {}

    /**
     * Gibt die Fehler als JSON-kompatibles Array zurück (für API-Response).
     */
    public function toArray(): array
    {
        return [
            'sheets_found' => $this->sheetsFound,
            'summary' => $this->summary,
            'errors' => $this->errors,
        ];
    }

    /**
     * Gibt die Gesamtzahl der Zeilen über alle Sheets zurück.
     */
    public function totalRows(): int
    {
        $total = 0;
        foreach ($this->summary as $sheet) {
            $total += $sheet['total'];
        }
        return $total;
    }
}
