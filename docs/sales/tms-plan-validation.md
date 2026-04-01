# TMS-Plan Validierung gegen Publixx PIM Codebasis

Stand: März 2026

---

## 1. VALIDIERUNGS-CHECKLISTE — Ergebnisse

### Tabellenstrukturen & name_de/name_en/name_json

**12 Tabellen haben `name_json`** (alle als `json()->nullable()`):

| Tabelle | name_de | name_en | name_json | Anmerkung |
|---------|---------|---------|-----------|-----------|
| `attributes` | ja | ja (nullable) | ja | |
| `attribute_types` | ja | ja (nullable) | ja | Im Plan als "attribute_groups" bezeichnet — **die Tabelle heisst `attribute_types`**, nicht `attribute_groups`! |
| `attribute_views` | ja | ja (nullable) | ja | |
| `unit_groups` | ja | ja (nullable) | ja | |
| `value_lists` | ja | ja (nullable) | ja | |
| `product_types` | ja | ja (nullable) | ja | |
| `price_types` | ja | ja (nullable) | ja | |
| `product_relation_types` | ja | ja (nullable) | ja | |
| `hierarchies` | ja | ja (nullable) | ja | |
| `hierarchy_nodes` | ja | ja (nullable) | ja | |
| `comparison_operator_groups` | ja | ja (nullable) | ja | **Nicht im Plan erwaehnt!** |
| `media_usage_types` | ja | ja (nullable) | ja | **Nicht im Plan erwaehnt!** |

### Abweichungen zum Plan

**1. `units`-Tabelle — KEIN name_de/name_en/name_json!**
- Die Tabelle hat: `technical_name`, `abbreviation`, `abbreviation_json` (json, nullable)
- Hat `is_translatable`-Flag, aber **keine name_de/name_en-Felder**
- Das Feld heisst `abbreviation_json`, nicht `name_json`
- Plan muss angepasst werden: Units haben Abkuerzungen, keine Namen

**2. `value_list_entries` — andere Feldnamen!**
- Hat: `display_value_de`, `display_value_en`, `display_value_json` (nicht name_de/name_en/name_json)
- Der TMS muss `display_value_json` statt `name_json` befuellen
- Der Ingest muss `display_value_de`/`display_value_en` als Quelltext verwenden

**3. `attribute_groups` existiert NICHT als Tabelle**
- Im Code heisst das Konzept `attribute_types` (Tabelle `attribute_types`, Model `AttributeType`)
- Der `JsonFormatExporter` exportiert es als `attribute_groups` (Zeile 240, method heisst `exportAttributeGroups()`, nutzt aber `AttributeType::query()`)
- Plan-Terminologie anpassen

**4. Zwei Entitaeten fehlen im Plan**
- `comparison_operator_groups` — hat name_de/name_en/name_json
- `media_usage_types` — hat name_de/name_en/name_json

