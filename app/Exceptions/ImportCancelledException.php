<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Wird geworfen wenn ein Import vom Benutzer abgebrochen wird.
 */
class ImportCancelledException extends \RuntimeException
{
    public function __construct(string $importId)
    {
        parent::__construct("Import {$importId} wurde vom Benutzer abgebrochen.");
    }
}
