<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Models\ConnectorConnection;
use App\Models\ConnectorProductChecksum;
use App\Models\Product;

/**
 * Verwaltet Produkt-Checksums für Delta-Sync zwischen anyPIM-Instanzen.
 * Nutzt die bestehende ConnectorProductChecksum-Infrastruktur.
 */
class AnyPimChecksumService
{
    /**
     * Lokale Checksum für ein Produkt berechnen und speichern.
     */
    public function storeChecksum(ConnectorConnection $connection, Product $product, string $checksum): void
    {
        ConnectorProductChecksum::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'product_id'              => $product->id,
            ],
            [
                'checksum' => $checksum,
            ],
        );
    }

    /**
     * Gespeicherte Checksum für ein Produkt abrufen.
     */
    public function getStoredChecksum(ConnectorConnection $connection, Product $product): ?string
    {
        return ConnectorProductChecksum::where('connection_id', $connection->id)
            ->where('product_id', $product->id)
            ->value('checksum');
    }

    /**
     * Alle Checksums für eine Connection löschen (z.B. vor Full-Sync).
     */
    public function clearChecksums(ConnectorConnection $connection): int
    {
        return ConnectorProductChecksum::where('connection_id', $connection->id)->delete();
    }

    /**
     * Alle Checksums für eine Connection als Map zurückgeben.
     *
     * @return array<string, string> product_id → checksum
     */
    public function getAllChecksums(ConnectorConnection $connection): array
    {
        return ConnectorProductChecksum::where('connection_id', $connection->id)
            ->pluck('checksum', 'product_id')
            ->toArray();
    }
}
