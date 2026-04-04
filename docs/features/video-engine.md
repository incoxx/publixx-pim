# anyPIM Video Engine

Automatische Erzeugung von Lehr- und Demo-Videos direkt aus dem anyPIM-Projekt.
Stories werden als YAML definiert, alles andere – Script, Untertitel, Video – wird automatisch erzeugt.

## Übersicht

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   story.yaml     │────▶│  Video Engine     │────▶│  Fertiges Video  │
│   (YAML-Datei)   │     │  (TypeScript)     │     │  (.mp4 + .srt)   │
└──────────────────┘     └──────────────────┘     └──────────────────┘
                                │
                    ┌───────────┼───────────┐
                    ▼           ▼           ▼
              Playwright    ffmpeg     Untertitel
              (Browser)    (Aufnahme)    (SRT)
```

### Pipeline

1. **Story validieren** – YAML gegen JSON Schema prüfen
2. **DemoVideoSeeder** – Reproduzierbare Demodaten erzeugen
3. **Playwright-Script generieren** – Story-Steps → ausführbares Browser-Script
4. **Aufnehmen** – Xvfb (virtueller Bildschirm) + ffmpeg (Screen Capture) + Playwright (Browser-Steuerung)
5. **Untertitel generieren** – SRT aus echten Aufnahme-Timestamps (synchron mit Video)
6. **Voiceover** (optional) – ElevenLabs oder gTTS Text-to-Speech
7. **Video rendern** – ffmpeg Merge: Video + Audio + Untertitel
8. **Output** – Fertiges MP4 nach `public/videos/`

## Story-Format (YAML)

Jede Story liegt in einem eigenen Verzeichnis unter `video-stories/`:

```
video-stories/
  _schema/story.schema.json     ← JSON Schema für Validierung
  _template/story.yaml          ← Vorlage für neue Stories
  01-produktanlage/story.yaml   ← Beispiel-Story
  02-kategoriebaum/story.yaml
  03-pql-suche/story.yaml
  demo-assets/                  ← Platzhalter-Bilder
```

### YAML-Struktur

```yaml
meta:
  id: "01-produktanlage"                # Muss dem Verzeichnisnamen entsprechen
  title: "Produkt anlegen"              # Angezeigt in --list
  description: "Beschreibung..."        # Kurze Beschreibung
  version: "1.0.0"                      # Für Versionierung
  duration_estimate: 90                 # Geschätzte Dauer in Sekunden
  tags:                                 # Optionale Tags
    - grundlagen
    - produkte
  voice:                                # Stimme-Konfiguration
    lang: "de"                          # Sprache (de, en, fr)
    gender: "female"                    # male | female
    provider: "elevenlabs"              # elevenlabs | gtts
    voice_id: ""                        # ElevenLabs Voice ID (leer = Standard)
  viewport:                             # Browser-Auflösung
    width: 1920
    height: 1080
  slow_mo: 300                          # ms zwischen Playwright-Aktionen
  seeder: "DemoVideoSeeder"            # Laravel Seeder vor Aufnahme
  seeder_reset: true                    # DB zurücksetzen (nie in Produktion!)

steps:
  - id: "step-id"                       # Eindeutige ID pro Step
    action: navigate                    # Aktion (siehe unten)
    target: "/login"                    # URL für navigate
    selector: "[data-testid='...']"     # CSS-Selektor für Zielelement
    value: "Eingabewert"                # Wert für fill/select
    wait_for: "[data-testid='...']"     # Warten bis Element sichtbar
    wait_timeout: 5000                  # Timeout in ms (Standard: 5000)
    sprecher: "Sprechertext..."         # Text für Untertitel + Voiceover
    pause_after: 1000                   # Pause nach dem Step in ms
    type_speed: 30                      # ms pro Zeichen bei fill (0 = sofort)
```

### Verfügbare Actions

| Action | Beschreibung | Pflichtfelder |
|--------|-------------|---------------|
| `navigate` | URL aufrufen | `target` |
| `click` | Element klicken | `selector` |
| `fill` | Textfeld befüllen (Zeichen für Zeichen) | `selector`, `value` |
| `select` | Dropdown auswählen (nach Label oder Value) | `selector`, `value` |
| `select_tree` | Baumauswahl (Pfad mit ` > ` getrennt) | `selector`, `value` |
| `upload` | Datei hochladen | `selector`, `file` |
| `hover` | Maus über Element | `selector` |
| `scroll` | Scrollen | `selector` oder `direction` |
| `wait` | Warten | `duration` (ms) |
| `highlight` | Element visuell hervorheben (blauer Ring) | `selector` |
| `screenshot` | Screenshot erzwingen | — |
| `assert_visible` | Prüfen ob Element sichtbar | `selector` |

### Selektoren

Stories nutzen `data-testid`-Attribute für stabile Selektoren:

```yaml
# Direkt
selector: "[data-testid='btn-login']"

# Mit Kind-Element (Input innerhalb eines Feld-Wrappers)
selector: "[data-testid='field-sku'] input"

# Mit Kind-Element (Select innerhalb eines Feld-Wrappers)
selector: "[data-testid='field-product_type_id'] select"
```

PimForm-Felder haben automatisch `data-testid="field-{key}"` auf dem Wrapper-Element.
Die vollständige Liste aller Selektoren: `video-engine/SELECTORS.md`

### Sprecher-Text und Untertitel

Der `sprecher`-Text wird für zwei Dinge verwendet:

1. **Untertitel (.srt)** – Zeitgestempelt mit echten Aufnahme-Timestamps
2. **Voiceover (optional)** – Text-to-Speech via ElevenLabs oder gTTS

```yaml
# Mit Sprechertext
- id: "login"
  action: navigate
  target: "/login"
  sprecher: "Willkommen bei anyPIM. Heute zeigen wir die Produktanlage."

