# 20 — Virtuelle Referenzprodukte (Referenz-Profile)

## Idee

Ein **virtuelles Referenzprodukt** (Referenz-Profil) beschreibt eine Produktgruppe
— z.B. „Akkubohrschrauber" — vollständig über **Prüfregeln** für Attributwerte.
Es ist wie eine *abstrakte Klasse für Produktdaten*: Ein konkretes Produkt
referenziert das Profil und wird gegen dessen Regeln geprüft.

- **Schritt 1 (umgesetzt):** rein regelbasierter, deterministischer Check.
  „Hier fehlt ein Wert", „Wert nicht plausibel".
- **Schritt 2 (vorbereitet):** Produkt + Referenz + strukturierter Abweichungs-Report
  an eine KI übergeben, die die Abweichungen *erklärt* (nicht selbst findet).

## Scope: am Produkt

Das Produkt referenziert **genau ein** Profil (`products.reference_profile_id`).
Bewusst nicht über Hierarchie/ProductType: ein Knoten wie „Serviceteile" kann
heterogene Teile enthalten, das Profil lässt sich also nicht ableiten. Das Produkt
**deklariert** sein Sollbild selbst (`class X extends Akkubohrschrauber`).

## Vererbung

Profile erben über `parent_profile_id`. Beim Check werden die Regeln entlang der
Kette **Root → Blatt** gemerged; das spezifischste Profil gewinnt bei gleichem
Schlüssel (`attribute + check`). **Abstrakte** Profile (`is_abstract = true`) sind
nur Basis und können nicht direkt von einem Produkt referenziert werden.

```
Elektrowerkzeug (abstract)   → voltage, weight, CE
  └─ Akkubohrschrauber       → + torque, battery_type
  └─ Akku-Winkelschleifer    → + disc_diameter, rpm
```

## Datenmodell

| Tabelle | Zweck |
|---------|-------|
| `product_reference_profiles` | Profil: name, parent_profile_id, is_abstract, version, `rules` (JSON), `golden_product_ids` (JSON) |
| `products.reference_profile_id` | Referenz des Produkts auf genau ein Profil |
| `product_conformance_results` | Letztes Prüfergebnis pro Produkt (unique product_id) |

## Regel-Format (`rules` JSON)

```json
[
  { "attribute": "torque",  "check": "required", "severity": "error" },
  { "attribute": "voltage", "check": "between", "min": 10.8, "max": 18, "unit": "V", "severity": "warning" },
  { "attribute": "weight",  "check": "max", "value": 3, "unit": "kg", "severity": "info" },
  { "attribute": "battery", "check": "in_list", "values": ["li-ion","nimh"], "severity": "error" }
]
```

`attribute` = `Attribute.technical_name`. Verfügbare Checks: `required`, `not_empty`,
`min`, `max`, `between`, `in_list`, `max_length`, `regex`. Severities: `error`,
`warning`, `info`. Leere Werte werden nur von `required`/`not_empty` gemeldet
(keine Doppel-Meldung mit Bereichsregeln).

## Prüf-Trigger

| Trigger | Mechanismus |
|---------|-------------|
| **on-save** | `ProductObserver` + `AttributeValueObserver` → `RunConformanceCheck` (async) |
| **on-demand** | `POST /products/{id}/conformance/check` (synchron) |
| **Batch** | `RecheckProfileProducts` Job + `php artisan pim:conformance-recheck` |

**Versionssprung:** Ändert sich `rules`, wird `version` erhöht und automatisch ein
**Batch-Re-Check** aller referenzierenden Produkte (inkl. abgeleiteter Profile)
angestoßen. Veraltete Ergebnisse erkennt die GUI über `result.profile_version < profile.version`
(`is_stale`).

## Ergebnis & Status

`status` = `fail` (≥1 error), `warning` (≥1 warning), sonst `pass`.
`score` = Anteil erfüllter Regeln (0–100). `deviations` (JSON) enthält je Abweichung
`attribute`, `check`, `severity`, `expected`, `actual`, `message` — zugleich der
Input für die KI-Erklärung (Schritt 2).

## API

| Methode | Route | Zweck |
|---------|-------|-------|
| `GET` | `reference-profiles` | Profile auflisten |
| `POST` | `reference-profiles` | Profil anlegen |
| `GET` | `reference-profiles/{id}` | Profil + effektive (vererbte) Regeln |
| `PUT` | `reference-profiles/{id}` | Profil ändern (Regeländerung → Re-Check) |
| `DELETE` | `reference-profiles/{id}` | Profil löschen (blockiert bei Nutzung) |
| `POST` | `reference-profiles/{id}/recheck` | Batch-Re-Check anstoßen |
| `GET` | `products/{id}/conformance` | Letztes Ergebnis (Produkt-Tab) |
| `POST` | `products/{id}/conformance/check` | On-demand-Neuprüfung |
| `PUT` | `products/{id}/reference-profile` | Profil zuweisen/entfernen |
| `GET` | `conformance/report` | Aggregierter Report über den Bestand |

## Kern-Engine

`app/Services/Conformance/ProfileConformanceChecker.php`
- `effectiveRules(profile)` — Regel-Merge inkl. Vererbung (Zyklen-/Tiefenschutz)
- `evaluateRule(rule, actual)` — reine, testbare Regelauswertung
- `check(product, trigger)` — prüft & persistiert das letzte Ergebnis

## Schritt 2 (KI) — vorbereitet, noch nicht umgesetzt

Die KI erhält den fertigen `deviations`-Report + `golden_product_ids` (echte
Vorbild-Produkte) als Kontext und erklärt die Abweichungen samt Korrekturvorschlag.
Deterministik = Wahrheit/Gating, KI = Sprache/Begründung.
