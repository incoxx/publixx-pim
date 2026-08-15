<?php

declare(strict_types=1);

namespace Tms\Tests\Unit;

use Tms\Tests\TestCase;

/**
 * Der TMS bringt keine eigene config/cache.php mit, es greift also Laravels
 * Standard — und der verweist auf eine Redis-Verbindung namens 'cache'. Solange
 * die in config/database.php fehlte, starb jeder Prozess, der den Cache
 * anfasste, sofort mit "Redis connection [cache] not configured".
 *
 * Im Betrieb traf das den Queue Worker: er liest im Daemon-Betrieb bei jedem
 * Durchlauf das Restart-Signal aus dem Cache und beendete sich deshalb direkt
 * nach dem Start (Supervisor: "Exited too quickly"). Der Ingest wurde vom TMS
 * mit 202 quittiert, aber nie verarbeitet — es entstand kein einziger Begriff.
 *
 * Die Tests laufen mit CACHE_STORE=array, greifen also nicht auf Redis zu.
 * Geprueft wird die Konfiguration selbst: jede Verbindung, auf die Cache oder
 * Queue verweisen, muss in database.redis auch existieren.
 */
class RedisConnectionsTest extends TestCase
{
    public function test_cache_store_references_an_existing_redis_connection(): void
    {
        $connection = config('cache.stores.redis.connection', 'cache');

        $this->assertIsArray(
            config("database.redis.{$connection}"),
            "Der Redis-Cache-Store verweist auf die Verbindung '{$connection}', "
            . 'die in config/database.php nicht definiert ist.',
        );
    }

    public function test_cache_lock_references_an_existing_redis_connection(): void
    {
        $connection = config('cache.stores.redis.lock_connection', 'default');

        $this->assertIsArray(
            config("database.redis.{$connection}"),
            "Der Cache-Lock verweist auf die Verbindung '{$connection}', "
            . 'die in config/database.php nicht definiert ist.',
        );
    }

    public function test_queue_references_an_existing_redis_connection(): void
    {
        $connection = config('queue.connections.redis.connection', 'default');

        $this->assertIsArray(
            config("database.redis.{$connection}"),
            "Die Queue verweist auf die Redis-Verbindung '{$connection}', "
            . 'die in config/database.php nicht definiert ist.',
        );
    }

    public function test_worker_queue_matches_supervisor_configuration(): void
    {
        // setup.sh/update.sh starten den Worker mit --queue=tms,default.
        // Weicht der Standard hier ab, befuellt der Dienst eine Warteschlange,
        // die niemand abarbeitet — mit demselben Ergebnis: keine Begriffe.
        $this->assertSame('tms', config('queue.connections.redis.queue'));
    }
}
