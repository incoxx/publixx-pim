# anyPIM — API Referenz

Base-URL: `/api/v1`
Authentifizierung: Laravel Sanctum (Bearer Token)

## Authentifizierung

Alle Endpoints (ausser Auth/Login, Catalog, Health und Debug) erfordern einen Bearer Token:

```
Authorization: Bearer <token>
```

### Auth

| Methode | Pfad | Beschreibung |
|---|---|---|
| POST | `/auth/login` | Login (public) |
| POST | `/auth/logout` | Logout |
| POST | `/auth/refresh` | Token erneuern |
| GET | `/auth/me` | Aktueller Benutzer |

**Login-Request:**
```json
{ "email": "admin@example.com", "password": "password" }
```

**Login-Response:**
```json
{ "token": "1|abc123...", "user": { "id": "...", "email": "..." } }
```

---

## Health (public)

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/health` | Systemstatus aller Services |

**Response (200 = healthy, 503 = degraded):**
```json
{
  "status": "healthy",
  "timestamp": "2025-01-15T10:30:00+00:00",
  "checks": {
    "app": { "status": "ok", "environment": "production" },
    "database": { "status": "ok", "response_ms": 1.2 },
    "cache": { "status": "ok", "driver": "redis", "response_ms": 0.8 },
    "storage": { "status": "ok" },
    "queue": { "status": "ok", "horizon": "running" },
    "disk": { "status": "ok", "free_gb": 120.5, "used_percent": 25 }
  }
}
```

---

## Produkte

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/products` | Produktliste (paginiert) |
| POST | `/products` | Produkt erstellen |
| GET | `/products/{id}` | Produkt anzeigen |
| PUT | `/products/{id}` | Produkt aktualisieren |
| DELETE | `/products/{id}` | Produkt loeschen |
| GET | `/products/{id}/preview` | Produkt-Vorschau (alle Daten) |
| GET | `/products/{id}/completeness` | Vollstaendigkeits-Analyse |
| GET | `/products/{id}/preview/export.xlsx` | Excel-Export |
| GET | `/products/{id}/preview/export.pdf` | PDF-Export |

### Produkt-Attributwerte

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/products/{id}/attribute-values` | Attributwerte lesen |
| GET | `/products/{id}/resolved-attributes` | Attribute mit Hierarchie-Vererbung |
| PUT | `/products/{id}/attribute-values` | Attributwerte speichern (Bulk) |

`resolved-attributes` akzeptiert optionalen Query-Parameter `?hierarchy_node_id=UUID` fuer Live-Preview.

**Attributwerte speichern:**
```json
{
  "values": [
    { "attribute_id": "uuid", "value": "Wert", "language": "de" },
    { "attribute_id": "uuid", "value": 18.0 }
  ]
}
```

### Varianten

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/products/{id}/variants` | Varianten auflisten |
| POST | `/products/{id}/variants` | Variante erstellen |
| GET | `/products/{id}/variant-rules` | Vererbungsregeln lesen |
| PUT | `/products/{id}/variant-rules` | Vererbungsregeln setzen |

### Versionen

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/products/{id}/versions` | Versionen auflisten |
| POST | `/products/{id}/versions` | Version erstellen |
| GET | `/products/{id}/versions/{v}` | Version anzeigen |
| POST | `/products/{id}/versions/{v}/activate` | Version aktivieren |
| POST | `/products/{id}/versions/{v}/schedule` | Version terminieren |
| POST | `/products/{id}/versions/{v}/cancel-schedule` | Terminierung abbrechen |
| POST | `/products/{id}/versions/{v}/revert` | Auf Version zuruecksetzen |
| GET | `/products/{id}/versions/compare` | Versionen vergleichen |

---

## Hierarchien

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/hierarchies` | Alle Hierarchien |
| POST | `/hierarchies` | Hierarchie erstellen |
| GET | `/hierarchies/{id}` | Hierarchie anzeigen |
| PUT | `/hierarchies/{id}` | Hierarchie aktualisieren |
| DELETE | `/hierarchies/{id}` | Hierarchie loeschen |
| GET | `/hierarchies/{id}/tree` | Baumstruktur |

