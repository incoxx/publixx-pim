---
name: fix-and-test
description: Bug fixen und Test dafür schreiben (Quality First)
disable-model-invocation: true
---

# Bug fixen + Test schreiben

Quality First: Jeder Fix bekommt einen Test, der den Bug reproduziert und die Lösung verifiziert.

## Input

`$ARGUMENTS` = Beschreibung des Bugs, Fehlermeldung, oder betroffene Datei/Funktion.

## Ablauf

### Phase 1: Bug verstehen

1. **Fehlermeldung analysieren** — Stack-Trace, Error-Typ, betroffene Zeile
2. **Betroffenen Code lesen** — Verstehen was passiert und was passieren sollte
3. **Root Cause identifizieren** — Nicht nur Symptom, sondern Ursache

### Phase 2: Test schreiben (ZUERST)

Schreibe den Test **bevor** du den Fix implementierst (TDD-Ansatz):

**Backend (PHPUnit):**
```php
// tests/Feature/{Domain}/{Entity}Test.php oder tests/Unit/Services/{Service}Test.php
public function test_{beschreibung_des_bugs}(): void
{
    // Arrange: Setup der Situation die den Bug auslöst
    // Act: Aktion die den Fehler produziert
    // Assert: Erwartetes Verhalten (schlägt aktuell fehl)
}
```

Konventionen:
- Feature-Tests in `tests/Feature/{Domain}/` (API-Calls, DB-Interaktion)
- Unit-Tests in `tests/Unit/Services/` (isolierte Logik)
- `use RefreshDatabase;` für DB-Tests
- `$this->postJson()`, `$this->getJson()` für API-Tests
- `Http::fake()` für externe Services
- `declare(strict_types=1)`

**Frontend (Vitest):**
```javascript
// pim-frontend/src/{bereich}/__tests__/{datei}.test.js
describe('{Komponente/Store}', () => {
    it('should {erwartetes_verhalten}', () => {
        // Arrange → Act → Assert
    })
})
```

### Phase 3: Fix implementieren

1. **Minimaler Fix** — Nur das Problem lösen, kein Refactoring nebenbei
2. **Test erneut laufen lassen** — Muss jetzt grün sein
3. **Keine Regression** — Bestehende Tests müssen weiter bestehen

### Phase 4: Verifizierung

```bash
# Backend
vendor/bin/phpunit tests/Feature/{Domain}/{Test}.php
vendor/bin/phpunit  # Alle Tests

# Frontend
cd pim-frontend && npm run test:run

# Code-Style
vendor/bin/pint --test
```

## Häufige Bug-Muster in anyPIM

### Null-Safety
```php
// Problem: array offset on null
$value = $data['key'];
// Fix:
$value = $data['key'] ?? null;
```

### Permission-Lücken
```php
// Problem: Fehlende Gate-Prüfung
public function destroy(Request $request, Model $model) {
    $model->delete(); // Keine Berechtigung geprüft!
}
// Fix:
public function destroy(Request $request, Model $model) {
    $this->authorize('delete', $model);
    $model->delete();
}
```

### Race Conditions / Rate Limiting
```php
// Problem: Schnelles Klicken löst mehrere API-Calls aus
// Fix: Debounce im Frontend oder Lock im Backend
```

### Media/Asset-Fehler
```php
// Problem: file_path null bei Import
// Fix: Null-Check + Fallback oder Skip
```

## Checkliste

- [ ] Root Cause verstanden (nicht nur Symptom gefixt)
- [ ] Test geschrieben der den Bug reproduziert
- [ ] Test war rot BEVOR der Fix implementiert wurde
- [ ] Fix ist minimal und fokussiert
- [ ] Alle bestehenden Tests bestehen weiterhin
- [ ] Code-Style (`vendor/bin/pint --test`) passt
- [ ] `declare(strict_types=1)` in neuen Dateien
