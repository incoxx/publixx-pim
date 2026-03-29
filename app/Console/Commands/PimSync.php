<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ConnectorConnection;
use App\Services\Connectors\AnyPim\AnyPimConnector;
use Illuminate\Console\Command;

/**
 * Artisan-Command für anyPIM-zu-anyPIM Synchronisation.
 *
 * Nutzung:
 *   php artisan pim:sync --connection=<uuid>
 *   php artisan pim:sync --connection=<uuid> --direction=push
 *   php artisan pim:sync --connection=<uuid> --direction=pull --delta
 *   php artisan pim:sync --all --delta
 */
class PimSync extends Command
{
    protected $signature = 'pim:sync
        {--connection= : Connection-ID (ConnectorConnection UUID)}
        {--all : Alle aktiven anyPIM-Connections synchronisieren}
        {--direction=bidirectional : Sync-Richtung (push, pull, bidirectional)}
        {--delta : Nur geänderte Produkte synchronisieren (Delta-Sync)}
        {--conflict= : Conflict-Resolution (remote_wins, local_wins, newer_wins)}
        {--dry-run : Nur anzeigen was synchronisiert würde, keine Änderungen}';

    protected $description = 'Produkte zwischen anyPIM-Instanzen synchronisieren';

    public function handle(AnyPimConnector $connector): int
    {
        $connections = $this->resolveConnections();

        if ($connections->isEmpty()) {
            $this->warn('Keine aktiven anyPIM-Connections gefunden.');
            return self::SUCCESS;
        }

        $this->info("anyPIM Sync gestartet — {$connections->count()} Connection(s)");
        $this->newLine();

        $hasErrors = false;

        foreach ($connections as $connection) {
            $this->info("▸ {$connection->name} ({$connection->id})");

            if ($this->option('dry-run')) {
                $this->dryRun($connector, $connection);
                continue;
            }

            try {
                $result = $this->executeSync($connector, $connection);
                $this->displayResult($result);
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
                $hasErrors = true;
            }

            $this->newLine();
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Connections auflösen (einzeln oder alle).
     */
    private function resolveConnections()
    {
        if ($this->option('connection')) {
            $connection = ConnectorConnection::where('id', $this->option('connection'))
                ->where('connector_type', 'anypim')
                ->where('is_active', true)
                ->first();

            if (! $connection) {
                $this->error("Connection nicht gefunden oder inaktiv: {$this->option('connection')}");
                return collect();
            }

            return collect([$connection]);
        }

        if ($this->option('all')) {
            return ConnectorConnection::where('connector_type', 'anypim')
                ->where('is_active', true)
                ->get();
        }

        $this->error('Bitte --connection=<uuid> oder --all angeben.');
        return collect();
    }

    /**
     * Sync ausführen basierend auf den Optionen.
     */
    private function executeSync(AnyPimConnector $connector, ConnectorConnection $connection): array
    {
        $direction = $this->option('direction') ?? $connection->settings['sync_direction'] ?? 'bidirectional';
        $isDelta = $this->option('delta');

        $options = [];
        if ($this->option('conflict')) {
            $options['conflict_resolution'] = $this->option('conflict');
        }

        if ($direction === 'bidirectional') {
            return $connector->syncBidirectional($connection, [], $options);
        }

        if ($direction === 'push') {
            return $isDelta
                ? $connector->deltaPush($connection, [], $options)
                : $connector->pushAll($connection, [], $options);
        }

        if ($direction === 'pull') {
            return $isDelta
                ? $connector->deltaPull($connection, [], $options)
                : $connector->pullAll($connection, [], $options);
        }

        $this->error("Unbekannte Richtung: {$direction}");
        return [];
    }

    /**
     * Dry-Run: Nur anzeigen was synchronisiert würde.
     */
    private function dryRun(AnyPimConnector $connector, ConnectorConnection $connection): void
    {
        try {
            $schema = $connector->fetchRemoteSchema($connection);
            $this->info('  Verbindung: OK');
            $this->info('  Remote Produkttypen: ' . count($schema['product_types'] ?? []));
            $this->info('  Remote Hierarchien: ' . count($schema['hierarchies'] ?? []));
            $this->info('  Remote Attribute: ' . ($schema['attribute_count'] ?? '?'));
            $this->info('  Richtung: ' . ($this->option('direction') ?? $connection->settings['sync_direction'] ?? 'bidirectional'));
            $this->info('  Delta: ' . ($this->option('delta') ? 'Ja' : 'Nein'));
        } catch (\Throwable $e) {
            $this->error("  Verbindung fehlgeschlagen: {$e->getMessage()}");
        }
    }

    /**
     * Sync-Ergebnis anzeigen.
     */
    private function displayResult(array $result): void
    {
        if (isset($result['push']) && isset($result['pull'])) {
            // Bidirektionaler Sync
            $push = $result['push'];
            $pull = $result['pull'];
            $this->info("  Push: {$push['pushed']} gesendet, {$push['skipped']} übersprungen, {$push['errors']} Fehler");
            $this->info("  Pull: {$pull['pulled']} empfangen, {$pull['skipped']} übersprungen, {$pull['errors']} Fehler");
        } elseif (isset($result['pushed'])) {
            $this->info("  Gesendet: {$result['pushed']}, Übersprungen: {$result['skipped']}, Fehler: {$result['errors']}");
        } elseif (isset($result['pulled'])) {
            $this->info("  Empfangen: {$result['pulled']}, Übersprungen: {$result['skipped']}, Fehler: {$result['errors']}");
        }
    }
}
