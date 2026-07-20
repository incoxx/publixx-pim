---
title: Attribut-Mapping
---

# Attribut-Mapping

Das **Attribut-Mapping** ordnet Quell-Attribute auf Ziel-Attribute einer anderen Klassifikation/Hierarchie zu — etwa von den eigenen Stammdaten auf die Merkmale einer extern importierten Klassifikationshierarchie. Es ist die Grundlage dafür, dasselbe Produkt in mehreren Klassifikationen korrekt auszugeben, ohne Werte mehrfach zu pflegen.

## Funktionsweise

1. Navigation: **Attribut-Mapping**.
2. **Quell-Schema** und **Ziel-Schema** (jeweils eine Hierarchie) auswählen.
3. In der Zuordnungstabelle je Ziel-Attribut ein Quell-Attribut und einen **Transformationstyp** festlegen:

| Transformation | Beschreibung |
|---|---|
| **Direkt (1:1)** | Wert unverändert übernehmen |
| **Einheiten-Umrechnung** | Wert zwischen Einheiten umrechnen (z. B. mm → cm) |
| **Wert-Zuordnung** | Werte einander zuordnen (z. B. eigene Auswahlwerte → Standard-Wertliste) |

### Bedingte Regeln

Zusätzlich lassen sich **Regeln** definieren, die abhängig von einer Bedingung (Attribut, Operator, Wert) ein oder mehrere Ziel-Attribute setzen.

## Werte synchronisieren

Die zugeordneten Werte werden in die Ziel-Attribute der Produkte übernommen:

- **Einzelnes Produkt** synchronisieren — mit sofortiger Rückmeldung (angelegt/aktualisiert/übersprungen).
- **Auswahl** synchronisieren — ausgewählte Produkte sofort verarbeiten.
- **Stapel** synchronisieren — viele Produkte asynchron als Hintergrund-Job.

::: tip Vorrang manueller Werte
Beim Export gewinnt ein direkt am Produkt gepflegter Wert (klassifikationsspezifisch) immer gegenüber einem per Mapping berechneten Wert. So bleiben manuelle Korrekturen erhalten.
:::

## Excel-Export/-Import

Zuordnungen und Regeln lassen sich als Excel-Datei (zwei Blätter: *Mappings*, *Rules*) exportieren, extern bearbeiten und wieder importieren (Upsert).

## Berechtigungen

Eigene Rechte steuern Ansehen, Anlegen und Löschen von Mappings und Regeln. Konfiguration unter [Rollen & Berechtigungen](/de/administration/rollen). Hintergründe zur Klassifikationsarchitektur finden sich in der internen Architektur-Dokumentation.
