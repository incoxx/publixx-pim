<?php

declare(strict_types=1);

namespace App\Services\ArtisanCockpit;

/**
 * Kuratierte Liste von Artisan-Befehlen für das Artisan-Cockpit (System-Menü).
 *
 * Nur Befehle mit `runnable = true` können per GUI ausgeführt werden. Sie sind auf
 * Tagesgeschäft/Entstörung beschränkt und benötigen ausschließlich optionale
 * Flags/Argumente (keine Datei-Pfade auf dem Server, keine Dauerprozesse wie
 * `queue:work`). Alle anderen Befehle werden zur Übersicht mit aufgeführt, aber
 * nur als Konsolen-Hinweis angezeigt (`runnable = false` + `console_hint`) –
 * entweder weil sie destruktiv sind (migrate:fresh, db:wipe, ...), einen
 * Datei-Pfad brauchen (pim:import, ...) oder als Daemon dauerhaft laufen
 * (queue:work, horizon:listen, ...).
 */
class ArtisanCommandRegistry
{
    /**
     * @return array<int, array{key: string, label: string, commands: array<int, array>}>
     */
    public static function categories(): array
    {
        return [
            [
                'key' => 'cache',
                'label' => 'Cache & Performance',
                'commands' => [
                    self::runnable('cache:clear', 'Application-Cache leeren', 'Leert den Laravel-Application-Cache (Redis).'),
                    self::runnable('config:clear', 'Config-Cache leeren', 'Entfernt die gecachte Konfigurationsdatei.'),
                    self::runnable('config:cache', 'Config-Cache neu erzeugen', 'Erzeugt die Konfiguration neu als Cache-Datei für schnelleres Booten.'),
                    self::runnable('route:clear', 'Route-Cache leeren', 'Entfernt die gecachte Routen-Datei.'),
                    self::runnable('route:cache', 'Route-Cache neu erzeugen', 'Erzeugt den Routen-Cache neu.'),
                    self::runnable('view:clear', 'View-Cache leeren', 'Entfernt kompilierte Blade-Views.'),
                    self::runnable('optimize:clear', 'Alle Caches leeren', 'Entfernt Bootstrap-, Config-, Route- und View-Cache in einem Schritt.'),
                    self::runnable('optimize', 'Alle Caches neu erzeugen', 'Erzeugt Bootstrap-, Config-, Route- und View-Cache neu (nach optimize:clear sinnvoll).'),
                    self::runnable('pim:content-cache-clear', 'Content-Cache leeren', 'Verwirft den gerenderten Content-Cache (gesamt oder gezielt per Navigation/Seite/Produkt).', options: [
                        self::option('nav', 'Navigation', 'string', help: 'Technical-Name oder ID – leer = alle'),
                        self::option('page', 'Seite', 'string', help: 'Slug oder ID – leer = alle'),
                        self::option('product', 'Produkt-ID', 'string'),
                        self::option('stats', 'Nur Hit-Rate anzeigen (setzt Metriken zurück)', 'boolean'),
                    ]),
                    self::runnable('pim:content-cache-warm', 'Content-Cache vorrendern', 'Rendert Sitemap, Seiten und Produktseiten vor, um den Cache aufzuwärmen.', timeout: 300, options: [
                        self::option('nav', 'Navigation', 'string', help: 'Technical-Name oder ID – leer = alle'),
                        self::option('lang', 'Sprachen', 'string', default: 'de', help: 'kommasepariert, z. B. de,en'),
                    ]),
                ],
            ],
            [
                'key' => 'search',
                'label' => 'Suche & Indexierung',
                'commands' => [
                    self::runnable('pim:meili-index', 'Meilisearch synchronisieren', 'Synchronisiert Produkte inkrementell (oder komplett) in den Meilisearch-Index.', timeout: 600, options: [
                        self::option('all', 'Alle Produkte (voller Reindex, nicht nur Änderungen)', 'boolean'),
                        self::option('chunk', 'Chunk-Größe', 'number', default: '500'),
                    ]),
                    self::runnable('search:reindex', 'Produktsuche neu indexieren', 'Reindexiert alle Produkte im Such-Index.', timeout: 600, danger: 'confirm', options: [
                        self::option('sync', 'Synchron ausführen (statt über die Queue)', 'boolean'),
                    ]),
                    self::runnable('typesense:reindex', 'PDF-Seiten neu indexieren (Typesense)', 'Indexiert alle verarbeiteten PDF-Dokumente neu für die Volltextsuche.', timeout: 600, danger: 'confirm', options: [
                        self::option('fresh', 'Collection vorher löschen und neu erstellen', 'boolean'),
                    ]),
                    self::console('pim:meili-setup', 'Meilisearch-Index konfigurieren', 'Legt die Meilisearch-Index-Konfiguration (Schema, Facetten) neu an – einmaliges Setup.', 'php artisan pim:meili-setup'),
                    self::console('typesense:setup', 'Typesense-Collection anlegen', 'Legt die Typesense-Collection für die PDF-Seitensuche an – einmaliges Setup.', 'php artisan typesense:setup'),
                ],
            ],
            [
                'key' => 'queue',
                'label' => 'Queue & Hintergrundjobs',
                'commands' => [
                    self::runnable('horizon:status', 'Horizon-Status prüfen', 'Zeigt, ob der Horizon-Master-Supervisor aktuell läuft oder pausiert ist.'),
                    self::runnable('horizon:supervisors', 'Supervisoren auflisten', 'Listet alle aktiven Horizon-Supervisoren mit ihren Queues auf.'),
                    self::runnable('queue:monitor', 'Queue-Auslastung prüfen', 'Zeigt die aktuelle Länge der angegebenen Queues.', options: [
                        self::option('queues', 'Queues', 'string', default: 'default,import,export,indexing,cache,warmup,pdf', help: 'kommasepariert'),
                    ]),
                    self::runnable('queue:failed', 'Fehlgeschlagene Jobs auflisten', 'Zeigt alle fehlgeschlagenen Queue-Jobs.'),
                    self::runnable('queue:retry', 'Fehlgeschlagene Jobs erneut versuchen', 'Stößt fehlgeschlagene Jobs erneut an.', danger: 'confirm', argument: ['name' => 'id', 'label' => 'Job-ID', 'default' => 'all', 'help' => '"all" für alle fehlgeschlagenen Jobs'], options: [
                        self::option('queue', 'Nur diese Queue', 'string'),
                    ]),
                    self::runnable('queue:restart', 'Queue-Worker neu starten', 'Signalisiert allen Workern, sich nach dem aktuellen Job neu zu starten (z.B. nach Deployment).', danger: 'confirm'),
                    self::runnable('queue:clear', 'Queue leeren', 'Löscht alle wartenden Jobs einer Queue unwiderruflich.', danger: 'confirm', options: [
                        self::option('queue', 'Queue-Name', 'string', required: true, help: 'z.B. import, export, indexing'),
                    ]),
                    self::console('queue:work', 'Queue-Worker starten', 'Startet einen dauerhaften Worker-Prozess – läuft als Daemon, nicht für einmalige GUI-Ausführung geeignet.', 'php artisan queue:work --queue=import,export,default'),
                    self::console('horizon:pause', 'Horizon pausieren', 'Pausiert die Job-Verarbeitung, ohne den Master-Prozess zu beenden.', 'php artisan horizon:pause'),
                    self::console('horizon:continue', 'Horizon fortsetzen', 'Setzt die Job-Verarbeitung nach einer Pause fort.', 'php artisan horizon:continue'),
                    self::console('horizon:terminate', 'Horizon beenden', 'Beendet den Master-Supervisor (Supervisor/Systemd startet ihn i.d.R. neu).', 'php artisan horizon:terminate'),
                    self::console('queue:flush', 'Alle fehlgeschlagenen Jobs löschen', 'Löscht die komplette failed_jobs-Tabelle unwiderruflich.', 'php artisan queue:flush'),
                ],
            ],
            [
                'key' => 'maintenance',
                'label' => 'Wartung & Bereinigung',
                'commands' => [
                    self::runnable('pim:conformance-recheck', 'Produkt-Konformität prüfen', 'Prüft Produkte gegen ihr virtuelles Referenzprodukt (Profil) und markiert Abweichungen.', timeout: 600, options: [
                        self::option('profile', 'Nur dieses Referenz-Profil (UUID)', 'string'),
                    ]),
                    self::runnable('media:scan-missing', 'Medien-Dateien prüfen', 'Prüft alle Medien-Dateien auf Existenz und aktualisiert den Datei-Status (ok/missing).', timeout: 600, danger: 'confirm', options: [
                        self::option('reset', 'Alle Status vorher auf "ok" zurücksetzen', 'boolean'),
                    ]),
                    self::runnable('media:deduplicate', 'Medien-Duplikate bereinigen', 'Behält bei gleichem Dateinamen im selben Ordner die neueste Version, ältere werden archiviert.', timeout: 600, danger: 'confirm', options: [
                        self::option('dry-run', 'Nur anzeigen, nichts ändern (--dry-run)', 'boolean', default: '1'),
                        self::option('folder', 'Nur dieser Ordner (UUID)', 'string'),
                    ]),
                    self::runnable('pim:fix-media-import-paths', 'Import-Medienpfade korrigieren', 'Korrigiert Datei-Pfade und führt Duplikate aus fehlerhaften Medien-Importen zusammen.', timeout: 600, danger: 'confirm', options: [
                        self::option('dry-run', 'Nur anzeigen, nichts ändern (--dry-run)', 'boolean', default: '1'),
                    ]),
                    self::runnable('pim:content-cleanup-orphans', 'Verwaiste Navigationsknoten bereinigen', 'Entfernt Navigationsknoten, deren Ziel (Seite/Produkt) gelöscht wurde.', danger: 'confirm'),
                    self::runnable('hierarchy:repair-paths', 'Hierarchie-Pfade reparieren', 'Repariert die materialisierten UUID-Pfade von Hierarchie-Knoten.', timeout: 300, danger: 'confirm', options: [
                        self::option('dry-run', 'Nur anzeigen, nichts ändern (--dry-run)', 'boolean', default: '1'),
                    ]),
                    self::runnable('attributes:cleanup-translatable-values', 'Attributwerte bereinigen', 'Bereinigt Altlasten in product_attribute_values, wenn ein Attribut nachträglich auf "sprachabhängig" umgestellt wurde.', timeout: 300, danger: 'confirm', options: [
                        self::option('dry-run', 'Nur anzeigen, nichts ändern (--dry-run)', 'boolean', default: '1'),
                        self::option('default-language', 'Zielsprache für Zeilen ohne Sprachbezug', 'string', default: 'de'),
                    ]),
                    self::console('model:prune', 'Prunable Models löschen', 'Löscht dauerhaft alle Models, die als "prunable" markiert sind (z.B. abgelaufene Tokens).', 'php artisan model:prune'),
                ],
            ],
            [
                'key' => 'diagnostics',
                'label' => 'Diagnose & System-Info',
                'commands' => [
                    self::runnable('about', 'System-Info anzeigen', 'Zeigt Laravel-, PHP- und Umgebungsinformationen.'),
                    self::runnable('env', 'Aktive Umgebung anzeigen', 'Zeigt die aktuell aktive Laravel-Umgebung (APP_ENV).'),
                    self::runnable('migrate:status', 'Migrationsstatus anzeigen', 'Zeigt, welche Datenbank-Migrationen bereits ausgeführt wurden.'),
                    self::runnable('db:show', 'Datenbank-Übersicht anzeigen', 'Zeigt Tabellen, Größen und Zeilenzahlen der aktuellen Datenbank.'),
                    self::runnable('schedule:list', 'Geplante Tasks anzeigen', 'Listet alle im Scheduler registrierten Tasks mit nächster Ausführungszeit.'),
                    self::runnable('pim:classify-errors', 'Fehler KI-klassifizieren', 'Klassifiziert Laravel-Logs und fehlgeschlagene Queue-Jobs automatisch via KI.', timeout: 300, options: [
                        self::option('limit', 'Maximale Anzahl Fehler-Gruppen', 'number', default: '50'),
                        self::option('dry-run', 'Nur analysieren, nichts speichern (--dry-run)', 'boolean'),
                    ]),
                    self::console('tinker', 'Interaktive PHP-Konsole', 'Startet eine interaktive REPL mit vollem Anwendungskontext – nur für Entwickler.', 'php artisan tinker'),
                    self::console('down', 'Wartungsmodus aktivieren', 'Schaltet die Anwendung für alle Besucher in den Wartungsmodus.', 'php artisan down'),
                    self::console('up', 'Wartungsmodus beenden', 'Beendet den Wartungsmodus.', 'php artisan up'),
                    self::console('migrate', 'Ausstehende Migrationen ausführen', 'Führt neue, noch nicht angewendete Migrationen aus.', 'php artisan migrate --force'),
                    self::console('migrate:rollback', 'Letzte Migration zurückrollen', 'Macht die zuletzt ausgeführte Migrationsgruppe rückgängig.', 'php artisan migrate:rollback --force'),
                    self::console('migrate:fresh', 'Datenbank komplett neu aufbauen', 'ACHTUNG: Löscht alle Tabellen und führt alle Migrationen neu aus. Nur auf Test-/Dev-Systemen.', 'php artisan migrate:fresh --force'),
                    self::console('db:wipe', 'Datenbank komplett leeren', 'ACHTUNG: Löscht alle Tabellen, Views und Types unwiderruflich.', 'php artisan db:wipe --force'),
                    self::console('db:seed', 'Seeder ausführen', 'Füllt die Datenbank mit Seed-/Testdaten.', 'php artisan db:seed --force'),
                    self::console('key:generate', 'Application-Key neu erzeugen', 'ACHTUNG: Invalidiert alle bestehenden Sessions und verschlüsselten Daten.', 'php artisan key:generate --force'),
                ],
            ],
            [
                'key' => 'import-export',
                'label' => 'Import & Export',
                'commands' => [
                    self::console('pim:import', 'Excel-/Datei-Import ausführen', 'Führt einen Import über einen Server-Dateipfad aus – dafür gibt es die Import-Oberfläche unter „Import“.', 'php artisan pim:import /pfad/datei.xlsx --force'),
                    self::console('pim:bmecat-import', 'BMEcat-Import ausführen', 'Importiert PIM-Daten aus einer BMEcat-XML-Datei.', 'php artisan pim:bmecat-import /pfad/datei.xml'),
                    self::console('pim:json-import', 'JSON-Import ausführen', 'Importiert PIM-Daten aus einem JSON-Export.', 'php artisan pim:json-import /pfad/datei.json'),
                    self::console('pim:export', 'Export-Profil ausführen', 'Führt ein gespeichertes Export-Profil aus – dafür gibt es die Export-Oberfläche unter „Export“.', 'php artisan pim:export {profil-id}'),
                    self::console('pim:export-job', 'Export-Job verwalten/ausführen', 'Legt Export-Jobs an oder führt fällige Jobs aus.', 'php artisan pim:export-job --run-scheduled'),
                    self::console('pim:sync', 'Instanzen synchronisieren', 'Synchronisiert Produkte zwischen zwei anyPIM-Instanzen.', 'php artisan pim:sync'),
                    self::console('pim:test-catalog', 'Test-Katalog generieren', 'Erzeugt einen vollständigen Demo-Katalog über alle PIM-Bereiche als Excel-Datei.', 'php artisan pim:test-catalog'),
                ],
            ],
        ];
    }

