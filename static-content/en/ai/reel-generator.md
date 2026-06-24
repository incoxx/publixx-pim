---
title: Reel Generator (Social Video)
---

# Reel Generator — Social-Media Videos from PIM Data

With the **Reel Generator** (internally "Social Video") you automatically create short social-media videos from product data — reels, shorts or feed posts. Images, texts and prices come straight from the PIM; an optional AI voiceover and an animated camera pan turn static data into a finished, downloadable video.

## What the generator produces

From the selected content the generator automatically builds a sequence of scenes:

- **Hero** — product name and optional subline on the main image
- **Feature scenes** (up to 3) — attribute name, formatted value and unit (e.g. *"Screen size: 9 inch"*)
- **Showcase scenes** — additional gallery images while the voiceover continues
- **Price scene** — large animated price with currency (if mapped)
- **CTA scene** — call to action (e.g. *"Discover now"* or AI-generated)

Formats: **9:16** (Reels/Shorts/TikTok), **1:1** (feed post), **16:9** (YouTube).

## Usage

Open via **Publish → Social Video**.

1. **Select products** — choose one or more products via the search field.
2. **Map content** — use dropdowns to decide which source fills which role:
   - *Hero image* and *gallery* (from the media usage types)
   - *Headline*, *subline* and up to *3 features* (from attributes)
   - *Price* (from a price type)
   - *3 AI-context fields* — these feed **only the spoken text**, not the visible video
3. **Look & style** — accent and background colour, transition (fade/slide/zoom/cut), media animation (Ken Burns, zoom, pan …), optional *creative brief* and *tonality*. Presets (*Tech, Bold, Elegant, Fresh*) set these values with one click.
4. **Camera-pan editor** — set a start and end focus point plus a zoom level per product. A live preview shows the camera pan exactly as it will be rendered.
5. **AI hook** (optional) — with *"Generate AI hook (Claude)"* Claude produces a coherent voiceover script spanning all scenes.
6. **Create video** — starts a background job. The status (*queued → rendering → done*) updates automatically.
7. **Download** — the finished video is offered as an **MP4**; the storyboard on the right shows all scenes including the voiceover text.

::: tip Voiceover and scene length
Scenes are automatically adjusted to the actual length of the voiceover (at least ~1.8 s plus some lead-out) so that text and images stay in sync with the voice.
:::

## Architecture & pipeline

```
Frontend (SocialVideoView)
  → POST /api/v1/social-video  (products, sources, camera pan, style, AI flag)
  → SocialVideoBuilder
        ├─ MappingResolver (attributes, prices, media per product)
        ├─ optional: Claude generates a coherent voiceover script
        └─ scene definition (JSON)
  → RenderSocialVideoJob (asynchronous, queue)
        ├─ measure voiceover length (ElevenLabs or gTTS fallback)
        ├─ Playwright (headless Chromium) records the scenes (recordVideo)
        └─ ffmpeg muxes video + voiceover into MP4
  → GET /api/v1/social-video/{job}/download  (authenticated download)
```

The actual recording is done by the **video engine** (`video-engine/`): scenes are built as HTML/CSS, recorded by a headless browser via `recordVideo` (without the browser bar) and then muxed with the voiceover into an MP4 (H.264/AAC).

## Configuration

```bash
# Video engine
VIDEO_ENGINE_ENABLED=true
VIDEO_ENGINE_BASE_URL=http://localhost:8000
VIDEO_ENGINE_OUTPUT_DIR=public/videos
VIDEO_ENGINE_FPS=30
VIDEO_ENGINE_QUALITY=high

# Voiceover (text-to-speech)
ELEVENLABS_API_KEY=
ELEVENLABS_VOICE_DE_FEMALE=
ELEVENLABS_VOICE_DE_MALE=
ELEVENLABS_FALLBACK=gtts      # fallback without an ElevenLabs key

# AI voiceover script (Claude)
CLAUDE_AI_API_KEY=
CLAUDE_AI_MODEL=claude-sonnet-4-5-20250929
```

- **Voice output:** With `ELEVENLABS_API_KEY` set, ElevenLabs (`eleven_multilingual_v2`) is used; without a key the free **gTTS** fallback applies.
- **AI voiceover script:** Generated via Claude (`CLAUDE_AI_MODEL`). Without `CLAUDE_AI_API_KEY` the feature stays disabled and default texts are used.
- **Engine requirements:** `ffmpeg` and a Playwright Chromium must be available (paths can be configured if needed).

::: warning Asynchronous processing
Rendering runs as a queue job with a long timeout. Make sure a queue worker is running (see [Cron Jobs & Scheduling](/en/installation/cron-jobs)), otherwise the job stays stuck in the queue.
:::
