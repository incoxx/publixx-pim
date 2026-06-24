---
title: Sheet-Designer (Excel)
---

# Sheet-Designer (Excel-Vorlagen)

Der **Sheet-Designer** ist ein grafischer Editor für Excel-Exportvorlagen. Sie gestalten mehrblättrige `.xlsx`-Exporte mit frei wählbaren Spalten, Gruppierung und Formatierung und steuern den Produktumfang über ein Suchprofil.

## Vorlage erstellen

1. Navigation: **Sheet-Designer** → Liste der eigenen und geteilten Vorlagen.
2. **„Neues Template"** anlegen.
3. Im Editor Spalten zusammenstellen — die Feldauswahl bietet:
   - **Basisfelder** (SKU, Name, EAN …)
   - **Attribute** (nach Datentyp)
   - **Preise** (nach Preistyp)
   - **Medien** (nach Verwendungstyp)
   - **Beziehungen** (nach Beziehungstyp)
4. Optional mehrere **Blätter** und eine **Gruppierung** definieren.
5. Ein **Suchprofil** verknüpfen, um den Produktumfang zu filtern.

## Vorschau, Export & Import

- **Vorschau** rendert eine Mini-Tabelle mit wenigen Produkten.
- **Download** erzeugt die Excel-Datei. Große Datenmengen laufen als **asynchroner Export-Job** mit Fortschrittsanzeige und Abbrechen-Funktion; die fertige Datei wird anschließend zum Download bereitgestellt.
- **Import** liest eine vorhandene `.xlsx`-Struktur ein und legt daraus automatisch eine Vorlage an.

::: tip Verwandte Funktionen
Für reine Datenübergaben siehe [JSON-Export](/de/export/json-export); für Bestelldaten den [Publixx-Export](/de/export/publixx-export).
:::

## Berechtigungen

Eigene Rechte steuern Ansehen, Anlegen, Bearbeiten und Löschen von Vorlagen. Nicht-geteilte Vorlagen sind nur für ihren Ersteller sichtbar. Konfiguration unter [Rollen & Berechtigungen](/de/administration/rollen).
