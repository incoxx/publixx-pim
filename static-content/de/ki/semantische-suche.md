---
title: Semantische Suche
---

# Semantische Suche

Die **semantische Schnellsuche** versteht natürlichsprachige Anfragen. Sie tippen z. B. *„Bohrmaschine unter 200 Euro"* oder *„8 Zoll Bildschirm in Rot"*, und anyPIM zerlegt die Anfrage automatisch in harte Filter (Preis, Maße, Gewicht …) und einen Suchtext, der per Hybrid-Verfahren (Stichwort + Vektor) gerankt wird.

## Was die Suche kann

- **Constraint-Erkennung:** Zahlen, Einheiten und Preise werden regelbasiert (ohne LLM) aus der Anfrage extrahiert und in exakte Filter übersetzt. *„unter 300 €"* wird zu `list_price ≤ 300`, *„8 Zoll"* zu einem Bereich `[7,6 … 8,4]` (Toleranz konfigurierbar).
- **Hybrid-Ranking:** Der verbleibende Suchtext wird gleichzeitig per Stichwort- und Vektorsuche bewertet (sofern ein Embedder aktiv ist).
- **Facetten:** Optional liefert die Suche Verteilungen über Attributwerte zurück.
- **Strukturierte Antwort:** Das Ergebnis enthält die gerankten Treffer, die angewandten Constraints (als Chips dargestellt) sowie nicht auflösbare Constraints — bewusst keine vom LLM erzeugte Prosa.

## Bedienung

Das Suchfeld **„Semantische Suche…"** (Funken-Symbol ✨) erscheint oben in der Navigationsleiste, sobald die Funktion aktiviert ist.

1. Anfrage eingeben — die Suche reagiert verzögert (Debounce) auf die Eingabe.
2. Im Dropdown erscheinen oben die erkannten **Constraints** als grüne Chips und darunter die **Treffer** (Bild, Name, SKU, Hierarchiepfad, Preis, Relevanz-Score).
3. Mit den Pfeiltasten navigieren, mit `Enter` ein Produkt öffnen.
4. Über *„Alle Ergebnisse in der Schnellsuche anzeigen"* gelangen Sie zur vollständigen Trefferliste (Tab **„Semantisch"**).

## Architektur

```
Anfrage
  → ConstraintExtractor (regelbasiert, kein LLM)
      ├─ Zahlen + Einheiten → harte Meilisearch-Filter
      └─ Resttext → Suchtext
  → HybridSearchService
      ├─ kein Suchtext      → Modus „filter"  (nur Filter)
      ├─ Embedder aus       → Modus „keyword" (nur Stichwort)
      └─ sonst              → Modus „hybrid"  (Stichwort + Vektor)
  → Meilisearch
  → strukturierte JSON-Antwort (Treffer, Constraints, Facetten, Modus)
```

Die Suche basiert auf **Meilisearch**. Produkte werden in einem denormalisierten Index abgelegt (Name, Beschreibung, durchsuchbarer Text, Preis, Hierarchiepfad sowie je ein `attr_*`-Feld pro filterbarem Attribut). Die Filter-Vokabeln stammen dynamisch aus den PIM-Attributen und Einheiten — es ist nichts fest verdrahtet.

::: tip Funktioniert auch ohne Embedder
Ist kein Embedder konfiguriert oder aktiv, degradiert die Suche automatisch auf reine Stichwortsuche. Die Constraint-Erkennung funktioniert in jedem Fall.
:::

### Abgrenzung zu Typesense

Meilisearch (Produktsuche) und [Typesense](/de/installation/typesense) (Volltextsuche in PDF-Dokumenten) ergänzen sich und ersetzen einander nicht. Meilisearch beantwortet *„Zeig mir Produkte, die zu dieser Beschreibung passen"*, Typesense *„In welchem PDF steht diese Textstelle?"*.

## Einrichtung & Verwaltung

### Umgebungsvariablen

```bash
MEILISEARCH_ENABLED=false
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_API_KEY=
MEILISEARCH_INDEX=products

# Embedder (optional, für semantische/Vektor-Suche)
MEILISEARCH_EMBEDDER_ENABLED=true
MEILISEARCH_EMBEDDER_SOURCE=ollama          # ollama | openAi | rest | huggingFace
MEILISEARCH_EMBEDDER_MODEL=nomic-embed-text
MEILISEARCH_EMBEDDER_URL=http://localhost:11434/api/embeddings
MEILISEARCH_EMBEDDER_API_KEY=

# Verhalten
MEILISEARCH_SEMANTIC_RATIO=0.5              # 0 = nur Stichwort, 1 = nur Vektor
MEILISEARCH_NUMERIC_TOLERANCE=0.05          # Toleranz, z. B. „8 Zoll" → [7,6 … 8,4]
```

::: warning Aktivierung
Setzen Sie `MEILISEARCH_ENABLED=true` und konfigurieren Sie `MEILISEARCH_HOST`. Ist die Funktion deaktiviert, antworten die Endpunkte mit `503`. Der nomic-Embedder-Standard setzt einen laufenden [Ollama](https://ollama.com)-Dienst voraus.
:::

### Konsolenbefehle

```bash
# Index anlegen/aktualisieren (Attribute, Filter, Embedder)
php artisan pim:meili-setup [--no-embedder] [--fresh-schema]

# Produkte indexieren (inkrementell; --all für Vollindex)
php artisan pim:meili-index [--all] [--since="2026-06-01 00:00"] [--chunk=500]
```

Die Indexierung erfolgt in Chunks (Standard 500 Produkte) ohne N+1-Abfragen. Embeddings werden von Meilisearch asynchron und nur für geänderte Dokumente berechnet.

### Administration in der Oberfläche

Im Admin-Bereich gibt es eine Statusansicht mit Diagnose (Erreichbarkeit von Meilisearch und Ollama, geladenes Modell, Dokumentanzahl, vorhandene Embeddings, fehlgeschlagene Tasks). Wartungsaktionen (`pull-model`, `setup`, `index-all`) lassen sich als Hintergrund-Job starten und der Fortschritt abfragen.

## Berechtigungen

| Berechtigung | Bedeutung |
|---|---|
| `semantic-search.view` | Semantische Suche verwenden |
| `meilisearch-admin.view` | Status & Diagnose im Admin-Bereich einsehen |
| `meilisearch-admin.manage` | Setup/Indexierung/Modell-Download anstoßen |

`semantic-search.view` ist standardmäßig breit vergeben (u. a. Admin, Data Steward, Product Manager, Export Manager, Viewer). Die `meilisearch-admin.*`-Berechtigungen erhalten nur Sysadmin und Admin.
