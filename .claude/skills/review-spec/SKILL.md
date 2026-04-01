---
name: review-spec
description: Technische Spec-Datei gegen die Codebase prüfen
disable-model-invocation: true
---

# Spec-Datei reviewen

Prüfe eine technische Spezifikation aus `docs/architecture/` gegen die aktuelle Codebase.

## Input

Argument `$ARGUMENTS` = Spec-Nummer oder Dateiname (z.B. "03" oder "03-pql-engine").

Falls kein Argument: Zeige die verfügbaren Specs aus `docs/architecture/` und frage den Nutzer.

## Ablauf

1. Spec-Datei aus `docs/architecture/` lesen
2. Beschriebene Komponenten identifizieren (Tabellen, Endpoints, Models, Services)
3. Gegen Codebase prüfen:

### Prüfpunkte

**Datenbank:**
- [ ] Beschriebene Tabellen existieren als Migrations in `database/migrations/`
- [ ] Spalten in Spec = Spalten in Migrations
- [ ] Indizes und Constraints stimmen überein

**API:**
- [ ] Beschriebene Endpoints existieren in `routes/api.php`
- [ ] HTTP-Methoden stimmen (GET/POST/PUT/DELETE)
- [ ] Request-Validation deckt beschriebene Felder ab

**Models:**
- [ ] Beschriebene Models existieren in `app/Models/`
- [ ] Relationships stimmen überein
- [ ] Fillable/Casts korrekt

**Services:**
- [ ] Beschriebene Services existieren
- [ ] Methoden-Signaturen stimmen

**Tests:**
- [ ] Tests vorhanden in `tests/Feature/` oder `tests/Unit/`

## Ausgabe

```markdown
## Spec-Review: {Dateiname}

### Status: {Vollständig | Teilweise implementiert | Veraltet}

### Implementiert (X/Y)
- ...

### Nicht implementiert
- ...

### Im Code aber nicht in Spec
- ...

### Abweichungen
- ...

### Empfehlungen
- ...
```