### Knoten

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/hierarchies/{id}/nodes` | Knoten auflisten |
| POST | `/hierarchies/{id}/nodes` | Knoten erstellen |
| GET | `/hierarchy-nodes/{id}` | Knoten anzeigen |
| PUT | `/hierarchy-nodes/{id}` | Knoten aktualisieren |
| DELETE | `/hierarchy-nodes/{id}` | Knoten loeschen |
| PUT | `/hierarchy-nodes/{id}/move` | Knoten verschieben |
| POST | `/hierarchy-nodes/{id}/duplicate` | Knoten duplizieren |

### Knoten-Attributzuweisungen

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/hierarchy-nodes/{id}/attributes` | Zugewiesene Attribute (+ `?inherited=true`) |
| POST | `/hierarchy-nodes/{id}/attributes` | Attribut zuweisen |
| PUT | `/node-attribute-assignments/{id}` | Zuweisung aktualisieren |
| DELETE | `/node-attribute-assignments/{id}` | Zuweisung entfernen |
| PUT | `/node-attribute-assignments/bulk-sort` | Sortierung aktualisieren |

### Knoten-Attributwerte

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/hierarchy-nodes/{id}/attribute-values` | Werte lesen |
| PUT | `/hierarchy-nodes/{id}/attribute-values` | Werte speichern (Bulk) |

---

## Attribute

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/attributes` | Alle Attribute |
| POST | `/attributes` | Attribut erstellen |
| GET | `/attributes/{id}` | Attribut anzeigen |
| PUT | `/attributes/{id}` | Attribut aktualisieren |
| DELETE | `/attributes/{id}` | Attribut loeschen |

**Datentypen:** `String`, `Number`, `Float`, `Date`, `Flag`, `Selection`, `Collection`, `Composite`, `Dictionary`

### Attributtypen (Gruppen)

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/attribute-types` | Alle Typen |
| POST | `/attribute-types` | Typ erstellen |
| GET/PUT/DELETE | `/attribute-types/{id}` | CRUD |

### Attribut-Views

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/attribute-views` | Alle Views |
| POST | `/attribute-views` | View erstellen |
| GET/PUT/DELETE | `/attribute-views/{id}` | CRUD |
| POST | `/attribute-views/{id}/attributes` | Attribut zuweisen |
| DELETE | `/attribute-views/{id}/attributes/{attr_id}` | Attribut entfernen |

---

## Einheiten

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/unit-groups` | Einheitengruppen |
| POST | `/unit-groups` | Gruppe erstellen |
| GET/PUT/DELETE | `/unit-groups/{id}` | CRUD |
| GET | `/unit-groups/{id}/units` | Einheiten der Gruppe |
| POST | `/unit-groups/{id}/units` | Einheit erstellen |
| GET/PUT/DELETE | `/units/{id}` | CRUD |

---

## Wertelisten

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/value-lists` | Alle Wertelisten |
| POST | `/value-lists` | Liste erstellen |
| GET/PUT/DELETE | `/value-lists/{id}` | CRUD |
| GET | `/value-lists/{id}/entries` | Eintraege |
| POST | `/value-lists/{id}/entries` | Eintrag erstellen |
| GET/PUT/DELETE | `/value-list-entries/{id}` | CRUD |

---

## Medien

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/media` | Medienliste |
| POST | `/media` | Medium hochladen (multipart/form-data) |
| GET | `/media/{id}` | Medium anzeigen |
| PUT | `/media/{id}` | Medium aktualisieren |
| DELETE | `/media/{id}` | Medium loeschen |
| GET | `/media/file/{filename}` | Datei ausliefern (public) |
| GET | `/media/thumb/{id}` | Thumbnail (public) |
| GET | `/media/diagnostics` | Speicherdiagnose |

### Medien-Attributwerte

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/media/{id}/attribute-values` | Attributwerte |
| PUT | `/media/{id}/attribute-values` | Attributwerte speichern |

### Produkt-Medien

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/products/{id}/media` | Zugewiesene Medien |
| POST | `/products/{id}/media` | Medium zuweisen |
| DELETE | `/product-media/{id}` | Zuweisung entfernen |

### Medien-Verwendungstypen

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/media-usage-types` | Alle Verwendungstypen |
| POST/GET/PUT/DELETE | `/media-usage-types/{id}` | CRUD |

---

## Preise

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/price-types` | Preistypen |
| POST/GET/PUT/DELETE | `/price-types/{id}` | CRUD |
| GET | `/products/{id}/prices` | Produktpreise |
| POST | `/products/{id}/prices` | Preis erstellen |
| PUT | `/product-prices/{id}` | Preis aktualisieren |
| DELETE | `/product-prices/{id}` | Preis loeschen |

---

## Relationen

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/relation-types` | Beziehungstypen |
| POST/GET/PUT/DELETE | `/relation-types/{id}` | CRUD |
| GET | `/products/{id}/relations` | Produktbeziehungen |
| POST | `/products/{id}/relations` | Beziehung erstellen |
| DELETE | `/product-relations/{id}` | Beziehung loeschen |

