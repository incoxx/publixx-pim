<?php

declare(strict_types=1);

namespace App\Services\Ecommerce\Adapters;

use App\Models\EcommerceCartType;

class AdapterFactory
{
    public static function make(EcommerceCartType $cartType): OrderAdapterInterface
    {
        $config = $cartType->adapter_config ?? [];

        return match ($cartType->adapter_type) {
            'email'    => new EmailAdapter($config),
            'rest_api' => new RestApiAdapter($config),
            default    => throw new \InvalidArgumentException(
                "Unbekannter Adapter-Typ: {$cartType->adapter_type}"
            ),
        };
    }
}
