---
title: Geplante Aktionen
---

# Geplante Aktionen

**Geplante Aktionen** führen Produktoperationen zu einem festgelegten Zeitpunkt aus — etwa eine Aktivierung zum Verkaufsstart oder eine Preisänderung zum Stichtag. Sie erscheinen gemeinsam mit Export-Jobs, geplanten Produktversionen und Projektterminen im [Planungskalender](/de/erweitert/planungskalender).

## Aktionstypen

| Typ | Wirkung |
|---|---|
| **Aktivierung** | Produkt zum Zeitpunkt aktivieren |
| **Deaktivierung** | Produkt deaktivieren |
| **Preisänderung** | Preis(e) setzen (Preistyp, Betrag, Währung) |
| **Datenänderung** | Attributwerte ändern |
| **Export** | einen Export-Job auslösen |

## Aktion anlegen

1. In der Produktdetailansicht den Reiter **„Geplante Aktionen"** öffnen.
2. **„Neue Aktion"** wählen und ausfüllen:
   - **Titel** der Aktion
   - **Aktionstyp** (siehe Tabelle)
   - **Geplant am** (Zeitpunkt in der Zukunft)
   - typabhängige **Nutzdaten** (z. B. Preistyp + Betrag bei einer Preisänderung)
   - optional **Farbe** (für die Kalenderdarstellung) und **Zuständigkeit**
3. Speichern — die Aktion erscheint mit Statusanzeige (*Ausstehend, In Bearbeitung, Abgeschlossen, Fehlgeschlagen*).

## Im Kalender

Im [Planungskalender](/de/erweitert/planungskalender) sind alle geplanten Aktionen in der Monats-, Wochen- oder Tagesansicht sichtbar und nach Typ und Status filterbar. Ausstehende Aktionen lassen sich direkt bearbeiten; Export-Jobs, Versionsfreigaben und Projekttermine werden zur Orientierung ebenfalls eingeblendet (schreibgeschützt).

::: warning Ausführung
Die zeitgesteuerte Ausführung erfordert einen laufenden Scheduler/Worker (siehe [Cronjobs & Planung](/de/installation/cronjobs)).
:::

## Berechtigungen

Das Ansehen erfordert das Recht, Produkte zu sehen; das Anlegen/Ändern das Recht, Produkte zu bearbeiten. Konfiguration unter [Rollen & Berechtigungen](/de/administration/rollen).
