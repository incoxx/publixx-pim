---
title: Portale (Dokumentenportal & Asset-Katalog)
---

# Portale

Mit **Portalen** stellen Sie ausgewählte Produktinformationen öffentlich bereit — ohne Login oder mit Zugangsschutz. anyPIM bietet einen konfigurierbaren Portal-Builder sowie zwei einsatzfertige Portaltypen: das **Dokumentenportal** und den **Asset-Katalog**.

## Portal-Builder (Portal-Konfiguration)

Unter **Portale** legen Sie Portale an und gestalten sie über mehrere Reiter:

- **Einstellungen** — Name, eindeutiger Slug, Standardsprache, verknüpfte Katalog-Vorlage, *aktiv* (veröffentlicht) und *geteilt* (sichtbar für andere).
- **Branding** — Titel, Untertitel, Hero-Text und Feature-Liste.
- **Filter-Steps** — schrittweise Filterführung über Widgets (Länderauswahl, Sprachauswahl, Filter-Dropdown, Filter-Karten).
- **HTML-Template** — eigenes HTML mit Platzhaltern für die Portal-Widgets.
- **CSS** — eigenes Styling.

Eine **Live-Vorschau** (Desktop/Tablet/Mobil) zeigt das Ergebnis. Aus Vorlagen lassen sich Portale per Klick erstellen oder bestehende duplizieren. Das veröffentlichte Portal ist unter `…/portal/{slug}` erreichbar.

## Dokumentenportal

Das Dokumentenportal stellt Produktdokumente (z. B. Gebrauchsanweisungen, Broschüren) nach **Land** und **Sprache** bereit:

1. Land auswählen.
2. Produkt per SKU, EAN oder Name suchen.
3. Das Portal zeigt das Hauptdokument sowie die nach Dokumenttyp gruppierten Dateien — umschaltbar nach Sprache.

## Asset-Katalog

Der Asset-Katalog ist eine durchsuchbare Medienbibliothek:

- **Ordnerbaum** mit Asset-Anzahl je Ordner.
- **Suche** über Dateiname, Titel, Beschreibung, Attribute und Hierarchie-Namen — inklusive phonetischer Treffer (Kölner Phonetik).
- **Filter** nach Verwendungszweck (Print/Web) und Medientyp.
- **Detailansicht** mit Metadaten, zugehörigen Produkten und Hierarchie-Pfaden.
- **ZIP-Download** mehrerer Assets auf einmal.

## Zugriff & Schutz

Die öffentlichen Portalrouten sind über die Katalog-Zugriffssteuerung abgesichert (offen oder per Zugangsschutz). Die Verwaltung der Portale erfolgt im PIM; die Sichtbarkeit von Portal-Konfigurationen ist benutzer-/teambezogen.

::: tip Verwandte Funktionen
Für eingebettete Produktkataloge auf der eigenen Website siehe [Katalog-Embed](/de/erweitert/catalog-embed).
:::
