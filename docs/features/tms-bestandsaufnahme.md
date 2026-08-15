# Translation Memory (TMS) — Bestandsaufnahme & Code-Review

**Stand:** 2026-08-15 · **Umfang:** `tms/` (eigenständiger Laravel-Service) + PIM-seitige Anbindung

---

## 1. Bestandsaufnahme

### 1.1 Architektur

Das Translation Memory ist ein **separater Laravel-11-Service** im Unterverzeichnis `tms/`
mit eigener Datenbank, eigenem Redis-DB-Index, eigenem Queue-Worker und eigenem
HTTP-Port. Das PIM spricht ihn ausschließlich über HTTP mit Bearer-Token an.

```
┌──────────────── PIM (Laravel) ─────────────────┐      ┌──────── TMS (Laravel) ────────┐
│                                                │      │                               │
│  TmsIngestCommand ──► IngestToTmsJob ──────────┼─POST─┼─► IngestController            │
│  (tms:ingest, täglich)                         │      │        └─► ProcessIngestBatch │
│                                                │      │              └─► TranslateUnit│
│  TmsSyncCommand ───► SyncTmsTranslationsJob ───┼─GET──┼─► ResolveController           │
│  (tms:sync, 04:00)      └─► name_json[lang]    │      │        (Redis → DB-Fallback)  │
│                                                │      │                               │
│  TranslationJobService ────────────────────────┼─POST─┼─► ImportTranslationsController│
│  (DeepL-Jobs → TM)                             │      │                               │
│                                                │      │  MySQL: tms_units,            │
│  TmsProxyController ◄── Vue (TranslationView)  │      │         tms_translations,     │
│      └─► TmsClient ────────────────────────────┼─HTTP─┼─► UnitController              │
│                                                │      │         tms_usages,           │
│  Redis DB 1/2/3                                │      │         tms_mt_log            │
└────────────────────────────────────────────────┘      │  Redis DB 4 (Cache + Queue)   │
                                                        └───────────────────────────────┘
```

### 1.2 Bestandteile

| Bereich | Dateien | Zeilen | Status |
|---------|---------|--------|--------|
| TMS-Service (`tms/`) | 21 PHP-Dateien | 1.702 | vollständig |
| PIM-Anbindung | 7 Dateien | 864 | vollständig |
| Frontend (Vue) | 6 Views + Store + API | ~2.100 | vollständig |
| Migrationen TMS | 5 | — | vollständig |
| **Tests** | **0** | **0** | **fehlt komplett** |

**TMS-Service:**

- Controller: `Ingest`, `Resolve`, `Unit` (index/show/update/stats/missing/delete/purge/retranslate), `ImportTranslations`
- Jobs: `ProcessIngestBatchJob`, `TranslateUnitJob`
- Provider: `DeepLProvider`, `GoogleTranslateProvider`, `ClaudeProvider`, `OpenAIProvider` + `ProviderChain` (Fallback-Kette)
- Models: `TmsUnit`, `TmsTranslation`, `TmsUsage`, `TmsMtLog`
- Middleware: `ValidateApiKey` (Bearer, `hash_equals`)

**PIM-Seite:**

- `App\Services\Tms\TmsClient` — HTTP-Client, fail-soft (loggt Warnung, gibt `[]` zurück)
- `IngestToTmsJob` — schickt Metadaten-Entitäten (10 Typen) an TMS
- `SyncTmsTranslationsJob` — holt Übersetzungen zurück in `name_json` / `display_value_json`
- `TmsProxyController` + Routen unter `/api/v1/tms/*`, Permission `translations.edit`
- Commands `tms:ingest`, `tms:sync`; Scheduler in `routes/console.php`

**Ingestierte Entitätstypen (10):** `unit_group`, `attribute_view`, `attribute_group`,
`value_list`, `value_list_entry`, `attribute`, `product_type`, `price_type`,
`relation_type`, `hierarchy`, `hierarchy_node`.
Produkt-Nutzdaten laufen **nicht** über das TM, nur Metadaten/Stammdaten.

### 1.3 Deployment-Status

| Skript | TMS-Behandlung |
|--------|----------------|
| `setup.sh` (Erstinstallation, 10 Schritte) | **keine einzige Erwähnung von TMS** |
| `update.sh` (10 Schritte) | Schritt 7/10, hinter `TMS_ENABLED=true`, `--skip-tms` |