### UUID-Pattern
- Alle Models nutzen `HasUuids` Trait (Laravel's built-in)
- DB-Spalten: `$table->char('id', 36)->primary()`
- Plan korrekt: CHAR(36) UUIDs

---

## 2. ExportService — Integrationspunkte fuer TmsClient

**Es gibt ZWEI verschiedene Export-Flows:**

### Flow 1: Mapping-basierter Export (`ExportService` + `DatasetBuilder`)
- `app/Services/Export/ExportService.php` — orchestriert Pipeline
- `app/Services/Export/DatasetBuilder.php` — baut Output-JSON
- `app/Services/Export/MappingResolver.php` — resolved Mapping-Regeln
- **Integrationspunkt:** `MappingResolver::resolve()` (Zeile 32-59) — hier werden bereits Sprach-Suffixe erzeugt (`target_en`, `target_fr`)

### Flow 2: JSON-Format-Export (`JsonFormatExporter`)
- `app/Services/Export/JsonFormatExporter.php` — exportiert komplettes PIM als JSON
- **Kritischer Integrationspunkt:** Jede `export*()` Methode gibt aktuell nur `name_de`/`name_en` aus, aber NICHT `name_json`
- Hier muss `name_json` ergaenzt werden

**Empfehlung:** Der beste Integrationspunkt ist der `JsonFormatExporter`, da er explizit alle Metadaten-Entitaeten einzeln mapped.

---

## 3. Frontend-Routing & Sidebar

**Framework:** Vue 3 + Vue Router + vue-i18n + Pinia Stores

**Router:** `pim-frontend/src/router/index.js`
- Flat route array mit lazy-loaded Components
- `meta: { title }` fuer Document-Title

**Sidebar:** `pim-frontend/src/components/layout/AppSidebar.vue`
- `allNavItems` Array mit `{ icon, label, to, permission? }` Objekten
- Divider-Items trennen Sektionen
- Icons: lucide-vue-next

**Neuen Menuepunkt hinzufuegen:**
1. Route in `pim-frontend/src/router/index.js` eintragen
2. Nav-Item in `AppSidebar.vue` im `allNavItems` Array einfuegen
3. Icon aus lucide-vue-next importieren (z.B. `Languages`)
4. View-Component unter `pim-frontend/src/views/translations/` anlegen

---

## 4. Bestehende Patterns

### HTTP-Client-Wrapper
Kein bestehender HTTP-Client-Wrapper vorhanden. TmsClient waere der erste dedizierte Service-Client.

### Queue-Jobs Conventions
Naming: `{Verb}{Noun}Job` (z.B. `ExecuteExportJob`, `UpdateSearchIndex`)
Traits: `Dispatchable, InteractsWithQueue, Queueable, SerializesModels`
TMS-Jobs: `IngestToTmsJob`, `TranslateTmsUnitJob`

### Event-System
Events und Observers vorhanden, aber KEINE Observers fuer Metadaten-Entitaeten.
Empfehlung: Scheduler-basierter Ingest statt Observers.

### Redis
4 separate Redis-DBs (0-3). TMS koennte DB 4 nutzen.
Cache-Key-Pattern: `pim:{entity}:{id}:{scope}`

### Auth
Laravel Sanctum mit Token-basierter API-Auth.
Service-to-Service: Sanctum API Token oder shared secret.

### Error-Responses
RFC 7807 Problem Details: `urn:anypim:error:<type>`

---

## 5. Wichtige Entdeckung: LocalizesResponse Trait

`app/Http/Traits/LocalizesResponse.php` nutzt `name_json` bereits als Fallback:
```php
protected function getLocalizedName(mixed $entity, string $lang): string {
    if ($lang === 'de') return $entity->name_de;
    if ($lang === 'en') return $entity->name_en ?? $entity->name_de;
    $json = $entity->name_json ?? [];
    return $json[$lang] ?? $entity->name_en ?? $entity->name_de;
}
```

Wenn `name_json` befuellt wird, funktionieren API-Responses **automatisch** mehrsprachig!

---

## 6. PLAN-BEWERTUNG

### Kann genau wie geplant umgesetzt werden:
- Separate Laravel-App mit eigener DB
- tms_units mit SHA-256 Hash
- tms_translations mit Compound PK
- tms_usages Verwendungsnachweise
- POST /api/ingest mit 202 Accepted
- GET /api/resolve mit Redis-first
- Graceful Degradation bei Timeout
- MT-Provider-Hierarchie
- "Bake, don't fry" Prinzip

### Muss angepasst werden:
1. Entitaeten-Liste korrigieren (attribute_types, units, value_list_entries)
2. Ingest-Payload flexibler gestalten (verschiedene Quellfelder)
3. JsonFormatExporter erweitern (name_json ausgeben)
4. Ingest-Trigger: Scheduler + manuell statt Observers

### Was im Plan fehlt:
1. LocalizesResponse Trait existiert bereits und loest name_json auf
2. Zwei Export-Pfade (JsonFormatExporter + DatasetBuilder)
3. Produkt-Uebersetzungen nutzen anderes System (product_attribute_values.language)
4. Empfehlung: name_json direkt in PIM-DB befuellen (Option A)

---

## 7. Kritische Dateien fuer Implementierung

### PIM-Backend:
- `app/Services/Export/JsonFormatExporter.php`
- `app/Services/Export/ExportService.php`
- `app/Services/Export/MappingResolver.php`
- `app/Http/Traits/LocalizesResponse.php` (keine Aenderung noetig)
- `app/Jobs/` (neue TMS-Jobs)
- `config/database.php` (Redis DB-Slot)

### PIM-Frontend:
- `pim-frontend/src/router/index.js`
- `pim-frontend/src/components/layout/AppSidebar.vue`
- `pim-frontend/src/views/translations/` (neu)
- `pim-frontend/src/api/` (TMS API-Client)
