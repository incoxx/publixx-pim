# Plan: Social-Media-Produktvideos

**Status:** Konzept / Spec
**Datum:** 2026-06-22
**Autor:** Produktidee Markus Gerber, ausgearbeitet als Umsetzungsplan

## Idee

Der Nutzer pickt **1..n Produkte**. Aus deren **Bildtypen** (Medien nach UsageType) und
**Texten** (Attribute) erzeugt anyPIM automatisch fertige **Social-Media-Videos** (Reels/Shorts/TikTok,
9:16) – inklusive KI-generiertem Verkaufsskript und Voiceover.

Kernprinzip (analog zur Klassifikations-Architektur): **kein separates Video-System**, sondern eine
weitere **Ausgabe-Ausprägung** der vorhandenen PIM-Daten – ein „Export", dessen Zielformat `.mp4` ist.

```
Produkt(e)  ──▶  MappingResolver        ──▶  KI-Skript (Claude)   ──▶  Reel-Definition  ──▶  Video-Engine  ──▶  social.mp4
 (picken)        media:* + attribute:*       Hook + CTA               (Szenen-JSON)         (HTML→Recorder)     9:16 + Voiceover
```

## Was wir wiederverwenden (≈ 70 % existiert bereits)

| Baustein | Datei | Wiederverwendung |
|----------|-------|------------------|
| Daten-Mapping | `app/Services/Export/MappingResolver.php` | `media:`/`attribute:`/`prices:` → flache Key→Value-Map pro Produkt |
| KI-Texte | `app/Services/Connectors/ClaudeAI/ClaudeAITextService.php` | `generateProductText()` mit Task `marketing` bzw. `custom_prompt` für Social-Hook + CTA |
| Aufnahme | `video-engine/src/recorder.ts` | Xvfb + ffmpeg `x11grab` – nimmt jede Browser-Seite auf, **unverändert** (nur Viewport 1080×1920) |
| HTML-Szenen | `video-engine/src/script-generator.ts` | rendert Intro/Outro **bereits** via `page.setContent()` – exakt das Muster für Produkt-Szenen |
| Voiceover | `video-engine/src/voice-synthesizer.ts` | ElevenLabs + gTTS-Fallback, Cache, Timestamp-Sync – **unverändert** |
| Render/Merge | `video-engine/src/video-renderer.ts` | ffmpeg-Merge Video + Audio + SRT + Sonic-Logo – **unverändert** |
| Orchestrierung | `app/Console/Commands/VideoGenerate.php` | Muster für neuen Command + `getVideoEnv()` |

**Neu zu bauen ist nur:** (1) ein Backend-Service, der pro Produkt eine *Reel-Definition* erzeugt, und
(2) ein Reel-Renderer in der Engine, der diese Definition in HTML-Szenen übersetzt.

## Architektur-Entscheidung: HTML-Szenen statt UI-Recording

Die heutige Engine zeichnet die **PIM-Oberfläche** auf (Login → Navigation → `data-testid`-Selektoren).
Für Produktvideos ist das weder nötig noch erwünscht. Stattdessen:

- Der Reel-Renderer erzeugt ein Playwright-Script, das **rein animierte HTML-Szenen** über
  `page.setContent()` darstellt (genau wie heute schon Intro/Outro).
- Layout/Design der Szenen entstehen in **Tailwind/CSS** → durch Designer pflegbar, ohne TS anzufassen.
- Kein Login, keine Seeder, keine Selektoren, keine Abhängigkeit zum Frontend-Stand.

**Vorteil:** maximale Wiederverwendung von Recorder/Voice/Renderer; das Risiko reduziert sich auf das
Schreiben von HTML-Templates.

## Datenfluss im Detail

### 1. Produktauswahl & Mapping (PHP)

Neuer Service `app/Services/Export/SocialVideoBuilder.php` – folgt der `{Format}FormatExporter`-Konvention
(`use ExportProductHelpers;`, Filter-Properties, `MappingResolver`):

