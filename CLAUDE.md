# anyPIM — Agent-Konventionen

## Architektur: Zentrale Mapping-Engine + Format-Writer

```
┌─────────────────────────────────────────────────────────────┐
│  PIM-Quelldaten                                             │
│  (Produkte, Attribute, Preise, Medien, Relationen)          │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  Mapping-Engine  (MappingResolver — existiert bereits)      │
│                                                             │
│  Quellfelder:              Zielfelder (per ElementMap):     │
│  ├─ attribute:tech_name    ├─ Format-spezifisch             │
│  ├─ prices:price_type      │  (aus {Format}ElementMap)      │
│  ├─ media:usage_type       │                                │
│  ├─ relations:rel_type     │                                │
│  └─ collection:coll_name   │                                │
│                                                             │
│  Mapping-Regeln (PublixxExportMapping):                     │
│  [{ source, target, type }]                                 │
│                                                             │
│  11 Typen: text, unit_value, composite, media_url,          │
│  media_array, price, variant_array, relation_array, group   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  Gemapptes Dataset  (format-neutral, key→value)             │
└────────────────────────┬────────────────────────────────────┘
                         │
            ┌────────────┼────────────┬────────────┐
            ▼            ▼            ▼            ▼
      ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
      │ GAEB XML │ │ ONYX XML │ │ ETIM     │ │ FABDIS   │
      │ Writer   │ │ Writer   │ │ Writer   │ │ Writer   │
      └──────────┘ └──────────┘ └──────────┘ └──────────┘
         ▲ nur Serialisierung — keine eigene Mapping-Logik
```

**Kernprinzip:** Die Format-Exporter sind **dünne Writer**. Die gesamte Daten-Transformation
geschieht in der zentralen Mapping-Engine (`MappingResolver`). Jeder Format-Writer:

1. Lädt Produkte via `buildFilteredProductQuery()` (aus ExportProductHelpers)
2. Löst Mapping-Regeln via `MappingResolver::resolve()` auf → bekommt key→value Map
3. Serialisiert das gemappte Dataset ins Zielformat (XMLWriter, json_encode, etc.)

---

## Modulare Konventionen (`.claude/rules/`)

Detaillierte Arbeitsanweisungen werden automatisch geladen, wenn relevante Dateien bearbeitet werden:

| Rule-Datei | Wird geladen bei | Inhalt |
|------------|-----------------|--------|
| `.claude/rules/export-format-conventions.md` | `app/Services/Export/**`, `*ExportController.php`, `*Export.php` | 5-Dateien-Pattern, Pflicht-Regeln, Code-Templates |
| `.claude/rules/mapping-resolver.md` | `MappingResolver.php`, `*ElementMap.php` | Quellfelder, Mapping-Typen, Regel-Struktur |
| `.claude/rules/classification-architecture.md` | `Attribute*.php`, `HierarchyNode*.php`, `*attribute_mapping*` | Klassifikationen, attribute_mappings, KI-Mapping |

## Skills (`.claude/skills/`)

| Skill | Zweck | Trigger |
|-------|-------|---------|
| `/project:new-export-format` | Neues Export-Format anlegen (5-Dateien-Pattern) | Manuell |
| `/project:add-crud-feature` | Vollständiges CRUD-Feature (Backend + Frontend) | Manuell |
| `/project:add-connector` | Neuen Plattform-Connector integrieren | Manuell |
| `/project:add-attribute-type` | Neuen Attribut-Datentyp anlegen | Manuell |
| `/project:fix-and-test` | Bug fixen + Test schreiben (TDD) | Manuell |
| `/project:review-code` | Code-Review nach Qualitätsstandards | Manuell |
| `/project:review-spec` | Spec-Datei gegen Codebase prüfen | Manuell |

---

## Aktive Agenten & Merge-Reihenfolge

| # | Branch | Format | Merge |
|---|--------|--------|-------|
| 1 | `claude/gaeb-xml-export-XSnPV` | GAEB DA XML 3.3 | **Zuerst** |
| 2 | `claude/explore-onyx-format-XuGMq` | ONYX 3.0 (Buchhandel) | Danach |
| 3 | `claude/etim-classification-mapping-e4Y3i` | ETIM Klassifikation | Danach |
| 4 | `claude/fabdis-data-export-4c4Px` | FABDIS 2.0 | Zuletzt |

**Nach jedem Merge:** verbleibende Agenten rebasen auf `main`.

---

## Technologie-Stack

- **Backend:** PHP 8.4, Laravel 11
- **Frontend:** Vue 3, Vite, Tailwind CSS 4, DaisyUI 5
- **Datenbank:** MySQL 8+
- **Tests:** PHPUnit (Backend), Vitest (Frontend)
- **Code-Style:** `declare(strict_types=1)`, deutsche Kommentare/Beschreibungen

## Dokumentation

Alle technischen Docs befinden sich in `docs/`:

| Ordner | Inhalt |
|--------|--------|
| `docs/architecture/` | 19 nummerierte Spec-Dateien (Datenmodell, API, PQL, Media, i18n, ...) |
| `docs/operations/` | Deployment, Installation, Production, Updates |
| `docs/reference/` | API-Referenz, Datenbankschema, Testing-Strategie |
| `docs/guides/` | Anleitungen (Von 0 auf 100, Offline-App, Shortcuts) |
| `docs/features/` | Feature-Übersicht, Integrationsbericht, Pläne |
| `docs/sales/` | Verkaufsmappe, TMS-Validation |
