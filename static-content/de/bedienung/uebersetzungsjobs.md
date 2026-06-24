---
title: Übersetzungsjobs (XLIFF)
---

# Übersetzungsjobs & XLIFF

Während die [Übersetzungen](/de/bedienung/uebersetzungen) das direkte Pflegen mehrsprachiger Werte beschreiben, bündeln **Übersetzungsjobs** die stapelweise Übersetzung vieler Attribute und System-Texte — automatisch per DeepL oder ausgelagert an eine Agentur über das XLIFF-Format.

## Übersetzungsjob anlegen

1. Navigation: **Übersetzungen → Übersetzungsjobs → „Neuer Job"**.
2. Felder ausfüllen:
   - **Name** des Jobs
   - **Quellsprache** und **Zielsprache**
   - **Scope**: *products* (Produkte + Attribute auswählen), *system* (System-Objekte wie Attribute, Wertlisten, Hierarchien, Produkttypen …) oder *mixed*
   - optional **Filter** zur Eingrenzung der Produkte
3. Der Job wird im Status **Entwurf** angelegt.

## Lebenszyklus

```
Entwurf ──absenden──► Wartend ──► In Bearbeitung ──► Abgeschlossen ──freigeben──► im PIM
                                          └──► Fehlgeschlagen ──erneut starten──► …
```

- **Absenden** — übergibt den Job an DeepL (es muss eine aktive [DeepL-Verbindung](/de/erweitert/konnektoren) konfiguriert sein). Die Verarbeitung läuft asynchron.
- **Freigabe** — übernimmt die fertigen Übersetzungen in die Produktdaten.
- **Abbrechen** / **Erneut starten** — laufende bzw. fehlgeschlagene Jobs steuern.

## XLIFF-Export/-Import (Agentur-Workflow)

Für die Übersetzung durch externe Dienstleister lassen sich die übersetzbaren Inhalte als **XLIFF 1.2** exportieren und nach der Übersetzung wieder importieren:

1. **Export** — übersetzbare Attribute als XLIFF-Datei herunterladen (optional auf bestimmte Produkte und das Sprachpaar eingegrenzt).
2. Die Agentur übersetzt die `<target>`-Elemente und liefert die Datei zurück.
3. **Import** — die ausgefüllte XLIFF-Datei hochladen; die Werte werden den passenden Produktattributen zugeordnet. Das Ergebnis meldet importierte, übersprungene und fehlerhafte Einträge.

::: tip Voraussetzung DeepL
Die automatische Übersetzung benötigt eine konfigurierte DeepL-Verbindung unter [Konnektoren](/de/erweitert/konnektoren). Der XLIFF-Workflow funktioniert auch ohne DeepL.
:::

## Zugriff

Einzelne Jobs sind nur für den Ersteller und für Administratoren zugänglich. Die Verarbeitung läuft als Queue-Job — stellen Sie sicher, dass ein Worker läuft (siehe [Cronjobs & Planung](/de/installation/cronjobs)).