```php
$mapped = $this->mappingResolver->resolve($this->mappingRules, $product, $this->languages);
// $mapped = ['hero_image' => '/storage/media/teaser.jpg',
//            'gallery'    => ['/img1.jpg', '/img2.jpg'],
//            'headline'   => 'Akkubohrer Professional',
//            'feature_1'  => 'Bürstenloser Motor',
//            'price'      => 189.99, ...]
```

Mapping-Ziele kommen aus `SocialVideoElementMap::TARGET_FIELDS` (z. B. `hero_image`, `gallery`,
`headline`, `feature_1..n`, `price`, `cta`). Quellen sind die bekannten Namespaces `media:`, `attribute:`,
`prices:`.

### 2. KI-Skript (PHP, Claude)

Aus den gemappten Texten erzeugt `ClaudeAITextService` ein knackiges Social-Skript pro Szene:

```php
$script = $this->claude->generateProductText($apiKey, $product, $lang, 'marketing', [
    'custom_prompt' => 'Erzeuge ein 6-Szenen-Reel-Skript (Hook, 3 Features, Preis, CTA) ...',
    'tonality'      => 'jung, energiegeladen, max. 12 Wörter pro Szene',
    'max_tokens'    => 600,
]);
```

Ergebnis: pro Szene ein kurzer **On-Screen-Text** + ein **Sprechertext** (Voiceover).
Bei fehlendem API-Key → Fallback auf rohe Attributtexte (deterministisch).

### 3. Reel-Definition (JSON, die Schnittstelle PHP → Engine)

`SocialVideoBuilder` schreibt **keine** YAML-Story, sondern eine schlanke **Reel-Definition**
(neues Schema `video-stories/_schema/reel.schema.json`):

```json
{
  "meta": {
    "id": "reel-akkubohrer-001",
    "format": "9x16",
    "viewport": { "width": 1080, "height": 1920 },
    "voice": { "lang": "de", "gender": "female", "provider": "elevenlabs" },
    "template": "default"
  },
  "scenes": [
    { "type": "hero",    "image": "/storage/media/teaser.jpg", "headline": "Akkubohrer Professional",
      "sprecher": "Schluss mit leeren Akkus mitten im Job." , "duration": 3000 },
    { "type": "feature", "image": "/storage/media/img1.jpg", "headline": "Bürstenloser Motor",
      "sprecher": "Mehr Drehmoment, längere Lebensdauer.", "duration": 2500 },
    { "type": "price",   "value": 189.99, "currency": "EUR",
      "sprecher": "Jetzt für nur 189 Euro.", "duration": 2500 },
    { "type": "cta",     "headline": "Jetzt entdecken", "sprecher": "Mehr auf incoxx.com.",
      "duration": 2500 }
  ]
}
```

### 4. Reel-Renderer (Engine, TS)

Neu: `video-engine/src/reel-renderer.ts` – analog zu `script-generator.ts`, aber **szenen- statt
schrittbasiert**. Erzeugt ein Playwright-Script, das pro Szene `page.setContent(<animiertes HTML>)`
aufruft. Szenen-Templates liegen als HTML/CSS-Bausteine (`hero`, `feature`, `price`, `cta`) vor.

Danach läuft die **bestehende Pipeline unverändert**: `Recorder` (1080×1920) → `VoiceSynthesizer`
(Sprechertexte) → SRT → `video-renderer` (Merge + Sonic-Logo).

### 5. Command + API

**Artisan** (`app/Console/Commands/SocialVideoGenerate.php`, Muster aus `VideoGenerate.php`):

```bash
php artisan pim:social-video --product=UUID[,UUID...] \
    --mapping=UUID --template=default --format=9x16 --lang=de [--no-voice]
```

**Job statt synchroner Download:** Video-Rendering dauert (Xvfb + ffmpeg + TTS), daher **nicht** als
`StreamedResponse` wie bei XML-Exporten, sondern als **Queued Job** mit Status-Polling:

