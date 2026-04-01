# Spec-Datei reviewen

Prüfe eine technische Spezifikation aus `docs/architecture/` gegen die aktuelle Codebase.

## Ablauf

1. Frage den Nutzer welche Spec geprüft werden soll (01-19, oder Dateiname)
2. Lies die Spec-Datei aus `docs/architecture/`
3. Analysiere die beschriebenen Komponenten:

## Prüfpunkte

### Vollständigkeit
- [ ] Alle beschriebenen Tabellen existieren als Migrations
- [ ] Alle beschriebenen API-Endpoints existieren in `routes/api.php`
- [ ] Alle beschriebenen Models existieren in `app/Models/`
- [ ] Alle beschriebenen Controller existieren

### Konsistenz
- [ ] Tabellennamen in Spec = Tabellennamen in Migrations
- [ ] Spalten in Spec = Spalten in Migrations
- [ ] API-Routen in Spec = Routen in api.php
- [ ] Beschriebene Features sind tatsächlich implementiert

### Abweichungen
- Welche Teile der Spec sind noch **nicht implementiert**?
- Welche implementierten Features fehlen in der **Spec**?
- Gibt es **Widersprüche** zwischen Spec und Code?

## Ausgabe

Gib einen strukturierten Report:

```
## Spec-Review: {Dateiname}

### Status: {Vollständig / Teilweise / Veraltet}

### Implementiert
- ...

### Nicht implementiert
- ...

### Nicht in Spec dokumentiert
- ...

### Empfehlungen
- ...
```
