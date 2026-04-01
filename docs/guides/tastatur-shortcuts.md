# anyPIM — Tastatur-Shortcuts

## Globale Shortcuts

Diese Shortcuts funktionieren überall im PIM (außer in öffentlichen Katalog-Routen).

### Aktionen

| Shortcut | Aktion | Beschreibung |
|----------|--------|-------------|
| `Ctrl+K` | Command Palette | Öffnet die Funktionssuche (alle Menüpunkte durchsuchbar) |
| `Ctrl+S` | Speichern | Speichert das aktuelle Formular (Produkteditor, Einstellungen, etc.) |
| `Ctrl+N` | Neues Element | Legt ein neues Element an (kontextabhängig) |
| `/` | Suche fokussieren | Fokussiert das Suchfeld (nur außerhalb von Eingabefeldern) |
| `Esc` | Schließen | Schließt Dialoge, Panels und Modals |

### Navigation (Ctrl+Shift+Buchstabe)

Schnellnavigation zu den wichtigsten Bereichen. Funktioniert nur außerhalb von Eingabefeldern.

| Shortcut | Ziel | Route |
|----------|------|-------|
| `Ctrl+Shift+F` | **Schnellsuche** (Finden) | `/quick-search` |
| `Ctrl+Shift+P` | **Produkte** | `/products` |
| `Ctrl+Shift+H` | **Hierarchien** | `/hierarchies` |
| `Ctrl+Shift+M` | **Medien** | `/media` |
| `Ctrl+Shift+A` | **Attribute** | `/attributes` |
| `Ctrl+Shift+E` | **Export** | `/exports` |
| `Ctrl+Shift+I` | **Import** | `/imports` |
| `Ctrl+Shift+D` | **Dashboard** | `/dashboard` |
| `Ctrl+Shift+W` | **Workflow** | `/workflow` |

> **Hinweis:** Mac-Nutzer verwenden `Cmd` statt `Ctrl`.

## Schnellsuche-Shortcuts

Innerhalb der Schnellsuche (`/quick-search`):

| Shortcut | Aktion |
|----------|--------|
| `↑` / `↓` | Durch Ergebnisse navigieren |
| `Enter` | Ausgewähltes Ergebnis öffnen |
| `Esc` | Suche leeren |

## Eselsbrücken

| Buchstabe | Deutsch | Englisch |
|-----------|---------|----------|
| **F** | **F**inden (Schnellsuche) | **F**ind |
| **P** | **P**rodukte | **P**roducts |
| **H** | **H**ierarchien | **H**ierarchies |
| **M** | **M**edien | **M**edia |
| **A** | **A**ttribute | **A**ttributes |
| **E** | **E**xport | **E**xport |
| **I** | **I**mport | **I**mport |
| **D** | **D**ashboard | **D**ashboard |
| **W** | **W**orkflow | **W**orkflow |

## Technische Implementierung

- **Datei:** `pim-frontend/src/App.vue` — globaler `keydown`-Handler
- **Hilfe-Seite:** `pim-frontend/src/views/HelpView.vue` — Shortcut-Tabelle
- Browser-Shortcuts (`Ctrl+T`, `Ctrl+W`, etc.) werden nicht überschrieben
- `Ctrl+Shift` statt `Ctrl` für Navigation, um Konflikte zu vermeiden
- Shortcuts sind inaktiv in `<input>`, `<textarea>`, `<select>` und `contentEditable`