- `POST /api/v1/social-video` (Middleware `module:social-video`) → legt `SocialVideoJob` an, gibt `job_id`.
- `GET  /api/v1/social-video/{job_id}` → Status (`queued|rendering|done|failed`) + Download-URL.
- Output nach `public/videos/social/{job_id}.mp4`.

> **Abweichung von der Export-Konvention** (Punkt 3 „StreamedResponse"): bewusst, weil Video-Rendering
> langlaufend ist. Filtering/Chunking/i18n bleiben wie in der Konvention.

## Neue / geänderte Dateien

| Datei | Art | Zweck |
|-------|-----|------|
| `app/Services/Export/SocialVideoBuilder.php` | neu | Produkt(e) → Reel-Definition (Mapping + KI-Skript) |
| `app/Services/Export/SocialVideoElementMap.php` | neu | `TARGET_FIELDS`, `defaultMappingRules()`, `fieldDefaults()` |
| `app/Jobs/RenderSocialVideoJob.php` | neu | Queued: ruft Engine, schreibt Status |
| `app/Http/Controllers/Api/V1/SocialVideoController.php` | neu | `POST` (anstoßen) + `GET` (Status/Download) |
| `app/Console/Commands/SocialVideoGenerate.php` | neu | CLI-Einstieg, Muster `VideoGenerate.php` |
| `video-engine/src/reel-renderer.ts` | neu | Reel-Definition → Playwright-HTML-Szenen |
| `video-engine/src/templates/*.ts` | neu | HTML/CSS-Szenen-Bausteine (hero, feature, price, cta) |
| `video-stories/_schema/reel.schema.json` | neu | Schema der Reel-Definition |
| `routes/api.php` | ändern | eigener `module:social-video`-Block (nur anhängen) |
| `.env.example` / `config/connectors.php` | ändern | bereits vorhandene Keys (ELEVENLABS, Claude) wiederverwenden |

`recorder.ts`, `voice-synthesizer.ts`, `video-renderer.ts`, `MappingResolver.php`,
`ClaudeAITextService.php` bleiben **unverändert** (nur Lesen / Aufruf).

## Offene Entscheidungen für die Umsetzung

1. **Mehrere Produkte:** je Produkt ein eigener Clip **oder** ein Karussell-Reel (mehrere Produkte in
   einem Video)? Beide möglich, MVP startet mit *1 Produkt = 1 Clip*; n Produkte = n Clips (Batch).
2. **Formate:** MVP nur 9:16. Später 1:1 und 16:9 über zusätzliche `format`-Presets (nur Viewport +
   CSS-Anpassung im Template).
3. **Musik:** Hintergrund-Audio/lizenzfreie Tracks? Vorerst nur Voiceover + optional Sonic-Logo.
4. **Auslöser:** manuell (GUI „Produkte picken → Reel"), Batch (Artisan) oder Event (bei Freigabe)?
   MVP: manuell + Artisan.

## MVP-Schnitt

1 Template (`default`, 9:16), 1 Produkt → 1 Clip mit Szenen Hero → 2× Feature → Preis → CTA.
Texte via `ClaudeAITextService` (Fallback: Attributtexte). Voiceover via vorhandenes TTS.
Einstieg über `php artisan pim:social-video --product=UUID`.

**Aufwandsschätzung MVP:** Backend-Service + ElementMap + Command ≈ 1 Tag; Reel-Renderer + 4
HTML-Szenen-Templates ≈ 1–2 Tage; Job + API + Route ≈ 0,5 Tag; Tests + Doku ≈ 0,5 Tag.
**Summe ≈ 3–4 Personentage** für einen vorzeigbaren MVP, da die Render-Infrastruktur bereits steht.

## Risiken

- **Render-Dauer/Last:** Xvfb+ffmpeg sind CPU-intensiv → unbedingt Queue, nicht synchron. In Produktion
  ggf. dediziertes Worker-Profil (vgl. Produktionsschutz in `VideoGenerate::handle()`).
- **Bildqualität/Seitenverhältnis:** Quellbilder sind selten 9:16 → `object-fit: cover` + Blur-Backdrop
  im Template einplanen.
- **KI-Kosten/Latenz:** pro Reel ein Claude-Call; Ergebnisse cachen (analog Voice-Cache), Skript als
  Attribut speicherbar (`save_as_attribute`).
- **Lizenz/Modul:** Feature hinter `module:social-video` (Enterprise) wie übrige Export-Formate.

---

## Umsetzung (MVP, Stand 2026-06-22)

Der MVP ist implementiert. Auswahl mehrerer Produkte → **ein** kombiniertes Video (Hero → bis zu
2 Features → Preis je Produkt, am Ende eine globale CTA-Szene).

### Tatsächlich erstellte Dateien

| Datei | Zweck |
|-------|-------|
| `app/Services/Export/SocialVideoElementMap.php` | Zielfelder + Default-Mapping-Regeln |
| `app/Services/Export/SocialVideoBuilder.php` | Produkte → Reel-Definition (MappingResolver + optional Claude-Hook) |
| `app/Jobs/RenderSocialVideoJob.php` | Queue-Job: ruft `reel-cli.ts`, schreibt dateibasierten Status |
| `app/Http/Controllers/Api/V1/SocialVideoController.php` | `POST /social-video`, `GET {job}/status`, `GET {job}/download`, `GET default-mapping` |
| `app/Console/Commands/SocialVideoGenerate.php` | CLI `pim:social-video` |
| `video-engine/src/reel-renderer.ts` | Reel-Definition → Playwright-HTML-Szenen (9:16, `setContent`) |
| `video-engine/src/reel-cli.ts` | Orchestrator: Recorder → VoiceSynthesizer → video-renderer |
| `video-stories/_schema/reel.schema.json` | Schema der Reel-Definition |
| `pim-frontend/src/views/publish/SocialVideoView.vue` | GUI-Maske (Publish-Menü) |
| `pim-frontend/src/api/socialVideo.js` | Frontend-API-Client |

Geändert: `routes/api.php` (Route-Block), `pim-frontend/src/router/index.js` (`/social-video`),
`pim-frontend/src/components/layout/AppSidebar.vue` (Menüpunkt **am Ende von „Publish"**).

### Bewusste Abweichungen vom ursprünglichen Plan

- **Kein `module:`-Gating** für den MVP: Die Routen liegen in der normalen Auth-Gruppe und der
  Menüpunkt ist mit `permission: 'products.view'` sichtbar (ein eigenes Lizenzmodul existiert noch
  nicht – kann später ergänzt werden, ohne die Logik zu ändern).
- **Status ohne DB-Migration:** Job-Status liegt als `storage/app/social-video/{job}/status.json`,
  Output unter `public/videos/social/{job}.mp4`.
- Der Builder nutzt direkt `MappingResolver` (wie `JsonFormatExporter`), nicht das
  `ExportProductHelpers`-Pattern der Export-Format-Konventionen.

### Nutzung

```bash
# Engine-Abhängigkeiten (einmalig, benötigt ffmpeg + Xvfb + Playwright):
cd video-engine && npm install && npx playwright install chromium

# CLI:
php artisan pim:social-video --products=UUID1,UUID2 --format=9x16 --ai
php artisan pim:social-video --products=UUID --reel-only   # nur Storyboard-JSON, kein Rendering
```

GUI: **Publish → Social-Video** → Produkte suchen/auswählen, Format + Sprache wählen, optional
„KI-Hook", **Video erstellen**. Das Storyboard erscheint sofort; der Render-Status wird gepollt und
das MP4 nach Fertigstellung im Browser abspielbar/herunterladbar.

> **Hinweis:** Das eigentliche Rendering läuft über die Queue und benötigt ffmpeg, Xvfb und den
> Playwright-Chromium (wie die bestehende `video-engine`). Ohne diese Tools liefert der Job einen
> klaren `failed`-Status; Storyboard-Erzeugung und KI-Hook funktionieren unabhängig davon.

