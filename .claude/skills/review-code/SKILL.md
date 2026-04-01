---
name: review-code
description: Code-Review nach anyPIM-Qualitätsstandards durchführen
disable-model-invocation: true
---

# Code-Review

Führe ein gründliches Code-Review nach anyPIM-Standards durch.

## Input

`$ARGUMENTS` = Dateipfad, Branch-Name, oder Beschreibung des zu reviewenden Codes.

Falls kein Argument: Prüfe `git diff` für unstaged Changes oder `git diff --cached` für staged Changes.

## Review-Kriterien

### 1. Sicherheit (KRITISCH)

- [ ] Keine SQL-Injection (Eloquent/Query Builder nutzen, kein raw SQL ohne Bindings)
- [ ] Keine XSS (Blade-Escaping, keine `{!! !!}` ohne Grund)
- [ ] Input-Validation in allen Controller-Methoden (FormRequest)
- [ ] Authorization/Gate-Checks vorhanden
- [ ] Keine Secrets im Code (API-Keys, Passwörter → .env)
- [ ] CSRF-Schutz bei State-ändernden Routen

### 2. Architektur

- [ ] Folgt dem Service-Layer-Pattern (keine Business-Logik im Controller)
- [ ] Traits korrekt verwendet (ExportProductHelpers, HasUuids)
- [ ] Keine Code-Duplikation (existierende Helpers nutzen)
- [ ] Separation of Concerns (Model ≠ Controller ≠ Service)
- [ ] Keine gemeinsam genutzten Dateien unnötig geändert

### 3. Performance

- [ ] N+1 Queries vermieden (eager loading mit `with()`, `whenLoaded()` in Resources)
- [ ] Chunking bei großen Datenmengen (`chunk(500, ...)`)
- [ ] Kein unnötiges `->get()` vor `->paginate()`
- [ ] Indizes für häufig abgefragte Spalten in Migrations
- [ ] StreamedResponse für große Downloads (kein Memory-Buffer)

### 4. Code-Qualität

- [ ] `declare(strict_types=1)` in allen PHP-Dateien
- [ ] Keine Magic Numbers (Konstanten verwenden)
- [ ] Error-Handling: try/catch wo nötig, sprechende Fehlermeldungen
- [ ] Keine leeren catch-Blöcke
- [ ] Konsistente Namenskonventionen (PascalCase Klassen, camelCase Methoden, kebab-case Routes)

### 5. Frontend (Vue/JS)

- [ ] Pinia Store statt lokaler State für geteilte Daten
- [ ] API-Client über `client.js` (nicht axios direkt)
- [ ] `ref()` / `computed()` korrekt verwendet
- [ ] Error-Handling in async Actions
- [ ] Keine hardcodierten Strings (i18n verwenden)

### 6. Tests

- [ ] Neue Features haben Tests
- [ ] Bug-Fixes haben Regressions-Tests
- [ ] Tests sind isoliert (RefreshDatabase, Http::fake())
- [ ] Assertions sind aussagekräftig

## Ausgabe-Format

```markdown
## Code-Review: {Datei/Branch}

### Bewertung: {Approved | Changes Requested | Needs Discussion}

### Kritisch (muss gefixt werden)
- ...

### Empfohlen (sollte gefixt werden)
- ...

### Optional (Nice-to-have)
- ...

### Positiv
- ...
```

## Schweregrade

| Grad | Bedeutung | Aktion |
|------|-----------|--------|
| KRITISCH | Sicherheitslücke, Datenverlust, Production-Breaking | Sofort fixen |
| EMPFOHLEN | Performance, Code-Qualität, fehlende Tests | Vor Merge fixen |
| OPTIONAL | Style, Naming, Vereinfachungen | Kann auch später |
