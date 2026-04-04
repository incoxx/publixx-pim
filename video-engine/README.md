# anyPIM Video Engine

Automatische Erzeugung von Lehr- und Demo-Videos aus YAML-Story-Definitionen.

## Voraussetzungen

### System-Tools

```bash
# ffmpeg (Video-Encoding)
sudo apt install ffmpeg

# Xvfb (Virtual Framebuffer)
sudo apt install xvfb

# Node.js >= 18
node --version

# Playwright Browser
cd video-engine && npx playwright install chromium
```

### Optional: Text-to-Speech

```bash
# gTTS (kostenloser Fallback)
pip3 install gtts

# ElevenLabs: API Key in .env setzen
```

## Installation

```bash
cd video-engine
cp .env.example .env
# .env anpassen (ANYPIM_BASE_URL, Demo-User etc.)
npm install
```

## Verwendung

### Alle Stories auflisten

```bash
php artisan pim:video-generate --list
```

### Systemcheck

```bash
php artisan pim:video-generate --preflight
# oder direkt:
cd video-engine && npx tsx src/preflight.ts
```

### Einzelne Story generieren

```bash
php artisan pim:video-generate --story=01-produktanlage
php artisan pim:video-generate --story=01-produktanlage --force  # Überschreiben
```

### Alle Stories generieren

```bash
php artisan pim:video-generate --all
php artisan pim:video-generate --all --force
```

### Shell-Scripts direkt

```bash
cd video-engine
bash scripts/generate-one.sh 01-produktanlage
bash scripts/generate-all.sh --force
```

## Verzeichnisstruktur

```
video-stories/
  _schema/story.schema.json    ← JSON Schema
  _template/story.yaml         ← Vorlage für neue Stories
  01-produktanlage/story.yaml  ← Story-Definitionen
  02-kategoriebaum/story.yaml
  03-pql-suche/story.yaml
  demo-assets/                 ← Platzhalter-Bilder

video-engine/
  src/                         ← TypeScript-Module
  scripts/                     ← Shell-Scripts + Hilfsskripte
  SELECTORS.md                 ← Alle data-testid Selektoren
  README.md                    ← Diese Datei

public/videos/                 ← Fertige Videos (.mp4 + .srt)
storage/video-engine/          ← Logs, Temp, Locks
```

## Neue Story anlegen

1. Verzeichnis kopieren: `cp -r video-stories/_template video-stories/04-meine-story`
2. `story.yaml` anpassen (meta.id = Verzeichnisname!)
3. Validieren: `cd video-engine && npx tsx src/story-validator.ts 04-meine-story`
4. Generieren: `php artisan pim:video-generate --story=04-meine-story`

## Demo-Daten

```bash
# DemoVideoSeeder ausführen (baut auf bestehenden Seedern auf)
php artisan db:seed --class=DemoVideoSeeder
```

## Fehlerbehebung

- **Xvfb startet nicht**: Prüfe ob Display :99 frei ist (`ps aux | grep Xvfb`)
- **Playwright Browser fehlt**: `npx playwright install chromium`
- **Story-Validierung schlägt fehl**: `npx tsx src/story-validator.ts <story-id>`
- **Kein Audio**: ElevenLabs API Key prüfen oder gTTS installieren
- **Lock-Datei blockiert**: Prüfe `storage/video-engine/lock/` auf stale Locks
