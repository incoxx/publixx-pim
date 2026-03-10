# Plan: 4 neue Hyperlink-Datentypen für Attribute

## Übersicht
Vier neue Datentypen hinzufügen: **Hyperlink**, **ImageLink**, **PdfLink**, **VideoLink**.
Werte werden als JSON in `value_string` gespeichert. Titel und Alt-Text sind übersetzbar.

## Entscheidungen
- **4 separate Enum-Werte** in der `data_type` Spalte
- **JSON-Speicherung** in bestehender `value_string` Spalte
- **Übersetzbar**: Titel und Alt-Text (via `is_translatable` + `language` Spalte)

### JSON-Struktur pro Typ

**Hyperlink:**
```json
{ "url": "https://...", "target": "_blank|_self", "title": "Linktext" }
```

**ImageLink:**
```json
{ "url": "https://...", "title": "Bildtitel", "alt_text": "Beschreibung", "width": 800, "height": 600 }
```

**PdfLink:**
```json
{ "url": "https://...", "title": "PDF-Titel", "target": "_blank|_self" }
```

**VideoLink:**
```json
{ "url": "https://...", "title": "Video-Titel", "width": 1920, "height": 1080 }
```

---

## Schritt 1: Datenbank-Migration
**Neue Datei:** `database/migrations/2026_03_10_000001_add_link_data_types_to_attributes.php`

- Erweitert die `data_type` ENUM um: `Hyperlink`, `ImageLink`, `PdfLink`, `VideoLink`
- Down-Migration: Konvertiert bestehende Link-Attribute zu `String`, entfernt Enum-Werte

---

## Schritt 2: Backend – Validierung & Konstanten

### 2a. SheetValidator.php
- `VALID_DATA_TYPES` Array erweitern um die 4 neuen Typen

### 2b. StoreAttributeRequest.php
- `data_type` Regel erweitern: `in:...,Hyperlink,ImageLink,PdfLink,VideoLink`

### 2c. UpdateAttributeRequest.php
- Gleiche Erweiterung der `data_type` Regel

---

## Schritt 3: Backend – Attributwert-Verarbeitung

### 3a. ProductAttributeValueController.php → resolveValueColumns()
- Neue Cases im `match` für alle 4 Link-Typen → `value_string` (JSON wird direkt als String gespeichert)

### 3b. ImportExecutor.php → mapValueToColumns()
- Link-Typen in den `default` Branch fallen lassen (value_string), kein Extracode nötig

### 3c. FlatImportExecutor.php → mapValueToColumns()
- Gleich wie 3b: fällt in `default` → value_string

### 3d. ImportFormatExporter.php → resolveAttributeValue()
- Kein Extracode nötig: value_string wird bereits zurückgegeben

### 3e. JsonFormatExporter.php → resolveAttributeValue()
- Für Link-Typen: JSON-String als decoded Objekt zurückgeben statt als rohen String

---

## Schritt 4: Frontend – Datentyp-Optionen

### 4a. AttributeFormPanel.vue
- 4 neue Optionen in der data_type Dropdown hinzufügen

### 4b. ProductDetailView.vue → mapDataTypeToInput()
- Neue Mappings: `Hyperlink` → `hyperlink`, `ImageLink` → `imagelink`, `PdfLink` → `pdflink`, `VideoLink` → `videolink`

---

## Schritt 5: Frontend – Neue Eingabekomponenten

### 5a. PimAttributeInput.vue
- 4 neue Input-Typen mit jeweils passenden Formularfeldern:
  - **hyperlink**: URL (text), Target (select: _blank/_self), Titel (text)
  - **imagelink**: URL (text), Titel (text), Alt-Text (text), Breite (number), Höhe (number)
  - **pdflink**: URL (text), Titel (text), Target (select: _blank/_self)
  - **videolink**: URL (text), Titel (text), Breite (number), Höhe (number)
- Interne Verwaltung als JSON-Objekt, emit als JSON-String

---

## Schritt 6: Frontend – BulkEditorView.vue
- Neue Cases für die 4 Link-Typen in der Zellenrendering-Logik
- Kompakte Darstellung: URL als Text-Input, Bearbeiten-Button für erweiterte Felder

---

## Schritt 7: Tests
- Bestehende Feature-Tests überprüfen und ggf. erweitern
- Sicherstellen, dass Attribute mit den neuen Typen erstellt/aktualisiert werden können
- JSON-Validierung in resolveValueColumns() testen

---

## Betroffene Dateien (Zusammenfassung)

| Datei | Änderung |
|-------|----------|
| `database/migrations/2026_03_10_000001_...` | **NEU** – Enum erweitern |
| `app/Services/Import/SheetValidator.php` | VALID_DATA_TYPES erweitern |
| `app/Http/Requests/Api/V1/StoreAttributeRequest.php` | data_type Regel |
| `app/Http/Requests/Api/V1/UpdateAttributeRequest.php` | data_type Regel |
| `app/Http/Controllers/Api/V1/ProductAttributeValueController.php` | resolveValueColumns() |
| `app/Services/Export/JsonFormatExporter.php` | resolveAttributeValue() |
| `pim-frontend/src/components/panels/AttributeFormPanel.vue` | Dropdown-Optionen |
| `pim-frontend/src/views/products/ProductDetailView.vue` | mapDataTypeToInput() |
| `pim-frontend/src/components/shared/PimAttributeInput.vue` | 4 neue Input-Typen |
| `pim-frontend/src/views/products/BulkEditorView.vue` | Zellenrendering |