# Ohne Sprechertext (stille Aktion)
- id: "fill-password"
  action: fill
  selector: "[data-testid='input-password']"
  value: "${VIDEO_DEMO_USER_PASSWORD}"
  sprecher: ""
```

### Umgebungsvariablen in Stories

Werte aus `.env` können mit `${VAR_NAME}` referenziert werden:

```yaml
value: "${VIDEO_DEMO_USER_EMAIL}"      # → demo@anypim.local
value: "${VIDEO_DEMO_USER_PASSWORD}"   # → demo1234
```

## Verwendung

### Stories auflisten

```bash
php artisan pim:video-generate --list
```

### Systemcheck (Preflight)

```bash
php artisan pim:video-generate --preflight
```

Prüft: ffmpeg, Xvfb, Node.js, Playwright, anyPIM-Erreichbarkeit, Speicherplatz.

### Video generieren

```bash
# Einzelne Story
php artisan pim:video-generate --story=01-produktanlage

# Vorhandenes Video überschreiben
php artisan pim:video-generate --story=01-produktanlage --force

# Alle Stories
php artisan pim:video-generate --all --force
```

### Ergebnis

Videos werden nach `public/videos/` kopiert und sind über den Webserver erreichbar:

```
https://anypim.de/videos/01-produktanlage.mp4
https://anypim.de/videos/01-produktanlage.srt
```

## Neue Story erstellen

1. **Verzeichnis anlegen:**
   ```bash
   cp -r video-stories/_template video-stories/04-meine-story
   ```

2. **`story.yaml` bearbeiten:**
   - `meta.id` muss dem Verzeichnisnamen entsprechen (`04-meine-story`)
   - Steps definieren (Login, Navigation, Aktionen)
   - Sprecher-Texte für Untertitel

3. **data-testid prüfen:**
   - Sind die benötigten Selektoren in den Vue-Komponenten vorhanden?
   - Falls nicht: `data-testid="..."` Attribut hinzufügen + Frontend neu bauen

4. **Validieren:**
   ```bash
   cd video-engine && npx tsx src/story-validator.ts 04-meine-story
   ```

5. **Generieren:**
   ```bash
   php artisan pim:video-generate --story=04-meine-story
   ```

## Untertitel-Synchronisierung

Die SRT-Untertitel werden mit den echten Aufnahme-Timestamps synchronisiert:

1. Das Playwright-Script schreibt `timestamps.json` während der Aufnahme
2. Jeder Step-Start und -Ende wird mit Echtzeit-Millisekunden erfasst
3. Die SRT-Generierung nutzt diese Timestamps statt geschätzter Dauern
4. Fallback: Wenn keine `timestamps.json` existiert, werden Dauern geschätzt

## Architektur

```
video-engine/
  src/
    preflight.ts          ← Systemprüfung vor Start
    story-validator.ts    ← YAML gegen Schema validieren
    script-generator.ts   ← story.yaml → Playwright-Script + Timestamps
    recorder.ts           ← Xvfb + ffmpeg Steuerung
    subtitle-extractor.ts ← Timestamps → .srt (synchronisiert)
    voice-synthesizer.ts  ← ElevenLabs/gTTS → MP3
    video-renderer.ts     ← Video + Audio + SRT zusammenführen
    uploader.ts           ← Output-Management
    lock-manager.ts       ← Verhindert parallele Ausführung
    logger.ts             ← Strukturiertes Logging

  scripts/
    generate-one.sh       ← Einzelne Story generieren
    generate-all.sh       ← Alle Stories generieren
    create-demo-assets.ts ← Platzhalter-Bilder erzeugen
```

### Artisan Command

```
php artisan pim:video-generate
  --story=<id>    Einzelne Story
  --all           Alle Stories
  --force         Video überschreiben + Produktionsschutz umgehen
  --list          Stories auflisten
  --preflight     Nur Systemcheck
  --lang=de       Sprache (Zukunft)
```

### DemoVideoSeeder

Idempotenter Seeder der auf bestehenden Seedern aufbaut:

- Ruft `RoleAndPermissionSeeder`, `AdminUserSeeder`, `DemoProductSeeder` etc. auf
- Erstellt zusätzlich: `demo@anypim.local` mit Admin-Rolle
- Kann beliebig oft ausgeführt werden (keine Duplikate, kein Datenverlust)

## Voraussetzungen

| Tool | Zweck | Installation |
|------|-------|-------------|
| ffmpeg | Video-Encoding | `apt install ffmpeg` |
| Xvfb | Virtueller Bildschirm | `apt install xvfb` |
| Node.js ≥ 18 | TypeScript-Engine | Bereits vorhanden |
| Playwright | Browser-Steuerung | `npx playwright install chromium` |
| ElevenLabs (optional) | Voiceover | API Key in `.env` |
| gTTS (optional) | Fallback-Voiceover | `pip3 install gtts` |

Alle Tools werden automatisch durch `setup.sh` installiert.

## Umgebungsvariablen (.env)

```env
# Video Engine
VIDEO_ENGINE_ENABLED=true
VIDEO_ENGINE_OUTPUT_DIR=public/videos
VIDEO_ENGINE_DISPLAY=:99
VIDEO_ENGINE_FPS=30
VIDEO_ENGINE_QUALITY=high              # high | medium | low

# Demo-User
VIDEO_DEMO_USER_EMAIL=demo@anypim.local
VIDEO_DEMO_USER_PASSWORD=demo1234

# ElevenLabs (optional)
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_DE_FEMALE=
ELEVENLABS_FALLBACK=gtts               # gtts | silent
```
