<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Interne JSON-RPC-Fehler mit Code (z.B. -32601 Method not found). */
class McpException extends \RuntimeException
{
    public function __construct(int $code, string $message)
    {
        parent::__construct($message, $code);
    }
}