    /**
     * Sucht einen Befehl anhand seines Keys (über alle Kategorien).
     */
    public static function find(string $command): ?array
    {
        foreach (self::categories() as $category) {
            foreach ($category['commands'] as $cmd) {
                if ($cmd['command'] === $command) {
                    return $cmd;
                }
            }
        }

        return null;
    }

    private static function runnable(
        string $command,
        string $label,
        string $description,
        string $danger = 'safe',
        int $timeout = 60,
        ?array $argument = null,
        array $options = [],
    ): array {
        return [
            'command' => $command,
            'label' => $label,
            'description' => $description,
            'runnable' => true,
            'danger' => $danger, // 'safe' | 'confirm'
            'timeout' => $timeout,
            'argument' => $argument,
            'options' => $options,
        ];
    }

    private static function console(string $command, string $label, string $description, string $consoleHint): array
    {
        return [
            'command' => $command,
            'label' => $label,
            'description' => $description,
            'runnable' => false,
            'console_hint' => $consoleHint,
        ];
    }

    private static function option(
        string $name,
        string $label,
        string $type,
        ?string $default = null,
        bool $required = false,
        ?string $help = null,
    ): array {
        return [
            'name' => $name,
            'label' => $label,
            'type' => $type, // 'boolean' | 'string' | 'number'
            'default' => $default,
            'required' => $required,
            'help' => $help,
        ];
    }
}
