---
title: Reel-Generator (Social-Video)
---

# Reel-Generator — Social-Media-Videos aus PIM-Daten

Mit dem **Reel-Generator** (intern „Social-Video") erzeugen Sie aus Produktdaten automatisch kurze Social-Media-Videos — Reels, Shorts oder Feed-Posts. Bilder, Texte und Preise stammen direkt aus dem PIM; ein optionaler KI-Sprechertext und eine animierte Kamerafahrt machen aus statischen Daten ein fertiges Video zum Herunterladen.

## Was der Generator erzeugt

Aus den ausgewählten Inhalten baut der Generator automatisch eine Szenenfolge:

- **Aufmacher** — Produktname und optionale Unterzeile auf dem Hauptbild
- **Feature-Szenen** (bis zu 3) — Attributname, formatierter Wert und Einheit (z. B. *„Bildschirmdiagonale: 9 Zoll"*)
- **Showcase-Szenen** — weitere Galerie-Bilder, während der Sprechertext weiterläuft
- **Preis-Szene** — großer animierter Preis mit Währung (falls gemappt)
- **CTA-Szene** — Handlungsaufforderung (z. B. *„Jetzt entdecken"* oder KI-generiert)

Formate: **9:16** (Reels/Shorts/TikTok), **1:1** (Feed-Post), **16:9** (YouTube).

## Bedienung

Aufruf über **Publish → Social-Video**.

1. **Produkte wählen** — über das Suchfeld ein oder mehrere Produkte auswählen.
2. **Inhalte zuordnen** — per Dropdown festlegen, welche Quelle welche Rolle übernimmt:
   - *Aufmacherbild* und *Galerie* (aus den Medien-Verwendungstypen)
   - *Überschrift*, *Unterzeile* und bis zu *3 Features* (aus Attributen)
   - *Preis* (aus einem Preistyp)
   - *3 KI-Kontext-Felder* — fließen **nur in den gesprochenen Text**, nicht ins sichtbare Video
3. **Look & Stil** — Akzent- und Hintergrundfarbe, Übergang (Weich/Slide/Zoom/Hart), Medien-Animation (Ken Burns, Zoom, Schwenk …), optionales *Kreativ-Briefing* und *Tonalität*. Presets (*Tech, Bold, Elegant, Frisch*) setzen diese Werte mit einem Klick.
4. **Kamerafahrt-Editor** — pro Produkt einen Start- und Endfokuspunkt sowie eine Zoomstufe festlegen. Eine Live-Vorschau zeigt die Kamerafahrt exakt so, wie sie im Video gerendert wird.
5. **KI-Hook** (optional) — Mit *„KI-Hook generieren (Claude)"* erzeugt Claude einen zusammenhängenden Sprechertext über alle Szenen hinweg.
6. **Video erstellen** — startet einen Hintergrund-Job. Der Status (*In Warteschlange → Rendering → fertig*) wird automatisch aktualisiert.
7. **Herunterladen** — Das fertige Video wird als **MP4** zum Download angeboten; das Storyboard rechts zeigt alle Szenen samt Sprechertext.

::: tip Vertonung und Szenenlänge
Die Szenen werden automatisch an die tatsächliche Länge des Sprechertexts angepasst (mindestens ~1,8 s plus etwas Auslauf), damit Texte und Bilder synchron zur Stimme stehen bleiben.
:::

## Architektur & Pipeline

```
Frontend (SocialVideoView)
  → POST /api/v1/social-video  (Produkte, Quellen, Kamerafahrt, Stil, KI-Flag)
  → SocialVideoBuilder
        ├─ MappingResolver (Attribute, Preise, Medien je Produkt)
        ├─ optional: Claude erzeugt zusammenhängenden Sprechertext
        └─ Szenen-Definition (JSON)
  → RenderSocialVideoJob (asynchron, Queue)
        ├─ Sprecherlänge messen (ElevenLabs oder gTTS-Fallback)
        ├─ Playwright (Headless-Chromium) zeichnet die Szenen auf (recordVideo)
        └─ ffmpeg fügt Video + Vertonung zu MP4 zusammen
  → GET /api/v1/social-video/{job}/download  (authentifizierter Download)
```

Die eigentliche Aufnahme erfolgt durch die **Video-Engine** (`video-engine/`): Die Szenen werden als HTML/CSS aufgebaut, von einem Headless-Browser per `recordVideo` aufgezeichnet (ohne Browserleiste) und anschließend mit der Vertonung zu einem MP4 (H.264/AAC) gemuxt.

## Konfiguration

```bash
# Video-Engine
VIDEO_ENGINE_ENABLED=true
VIDEO_ENGINE_BASE_URL=http://localhost:8000
VIDEO_ENGINE_OUTPUT_DIR=public/videos
VIDEO_ENGINE_FPS=30
VIDEO_ENGINE_QUALITY=high

# Vertonung (Text-to-Speech)
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_DE_FEMALE=
ELEVENLABS_VOICE_DE_MALE=
ELEVENLABS_FALLBACK=gtts      # Fallback ohne ElevenLabs-Key

# KI-Sprechertext (Claude)
CLAUDE_AI_API_KEY=
CLAUDE_AI_MODEL=claude-sonnet-4-5-20250929
```

- **Sprachausgabe:** Mit `ELEVENLABS_API_KEY` wird ElevenLabs (`eleven_multilingual_v2`) verwendet; ohne Key greift der freie **gTTS**-Fallback.
- **KI-Sprechertext:** Wird über Claude (`CLAUDE_AI_MODEL`) erzeugt. Ohne `CLAUDE_AI_API_KEY` bleibt die Funktion deaktiviert und es werden Standardtexte verwendet.
- **Voraussetzungen der Engine:** `ffmpeg` und ein Playwright-Chromium müssen verfügbar sein (Pfade lassen sich bei Bedarf konfigurieren).

::: warning Asynchrone Verarbeitung
Das Rendern läuft als Queue-Job mit langem Timeout. Stellen Sie sicher, dass ein Queue-Worker läuft (siehe [Cronjobs & Planung](/de/installation/cronjobs)), sonst bleibt der Job in der Warteschlange hängen.
:::
