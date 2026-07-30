---
title: Konnektoren & Integrationen
---

# Konnektoren & Integrationen

Über **Konnektoren** verbindet anyPIM Produktdaten und Medien mit externen Systemen — Shop-Systemen, Asset-Diensten sowie KI- und Übersetzungs-APIs. Das Framework verwaltet Zugangsdaten verschlüsselt, protokolliert Synchronisationen und unterstützt sowohl einzelne als auch stapelweise Syncs.

## Verfügbare Konnektoren

| Konnektor | Zweck |
|---|---|
| **Shopware 6** | Kategorien, Produkte, Eigenschaften und Medien synchronisieren |
| **Shopify** | Produkte, Kategorien, Metafelder und Medien synchronisieren |
| **anyPIM** | Bidirektionaler Abgleich mit anderen anyPIM-Instanzen (inkl. Übersetzungen) |
| **DeepL** | Maschinelle Übersetzung (u. a. für [Übersetzungsjobs](/de/bedienung/uebersetzungsjobs)) |
| **Claude AI** | KI-Textgenerierung (u. a. für [Copilot](/de/ki/copilot) und [Reel-Generator](/de/ki/reel-generator)) |

## Einrichtung

1. Navigation: **Konnektoren → Plugin-Einstellungen**. Dort die Zugangsdaten des gewünschten Dienstes hinterlegen (verschlüsselt gespeichert).
2. Auf der Konnektor-Karte **„Verbinden"** wählen. Bei OAuth-Diensten (Shopware, Shopify, anyPIM) öffnet sich die Autorisierung; die Verbindung erscheint anschließend unter **„Verbindungen"**.

## Synchronisieren (Shop-Systeme)

1. Eine Verbindung öffnen und ein **Website-Profil** wählen, das den Produktumfang bestimmt.
2. Zuordnungen (z. B. Shopware-Eigenschaften ↔ PIM-Attribute) festlegen.
3. Synchronisation ausführen:
   - **Profil-Sync** — alle Produkte des Profils
   - **Delta-Sync** — nur geänderte Produkte (über Prüfsummen erkannt)
   - **Einzelprodukt** — direkt aus der Produktansicht
4. Im **Sync-Protokoll** Erfolge/Fehler einsehen (auch als Excel exportierbar). Wartungsaktionen (z. B. *Shop zurücksetzen*, *Kategorien/Medien entfernen*) stehen je Konnektor bereit.

## Berechtigungen & Modul

Der Konnektor-Bereich ist über das Modul `connectors` freigeschaltet und über eigene Rechte gesteuert (*ansehen*, *verwalten*, *synchronisieren*). Administratoren haben vollen Zugriff. Konfiguration unter [Rollen & Berechtigungen](/de/administration/rollen).

::: tip Marketingüberblick
Eine produktorientierte Übersicht der Integrationen findet sich unter [Integrationen](/de/marketing/integrationen).
:::