`update.sh` legt `tms/.env` an, generiert `APP_KEY`, führt `composer install --no-dev`
und `migrate --force` aus, startet Queue-Worker und HTTP-Server jeweils per `nohup`.

### 1.4 Letzte Änderung

Letzter Commit, der TMS-Code berührt: `74b0ea2` (2026-07-26) — und das nur als Merge.
Der Modul-Code selbst stammt aus der Erstimplementierung (Migrationen datiert 2026-03-09).
Es existiert **kein einziger Test**, weder in `tms/tests/` (Verzeichnis fehlt, kein
`phpunit.xml`) noch in den 105 PIM-Testdateien (kein Treffer auf „tms").

---

## 2. Code-Review — Befunde

Sortiert nach Schweregrad. `[B]` = Blocker, `[H]` = hoch, `[M]` = mittel, `[N]` = niedrig.

### Deployment / Betrieb

**[B-1] `setup.sh` richtet TMS überhaupt nicht ein**
Nach einer Erstinstallation existiert `tms/vendor` nicht, `tms/.env` nicht, die
TMS-Datenbank nicht, kein Queue-Worker, kein HTTP-Server. Ein Kunde, der nach
`setup.sh` `TMS_ENABLED=true` setzt, bekommt bei jedem TM-Aufruf einen
Connection-Refused, den `TmsClient` still schluckt (Log-Warnung, leeres Array) —
die UI zeigt einfach 0 Begriffe, ohne Fehlermeldung. Das ist genau das Szenario,
das laut Anforderung *immer* funktionieren muss.
→ TMS-Schritt in `setup.sh` analog zu `update.sh` ergänzen.

**[B-2] `update.sh` bricht bei TMS-Erstlauf komplett ab (`set -euo pipefail`)**
`update.sh:35` setzt `set -euo pipefail`. Im TMS-Block laufen drei ungeschützte Befehle:

```bash
TMS_APP_KEY=$(grep '^APP_KEY=' "${TMS_DIR}/.env" | cut -d'=' -f2-)   # Zeile 703
php artisan key:generate --force                                      # Zeile 706
php artisan migrate --force                                           # Zeile 723
```

- `tms/artisan` macht in Zeile 6 `require __DIR__.'/vendor/autoload.php'`. Beim
  **allerersten** Lauf existiert `tms/vendor` noch nicht — `key:generate` läuft
  aber **vor** `composer install` (Zeile 710 ff.). Fataler PHP-Error, Exit ≠ 0.
- `set -e` beendet damit das gesamte `update.sh` **in Schritt 7 von 10**.
  Die Schritte 8 (Caches), 9 (Frontend/Assets) und 10 (Webserver, Supervisor,
  Cron, Berechtigungen) laufen nie — das gesamte Update bleibt halb fertig.
- Dasselbe passiert, wenn die MySQL-Datenbank `tms` nicht existiert
  (`migrate` schlägt fehl) oder wenn `APP_KEY=` in der `.env` fehlt
  (`grep` liefert Exit 1, `pipefail` schlägt durch).

→ Reihenfolge korrigieren (`composer install` vor `key:generate`), alle drei
Aufrufe mit `|| { warn ...; }` absichern, TMS-Fehler dürfen das Update nicht killen.

**[H-3] TMS-Datenbank wird nirgends angelegt**
`tms/.env.example` setzt `DB_DATABASE=tms`, `DB_USERNAME=root`, `DB_PASSWORD=` (leer).
Weder `setup.sh` noch `update.sh` legen Datenbank oder Benutzer an oder übernehmen
die MySQL-Zugangsdaten aus der PIM-`.env`. Der Erstlauf scheitert zwangsläufig
(siehe B-2).

**[H-4] `TMS_API_KEY` wird zwischen PIM und TMS nicht abgeglichen**
PIM-`.env.example`: `TMS_API_KEY=change-me-to-a-secure-key`,
TMS-`.env.example`: `TMS_API_KEY=your-shared-api-key`. Die beiden Werte **müssen
identisch** sein, werden aber von keinem Skript synchronisiert. Default-Zustand
nach Installation: alle TMS-Aufrufe → 401, still geschluckt vom `TmsClient`.
Dasselbe gilt für Port-Konsistenz: PIM `TMS_BASE_URL=…:8001/api` vs. TMS
`APP_URL=…:8001` — auseinanderlaufend konfigurierbar, ohne Prüfung.

**[H-5] Produktivbetrieb auf PHP-Built-in-Server ohne Prozessüberwachung**
`update.sh:738-747` startet beides per `nohup … &`:
```bash
nohup php "${TMS_DIR}/artisan" queue:work --queue=tms,default …
nohup php -S "127.0.0.1:${TMS_PORT}" -t "${TMS_DIR}/public" …
```
- `php -S` ist ein **single-threaded Dev-Server** — laut PHP-Doku ausdrücklich
  nicht für Produktion. Er serialisiert alle Requests; `resolve` mit
  2 s Client-Timeout (`TMS_TIMEOUT`) läuft dabei schnell ins Leerlaufen.
- Kein Supervisor/systemd → beide Prozesse sind **nach einem Reboot weg**.
  Das PIM nutzt für seine eigenen Worker korrekt Supervisor/Horizon
  (`setup.sh:1660`), TMS bleibt davon ausgenommen.
- `TMS_ENABLED` wird zudem nur aus der PIM-`.env` gelesen, nicht aus einem
  gecachten Config-Zustand — nach `config:cache` können Skript und App
  auseinanderlaufen.

**[M-6] `APP_DEBUG=true` + Exception-Leak in der Default-Konfiguration**
`tms/.env.example` liefert `APP_ENV=local` / `APP_DEBUG=true` aus, und
`tms/bootstrap/app.php` gibt in `withExceptions` **jede** Exception-Message
im JSON-Body zurück:
```php
return response()->json(['error' => $e->getMessage()], …);
```
Damit landen DB-Fehler inkl. SQL-Fragmenten und Provider-API-Fehler
(inkl. evtl. Key-Fragmenten aus `$response->body()`, siehe DeepLProvider) in
der HTTP-Antwort. `update.sh` korrigiert `APP_ENV`/`APP_DEBUG` nicht.

**[M-7] Keine Health-Prüfung des TMS im PIM**
`SystemInfoController` zeigt die TMS-Env-Variablen nur an, prüft aber nie, ob
der Service erreichbar ist. `tms/bootstrap/app.php` stellt bereits `health: '/up'`
bereit — der Endpunkt wird nirgends abgefragt. Da `TmsClient` alle Fehler
verschluckt, ist ein toter TMS von außen nicht von „keine Daten" unterscheidbar.

### Funktionale Fehler

**[B-8] `openai` fehlt im `provider`-ENUM → Job-Abbruch bei OpenAI-Nutzung**
`tms/database/migrations/…000002_create_tms_translations_table.php:14`:
```php
$table->enum('provider', ['deepl', 'google', 'claude', 'human', 'import'])
```
`OpenAIProvider::name()` liefert aber `'openai'`, und `ProviderChain` lässt
`openai` über `TMS_PROVIDER_CHAIN` explizit zu. Sobald OpenAI in der Kette greift,
schlägt `TmsTranslation::updateOrCreate([… 'provider' => 'openai'])` im
MySQL-Strict-Mode mit einem Data-Truncated-Fehler fehl — die Übersetzung geht
verloren, der Job läuft in `tries = 3` und landet in `failed_jobs`.
Zusätzlich akzeptiert `ImportTranslationsController` `provider` als
`string|max:20` ungeprüft und schreibt ihn direkt ins ENUM — jeder abweichende
Wert erzeugt denselben Fehler.
→ Migration um `openai` ergänzen (oder ENUM → `varchar(20)`) und im Import
gegen eine Whitelist validieren.

**[H-9] `IngestToTmsJob`: Batches mit leeren `fields` werden komplett verworfen**
`app/Jobs/IngestToTmsJob.php` filtert Entitäten ohne Felder **erst ganz am Ende**:
```php
$entities = array_values(array_filter($entities, fn ($e) => !empty($e['fields'])));
```
Die Zwischen-`$flush()`-Aufrufe (bei Value-Lists, Attributen, Hierarchie-Knoten —
also genau den großen Tabellen) senden ungefiltert. `IngestController` validiert
`entities.*.fields => required|array|min:1`; eine einzige Entität ohne `name_de`
**und** ohne `name_en` lässt die Validierung für den **gesamten Batch von bis zu
200 Entitäten** mit 422 scheitern. `TmsClient::ingest()` loggt nur eine Warnung —
die 200 Begriffe fehlen danach still im TM. Bei Hierarchie-Knoten, die nur
`name_json` gepflegt haben, tritt das regelmäßig auf.
→ Filter in `buildEntity()` bzw. direkt vor jedem `ingest()`-Aufruf ziehen.

**[H-10] `buildEntity()` erzeugt bei Lücken ein JSON-Objekt statt eines Arrays**
```php
'fields' => array_filter($fields, fn ($f) => !empty($f['text'])),
```
`array_filter` erhält die Schlüssel. Ist `name_de` leer und `name_en` gesetzt,
entsteht `[1 => …]` → `json_encode` serialisiert das als `{"1": {…}}` statt als
Liste. Aktuell trägt die Laravel-Validierung das noch mit, es ist aber eine
stille Formatabweichung im API-Vertrag.
→ `array_values(array_filter(...))`.

**[H-11] Englische Quelltexte werden ingestiert, aber nie verwendet**
`IngestToTmsJob` sendet pro Entität **zwei** Felder: `name_de` (lang `de`) und
`name_en` (lang `en`). Der Hash ist `sha256(lang|text)`, es entstehen also zwei
getrennte TM-Units. `SyncTmsTranslationsJob` löst jedoch ausschließlich
`sha256('de|' . $sourceText)` auf. Konsequenz:

- Für jede englische Unit werden `fr`, `es`, `it`, `nl` maschinell übersetzt und
  bezahlt — die Ergebnisse werden **nie abgerufen**. Das verdoppelt die
  MT-Kosten des Erst-Ingests nahezu.
- `UnitController::stats()` zählt `TmsUnit::count()` als `total` für **jede**
  Sprache. Da `TranslateUnitJob` `$targetLang === $unit->source_lang`
  überspringt, kann die en-Abdeckung strukturell nie 100 % erreichen — die
  Coverage-Anzeige im Dashboard ist dauerhaft zu niedrig.

→ Entweder `name_en` gar nicht ingestieren, oder `stats`/`missing` nach
`source_lang` filtern und die en-Units gezielt für die Rückrichtung nutzen.

**[H-12] `triggerIngest` / `syncToDatabase` laufen synchron im HTTP-Request**
`TmsProxyController:102` und `:150`:
```php
$job = new IngestToTmsJob();
$result = $job->handle($this->client);
```
Beide Jobs iterieren über **den gesamten** Metadaten-Bestand (u. a. alle
`hierarchy_nodes`, alle `attributes`, alle `value_list_entries`) mit
HTTP-Roundtrips pro 200er-Batch. Bei realistischen Katalogen läuft das in
`max_execution_time` bzw. den Apache-Timeout. Die Klassen implementieren
`ShouldQueue` und `ShouldBeUnique` — sie sind für den Queue-Betrieb gebaut und
werden hier bewusst umgangen.
→ `dispatch()` statt `handle()`, Status über Job-Batch/Polling zurückmelden.

**[M-13] `SyncTmsTranslationsJob` überschreibt manuell gepflegte Übersetzungen**
Der Sync schreibt jeden vom TM gelieferten Wert nach `name_json[$lang]`, sobald
er vom Bestand abweicht — unabhängig davon, ob der PIM-Wert von Hand gepflegt
wurde. Im TM selbst gibt es eine `status`-Unterscheidung (`auto` / `reviewed`),
die beim Rückschreiben nicht ausgewertet wird. Wenn das TM bewusst führend ist,
gehört das dokumentiert; andernfalls braucht es einen Schutz für redaktionell
gepflegte Felder.

**[M-14] `saveQuietly()` umgeht die Suchindex-Aktualisierung**
`SyncTmsTranslationsJob` speichert durchgängig mit `saveQuietly()`. Damit feuern
keine Model-Events — Meilisearch/Typesense-Indizes und Cache-Invalidierung
bekommen die neuen Übersetzungen nicht mit. Nach einem Sync sind Suchtreffer in
den Zielsprachen so lange veraltet, bis ein Reindex läuft.

**[M-15] `Redis::keys()` beim Purge blockiert Redis**
`UnitController::deleteTranslations()` und `purgeUnits()` nutzen
`Redis::keys("{$prefix}*")`. `KEYS` ist ein blockierender O(N)-Befehl über den
gesamten Keyspace der Redis-DB — in DB 4 liegen zusätzlich die Queue-Daten.
→ `SCAN`-basiertes Löschen in Blöcken.

**[M-16] `ResolveController` macht bis zu 4.000 sequentielle Redis-GETs**
Limits sind 200 Hashes × 20 Sprachen; die Schleife ruft `Redis::get()` einzeln
auf. Bei einem `SyncTmsTranslationsJob` über einen großen Katalog summiert sich
das erheblich.
→ `MGET`/Pipeline verwenden.

**[N-17] `TmsTranslation` mit Array-`$primaryKey` ist fragil**
`protected $primaryKey = ['tms_unit_id', 'target_lang'];` wird von Eloquent nicht
offiziell unterstützt. `getKey()` und `setKeysForSaveQuery()` sind zwar
überschrieben, aber `getKeyName()` liefert ein Array — `refresh()`, `fresh()`,
Route-Model-Binding und `find()` würden brechen. Aktuell wird nichts davon
genutzt; als Falle für künftige Änderungen dokumentieren oder eine
Surrogat-`id` einführen.

**[N-18] Kleinigkeiten**
- `IngestToTmsJob` importiert `App\Models\ValueListEntry`, verwendet es nicht.
- `tms_translations.status` kennt `'pending'`, kein Codepfad schreibt den Wert je.
- `TmsProxyController::retranslate` validiert `unit_ids.*` als `string|max:36`,
  der TMS-`UnitController` verlangt `uuid` — ungültige IDs werden erst im TMS
  abgelehnt und dort als 422 verschluckt (`TmsClient` gibt `[]` zurück, die UI
  meldet nichts).
- `TmsClient` liefert bei jedem Fehler `[]`; die Vue-Views unterscheiden nicht
  zwischen „leer" und „Fehler". Nutzer sehen bei kaputtem TMS eine leere,
  scheinbar funktionierende Oberfläche.

### Tests

**[B-19] Null Testabdeckung für ein als zentral eingestuftes Modul**
- `tms/` hat kein `tests/`-Verzeichnis und keine `phpunit.xml`, obwohl
  `phpunit/phpunit` in `require-dev` steht. `update.sh` installiert ohnehin
  `--no-dev`.
- Keine der 105 PIM-Testdateien berührt TMS. `IngestToTmsJob`,
  `SyncTmsTranslationsJob`, `TmsClient` und `TmsProxyController` sind
  ungetestet — obwohl `TmsClient` über `Http::fake()` trivial testbar wäre.
- Der Hash-Vertrag `sha256("{$lang}|{$text}")` ist an **vier** Stellen dupliziert
  (`ProcessIngestBatchJob`, `ImportTranslationsController`,
  `SyncTmsTranslationsJob` ×2). Eine einseitige Änderung entwertet still das
  gesamte Translation Memory, ohne dass irgendein Test anschlägt.

---

## 3. Empfohlene Reihenfolge

| Prio | Maßnahme | Befunde |
|------|----------|---------|
| 1 | TMS-Schritt in `setup.sh` ergänzen (DB + Benutzer anlegen, API-Key aus PIM-`.env` übernehmen, `APP_ENV=production`) | B-1, H-3, H-4, M-6 |
| 2 | `update.sh` Schritt 7 absichern: `composer install` vor `key:generate`, alle Aufrufe gegen `set -e` schützen | B-2 |
| 3 | Migration: `openai` ins `provider`-ENUM, Import-Whitelist | B-8 |
| 4 | `IngestToTmsJob`: Feld-Filter vor jedes `ingest()` ziehen, `array_values()` | H-9, H-10 |
| 5 | Test-Suite aufsetzen: Hash-Vertrag, `TmsClient` mit `Http::fake()`, Ingest/Resolve-Roundtrip, Sync-Mapping | B-19 |
| 6 | Supervisor-Units für TMS-Worker und TMS-HTTP (statt `nohup` + `php -S`), Health-Check im `SystemInfoController` | H-5, M-7 |
| 7 | `triggerIngest`/`syncToDatabase` auf `dispatch()` umstellen | H-12 |
| 8 | `name_en`-Ingest klären, `stats` nach `source_lang` filtern | H-11 |
| 9 | Übrige M-/N-Befunde | M-13 … N-18 |

---

## 4. Offene Fragen

1. **Ist das TM für die Zielsprachen führend?** Davon hängt ab, ob M-13
   (Überschreiben manueller Übersetzungen) ein Bug oder gewolltes Verhalten ist.
2. **Soll `name_en` weiterhin ingestiert werden?** Aktuell entstehen Kosten ohne
   Nutzen (H-11). Wenn Englisch als zweite Quellsprache gedacht war, fehlt die
   Rückrichtung im Sync.
3. **Zielbetriebsart des TMS-HTTP-Endpunkts** — eigener Apache-VHost/Port,
   Unix-Socket oder Einbettung ins PIM? Davon hängt die Umsetzung von H-5 ab.
