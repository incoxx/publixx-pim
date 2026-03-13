<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ProductDeleted
{
    use Dispatchable;

    public function __construct(
        public string $productId,
        public readonly array $snapshot = [],
    ) {}
}
