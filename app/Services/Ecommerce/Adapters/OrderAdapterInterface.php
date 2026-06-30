<?php

declare(strict_types=1);

namespace App\Services\Ecommerce\Adapters;

use App\Models\EcommerceOrder;

interface OrderAdapterInterface
{
    public function getType(): string;

    // Bestellung übermitteln; gibt Protokoll-Daten zurück
    public function dispatch(EcommerceOrder $order): array;
}