### Relation-Attributwerte

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/product-relations/{id}/attribute-values` | Werte lesen |
| PUT | `/product-relations/{id}/attribute-values` | Werte speichern |

---

## Produkttypen

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/product-types` | Alle Produkttypen |
| POST/GET/PUT/DELETE | `/product-types/{id}` | CRUD |
| GET | `/product-types/{id}/schema` | Attribut-Schema |

---

## PQL (Product Query Language)

| Methode | Pfad | Beschreibung |
|---|---|---|
| POST | `/pql/query` | PQL-Abfrage ausfuehren |
| POST | `/pql/query/count` | Treffer zaehlen |
| POST | `/pql/query/validate` | Syntax validieren |
| POST | `/pql/query/explain` | Abfrageplan anzeigen |

**Beispiel:**
```json
{ "query": "sku = 'PD-18V-001' AND status = 'active'" }
```

---

## Import

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/imports/templates/{type}` | Import-Template herunterladen |
| GET | `/imports/export-format` | Format-Beschreibung |
| POST | `/imports` | Import starten (multipart) |
| GET | `/imports/{id}` | Import-Status |
| GET | `/imports/{id}/preview` | Vorschau |
| POST | `/imports/{id}/execute` | Import ausfuehren |
| GET | `/imports/{id}/result` | Ergebnis |
| DELETE | `/imports/{id}` | Import loeschen |

---

## Export

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/export/products` | Exportierbare Produkte |
| GET | `/export/products/{id}` | Einzelprodukt-Export |
| POST | `/export/products/bulk` | Massenexport |
| GET | `/export/products/{id}/publixx` | Publixx-Format |
| POST | `/export/query` | Export per PQL-Abfrage |

---

## Publixx Live-API

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/publixx/datasets/{mapping}` | Datensaetze (nach Mapping) |
| GET | `/publixx/datasets/{mapping}/{product}` | Einzelner Datensatz |
| POST | `/publixx/datasets/{mapping}/pql` | PQL-Abfrage |
| POST | `/publixx/webhook` | Webhook empfangen |

---

## PXF-Templates

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/pxf-templates` | Alle Templates |
| POST | `/pxf-templates` | Template erstellen |
| POST | `/pxf-templates/import` | Template importieren |
| GET/PUT/DELETE | `/pxf-templates/{id}` | CRUD |
| GET | `/pxf-templates/{id}/preview/{product}` | Vorschau fuer Produkt |

---

## Benutzer & Rollen

### Benutzer

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/users` | Alle Benutzer |
| POST | `/users` | Benutzer erstellen |
| GET/PUT/DELETE | `/users/{id}` | CRUD |

### Rollen

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/roles` | Alle Rollen |
| POST | `/roles` | Rolle erstellen |
| GET/PUT/DELETE | `/roles/{id}` | CRUD |
| PUT | `/roles/{id}/permissions` | Berechtigungen setzen |

---

## Katalog (public)

Oeffentliche Endpoints ohne Authentifizierung:

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/catalog/products` | Produktliste |
| GET | `/catalog/products/{id}` | Produkt |
| GET | `/catalog/products/{id}/json` | Produkt als JSON |
| GET | `/catalog/products/export.json` | Alle Produkte als JSON |
| GET | `/catalog/categories` | Kategorien |

---

## Asset-Katalog (public)

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/asset-catalog/assets` | Assets durchsuchen |
| GET | `/asset-catalog/assets/{id}` | Einzelnes Asset |
| GET | `/asset-catalog/folders` | Ordnerstruktur |
| POST | `/asset-catalog/download` | Download-Paket erstellen |

---

## Debug (nur Test-Server)

| Methode | Pfad | Beschreibung |
|---|---|---|
| GET | `/debug/logs` | Laravel-Log anzeigen (`?channel=laravel&lines=500`) |
| GET | `/debug/logs/clear` | Log leeren |

---

## Admin

| Methode | Pfad | Beschreibung |
|---|---|---|
| POST | `/admin/reset-data` | Alle Daten zuruecksetzen |
| POST | `/admin/load-demo-data` | Demo-Daten laden |
| GET | `/admin/deploy/status` | Deployment-Status |
| POST | `/admin/deploy` | Deployment starten |
| POST | `/admin/deploy/rollback` | Rollback |

---

## Allgemeine Query-Parameter

Die meisten Listen-Endpoints unterstuetzen:

| Parameter | Beschreibung | Beispiel |
|---|---|---|
| `page` | Seitennummer | `?page=2` |
| `perPage` | Eintraege pro Seite | `?perPage=50` |
| `sort` | Sortierung | `?sort=-created_at` |
| `filter[field]` | Filterung | `?filter[status]=active` |
| `include` | Relations einbinden | `?include=attributes,media` |
| `lang` | Sprachfilter | `?lang=de,en` |
