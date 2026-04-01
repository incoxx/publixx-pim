---
name: add-attribute-type
description: Neuen Attribut-Datentyp zum PIM hinzufügen (Backend + Frontend + Export)
disable-model-invocation: true
---

# Neuen Attribut-Datentyp anlegen

Erweitere das anyPIM Attribut-System um einen neuen Datentyp.

## Schritt 1: Anforderungen klären

Frage den Nutzer:
1. **Typ-Name** (z.B. "Hyperlink", "ColorPicker", "GeoLocation")
2. **Speicher-Strategie**: Welches `value_*` Feld? (`value_string`, `value_text`, `value_numeric`, `value_json`)
3. **Datenstruktur**: Einfacher Wert oder JSON-Objekt?
4. **Übersetzbar?** (mehrsprachig oder einsprachig)
5. **Validierungsregeln** (z.B. URL-Format, Farbcode-Pattern)
6. **Export-Verhalten**: Wie soll der Wert in Exports erscheinen?

## Schritt 2: Backend

### 2a. Attribute Model erweitern — `app/Models/Attribute.php`

- Neuen Typ zur `DATA_TYPES` Konstante hinzufügen
- Type-spezifische Cast-Logik falls nötig

### 2b. Validation — `app/Http/Requests/Api/V1/StoreAttributeRequest.php`

- Validation-Regeln für den neuen Typ
- `UpdateAttributeRequest.php` ebenfalls anpassen

### 2c. ProductAttributeValue — `app/Models/ProductAttributeValue.php`

- Sicherstellen dass der Wert korrekt gespeichert/gelesen wird
- Accessor/Mutator falls JSON-Struktur

### 2d. Migration (falls neues Feld nötig)

- Nur wenn kein bestehendes `value_*` Feld passt
- `$table->json('value_{typ}')->nullable()` o.ä.

### 2e. Export-Support — `app/Services/Export/MappingResolver.php`

- Prüfen ob bestehende Mapping-Typen ausreichen
- Falls nicht: Neuen Mapping-Typ in `resolve()` hinzufügen

## Schritt 3: Frontend

### 3a. Input-Komponente — `pim-frontend/src/components/attributes/{Type}Input.vue`

Vue 3 Component mit:
- Props: `modelValue`, `attribute`, `disabled`, `errors`
- Emits: `update:modelValue`
- v-model Binding

### 3b. Display-Komponente — `pim-frontend/src/components/attributes/{Type}Display.vue`

Readonly-Darstellung für Listen und Detail-Ansichten.

### 3c. Registrierung

Den neuen Typ in der Attribut-Typ-Registry eintragen (Component-Map die Typ-Name → Komponente zuordnet).

## Schritt 4: Tests

### Backend
```php
// tests/Feature/Api/AttributeTest.php — Erweitern:
public function test_can_create_{type}_attribute(): void { ... }
public function test_can_store_{type}_value(): void { ... }
public function test_{type}_validation_rejects_invalid(): void { ... }
```

### Frontend
```javascript
// pim-frontend/src/components/attributes/__tests__/{Type}Input.test.js
describe('{Type}Input', () => {
    it('renders correctly', () => { ... })
    it('emits update on change', () => { ... })
    it('validates input', () => { ... })
})
```

## Schritt 5: Checkliste

- [ ] Typ in `Attribute::DATA_TYPES` registriert
- [ ] Validation in Store/Update Requests
- [ ] ProductAttributeValue Speicherung funktioniert
- [ ] Export via MappingResolver korrekt
- [ ] Frontend Input-Komponente
- [ ] Frontend Display-Komponente
- [ ] Komponente in Typ-Registry eingetragen
- [ ] Backend-Tests geschrieben
- [ ] `declare(strict_types=1)`
- [ ] i18n: Labels übersetzbar

## Referenz

Bestehende Typen als Vorlage:
- Einfach: `value_string` Typen (Text, URL)
- JSON: `value_json` Typen (DelimitedValue, JsonArtefact, Composite)
- Selection: Typen mit Wertlisten (Selection, MultiSelection)
