---
title: KI & Automatisierung
---

# KI & Automatisierung

anyPIM bündelt mehrere KI-gestützte Funktionen, die die tägliche Arbeit mit Produktdaten beschleunigen — vom dialogbasierten Assistenten über die semantische Schnellsuche bis zur automatischen Erzeugung von Social-Media-Videos.

## Übersicht

| Funktion | Beschreibung | Voraussetzung |
|---|---|---|
| [**Copilot**](/de/ki/copilot) | In-App-Chat-Assistent, der lesend auf das PIM zugreift und Änderungen mit Bestätigung ausführt | Anthropic API-Key, MCP-Endpoint |
| [**Semantische Suche**](/de/ki/semantische-suche) | Natürlichsprachige Schnellsuche mit Constraint-Erkennung und Hybrid-Ranking | Meilisearch (optional mit Embedder) |
| [**Reel-Generator**](/de/ki/reel-generator) | Erzeugt Social-Media-Produktvideos (Reels/Shorts) inkl. KI-Sprechertext aus PIM-Daten | Video-Engine, optional Claude & ElevenLabs |

## Gemeinsame Konzepte

Alle KI-Funktionen folgen denselben Grundprinzipien:

- **Bestehende PIM-Daten als Quelle:** Es werden keine separaten Datentöpfe gepflegt. Attribute, Medien, Preise und Hierarchien sind die Grundlage.
- **Human-in-the-Loop:** Schreibende Aktionen (z. B. über den Copilot) werden dem Nutzer vor der Ausführung zur Bestätigung vorgelegt.
- **Deterministisch wo möglich:** Die semantische Suche extrahiert Zahlen, Einheiten und Preise regelbasiert (ohne LLM) und nutzt KI nur dort, wo sie echten Mehrwert bringt.
- **Optionale Aktivierung:** Jede Funktion lässt sich unabhängig über Umgebungsvariablen und Berechtigungen ein- oder ausschalten. Fehlt ein API-Key, bleibt die jeweilige Funktion deaktiviert bzw. degradiert auf einen lokalen Fallback.

::: tip Hinweis
Die KI-Funktionen sind über das Berechtigungssystem geschützt. Welche Rolle welche Funktion sehen und ausführen darf, ist unter [Rollen & Berechtigungen](/de/administration/rollen) konfigurierbar.
:::
