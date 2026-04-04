# anyPIM Video Engine – Lieferung

## Was wurde gebaut

### Neue Infrastruktur (alles neue Dateien)

1. **Video-Engine** (`video-engine/`) – TypeScript-basierte Pipeline:
   - `src/preflight.ts` – Systemprüfungen vor Start
   - `src/story-validator.ts` – YAML-Validierung gegen JSON Schema
   - `src/script-generator.ts` – Story → Playwright-Script
   - `src/recorder.ts` – Xvfb + ffmpeg Orchestrierung
   - `src/subtitle-extractor.ts` – Sprecher-Texte → SRT
   - `src/voice-synthesizer.ts` – ElevenLabs / gTTS Voiceover
   - `src/video-renderer.ts` – ffmpeg Merge (Video + Audio + SRT)
   - `src/uploader.ts` – Output-Management
   - `src/lock-manager.ts` – Parallele Ausführung verhindern
   - `src/logger.ts` – Strukturiertes Logging

2. **Story-Definitionen** (`video-stories/`):
   - `_schema/story.schema.json` – JSON Schema für Validierung
   - `_template/story.yaml` – Vorlage für neue Stories
   - `01-produktanlage/story.yaml` – Produkt anlegen
   - `02-kategoriebaum/story.yaml` – Hierarchie verwalten
   - `03-pql-suche/story.yaml` – Suchfunktionen

3. **Artisan Command** (`app/Console/Commands/VideoGenerate.php`):
   - `pim:video-generate --list|--preflight|--story=|--all|--force`

4. **DemoVideoSeeder** (`database/seeders/DemoVideoSeeder.php`):
   - Baut auf bestehenden Seedern auf, idempotent

5. **Toast-System** (3 neue Dateien):
   - `pim-frontend/src/stores/toast.js`
   - `pim-frontend/src/components/shared/PimToast.vue`

### Minimale Änderungen an bestehendem Code

- ~20 `data-testid`-Attribute in 10 Vue-Komponenten (je 1 Zeile)
- 1 Zeile `<PimToast />` in `App.vue`
- 1 Zeile Toast-Trigger in `ProductCreatePanel.vue`
- `.env.example` und `.gitignore` ergänzt

## Was fehlt / noch nicht implementiert

1. **S3-Upload** – Platzhalter in `uploader.ts`, nicht implementiert
2. **Mehrsprachigkeit** – Engine ist vorbereitet (lang-Parameter), aber Story-Übersetzungen und DeepL-Integration fehlen
3. **Demo-Assets als echte Bilder** – Aktuell SVG-Platzhalter, keine JPG/PNG
4. **gTTS Python-Integration** – Funktioniert nur wenn `gtts` per pip installiert ist
5. **CI/CD Integration** – Kein automatisches Video-Update bei Code-Änderungen
6. **select_tree-Action** – Implementiert aber nicht gegen echten PimTree getestet (Produkt-Erstellung nutzt Flat-Dropdown)

## Bekannte Einschränkungen

- **Xvfb + ffmpeg erforderlich** – Funktioniert nur auf Linux-Systemen mit X11
- **ElevenLabs API kostenpflichtig** – Ohne API Key werden Videos ohne Voiceover erzeugt
- **Playwright Browser muss installiert sein** – `npx playwright install chromium`
- **Nicht für Produktion** – Produktionsschutz eingebaut (`APP_ENV !== 'production'`)
- **Kein macOS-Support** – Xvfb ist Linux-only; auf macOS müsste ein anderer Screen-Capture-Ansatz gewählt werden
