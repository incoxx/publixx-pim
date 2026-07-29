# Bestandsaufnahme: UI-Übersetzungen (Deutsch/Englisch)

> Auftrag: Menü und verdrahtete Oberflächen/Masken sind nur eingeschränkt übersetzt.
> Scope: Deutsch + Englisch. Diese Bestandsaufnahme deckt **alle** `.vue`-Dateien in
> `pim-frontend/src` (325 Dateien, Views + Components) sowie die Backend-Seite (Laravel) ab.

## Executive Summary

| Kennzahl | Wert |
|---|---|
| `.vue`-Dateien gesamt (`pim-frontend/src`) | 325 |
| davon nutzen `vue-i18n` (`$t()` / `useI18n()`) überhaupt | 44 (13,5 %) |
| davon **ganz ohne** i18n — 100 % hartkodierter Text | **281 (86,5 %)** |
| Code-Zeilen in nicht-übersetzten Dateien | ~73.200 |
| Code-Zeilen in (teilweise) übersetzten Dateien | ~23.800 |
| Sidebar-Menüpunkte gesamt | ~90 |
| davon über `t('nav.…')` übersetzt | 22 |
| davon hartkodiert Deutsch | ~68 |
| Backend: Nutzung von Laravel `__()`/`trans()` | **0 Treffer in `app/`** |
| Backend: `lang/`-Verzeichnis vorhanden | **Nein** |

**Drei kritische, konkret nachgewiesene Bugs** (nicht nur „fehlende Übersetzung“, sondern kaputte Mechanik):

1. **Screenshot-Bug erklärt:** `nav.quickSearch` fehlt komplett im `en`-Block von `main.js`. Läuft die UI auf Englisch, greift auch der `fallbackLocale: 'en'` nicht mehr (er *ist* ja schon aktiv) → vue-i18n gibt den rohen Key `nav.quickSearch` aus, exakt wie im Screenshot zu sehen.
2. **Sprachumschaltung ist inkonsistent / teilweise wirkungslos:** Es gibt zwei UI-Sprachschalter — der Globus-Dropdown in `AppHeader.vue` setzt korrekt sowohl den Store als auch `i18n.locale`; der Dropdown in `SettingsView.vue` (`t('settings.uiLanguage')`) setzt **nur den Pinia-Store**, nie `i18n.locale` → Auswahl dort hat keinerlei sichtbare Wirkung. Zusätzlich wird die gespeicherte Sprache (`localStorage['pim_locale']`) beim App-Start **nicht** wieder in `i18n.locale` geladen — `main.js` initialisiert immer nur aus `VITE_DEFAULT_LOCALE`. Ein Reload wirft die Sprachwahl faktisch zurück auf Deutsch.
3. **Französisch ist als UI-Sprache wählbar, obwohl 0 Strings existieren:** `availableLocales` in `stores/locale.js` listet `de/en/fr` und wird sowohl für Datensprachen als auch für den UI-Sprachschalter verwendet. Für `fr` gibt es in `main.js` **keinen** Message-Block — Auswahl von „Français“ zeigt kommentarlos die englischen Fallback-Texte. Da der Auftrag sich auf DE/EN beschränkt, sollte „Français“ aus der **UI**-Sprachliste entfernt werden (als Datensprache kann es bleiben).

---

## 1. Technische Basis: vue-i18n

- **Bibliothek:** `vue-i18n@11` ist installiert und in `pim-frontend/src/main.js` verdrahtet (`legacy: false`, `fallbackLocale: 'en'`, Default-Locale aus `VITE_DEFAULT_LOCALE` bzw. `'de'`).
- **Struktur-Problem:** Es gibt **keine** separaten Locale-Dateien (`de.json`/`en.json`, `locales/`-Ordner o. ä.). Beide Sprachblöcke liegen als ein einziges, ~650 Zeilen langes JS-Objekt direkt in `main.js`. Das ist für den aktuellen Umfang (12 Namespaces: `nav`, `common`, `product`, `hierarchy`, `productType`, `relationType`, `attributeView`, `attributeType`, `attribute`, `import`, `export`, `settings`, `cmd`, `catalog`, `assetCatalog`) schon unhandlich und wird bei Vollausbau (geschätzt tausende Keys) nicht mehr wartbar sein.
- **Kein Locale-Persistenz-Mechanismus** zwischen Reloads (siehe Bug 2 oben).
- **Keine Vitest-Abdeckung**, die de/en-Key-Parität prüft — der `nav.quickSearch`-Bug (Bug 1) wäre durch einen einfachen Struktur-Vergleichstest sofort aufgefallen.

## 2. Umfang: Views (130 Dateien, nach Modul)

| Modul (`views/…`) | Dateien | mit i18n | Modul (`views/…`) | Dateien | mit i18n |
|---|---|---|---|---|---|
| accessLinks | 2 | 0 | mediaMotifs | 1 | 0 |
| admin | 10 | 0 | mediaUsageTypes | 1 | 0 |
| apiDesigner | 2 | 0 | messenger | 1 | 0 |
| assetCatalog | 2 | 1 | navigation | 1 | 0 |
| attributeTypes | 1 | 1 | pdf-templates | 2 | 0 |
| attributeViews | 1 | 1 | portalConfig | 2 | 0 |
| attributes | 1 | 1 | priceRegions | 1 | 0 |
| calendar | 1 | 0 | prices | 1 | 0 |
| catalog | 4 | 2 | productTypes | 1 | 1 |
| catalog-templates | 2 | 0 | products | 4 | 2 |
| cockpit | 1 | 0 | projects | 2 | 0 |
| collectionTypes | 1 | 0 | publish | 3 | 0 |
| collections | 2 | 0 | referenceProfiles | 2 | 0 |
| comparisonOperators | 1 | 0 | relationTypes | 1 | 1 |
| connectors | 14 | 0 | reports | 2 | 1 |
| content | 8 | 0 | roles | 1 | 0 |
| copilot | 1 | 0 | search | 3 | 0 |
| dashboard | 1 | 0 | settings | 1 | 1 |
| dictionary | 1 | 0 | site | 2 | 0 |
| documentPortal | 4 | 0 | teams | 1 | 0 |
| ecommerce | 4 | 0 | translations* | 6 | 0 |
| excelDesigner | 2 | 0 | units | 1 | 0 |
| exports | 4 | 0 | users | 2 | 0 |
| formattingRules | 1 | 0 | valueLists | 1 | 0 |
| hierarchies | 1 | 1 | watchlist | 1 | 0 |
| imports | 2 | 0 | workflow | 3 | 0 |
| journal | 1 | 0 | (Top-Level: LoginView etc.) | 3 | 1 |
| manufacturers | 1 | 0 | | | |
| mcpPlayground | 1 | 0 | | | |
| media | 1 | 0 | | | |
| mediaCountries | 1 | 0 | | | |
| mediaLanguages | 1 | 0 | | | |

\* `translations/` ist die **Datensprachen**-Funktion (Translation Memory, DeepL-Jobs), keine UI-Übersetzung — siehe Abschnitt 6.

**Befund:** Nur 11 von 58 Modul-Ordnern haben überhaupt eine übersetzte Datei, und selbst dort meist nur teilweise (ein `$t()`-Aufruf neben viel hartkodiertem Text zählt hier schon als „mit i18n“). Kernarbeitsbereiche mit **null** Übersetzung: **Dashboard**, **Suche** (`search/`), **Merkliste** (`watchlist/`), **Media**, **Workflow**, **Content**, **E-Commerce**, **Import/Export**, **Connectoren** (14 Dateien!), **Admin** (10 Dateien), **Kalender**, **Berichte/PDF-Vorlagen**, **Benutzer/Rollen**.

## 3. Umfang: Components (194 Dateien, nach Modul)

| Modul (`components/…`) | Dateien | mit i18n |
|---|---|---|
| apiDesigner | 6 | 0 |
| assetCatalog | 13 | 12 |
| attributes | 2 | 0 |
| calendar | 2 | 0 |
| catalog | 23 | 13 |
| cockpit | 1 | 0 |
| collections | 2 | 0 |
| content | 1 | 0 |
| dashboard | 23 | **0** |
| dialogs | 5 | **0** |
| documentPortal | 6 | 0 |
| excelDesigner | 6 | 0 |
| hierarchies | 1 | 0 |
| layout | 6 | 2 |
| media | 4 | 0 |
| mediaMotifs | 2 | 0 |
| messenger | 4 | 0 |
| panels | 27 | **0** |
| pdf-templates | 7 | 0 |
| products | 8 | 1 |
| reports | 7 | 0 |
| roles | 4 | 0 |
| search | 2 | 0 |
| shared | 29 | 2 |
| site | 3 | 0 |

**Auffällig:** Die beiden größten Component-Ordner — **`panels/` (27 Dateien)** und **`dashboard/` (23 Dateien)** — sind zu 100 % hartkodiert. Beides sind vielgenutzte, sichtbare Flächen (Cockpit-Panels bzw. Dashboard-Kacheln).

**Was schon gut funktioniert:** `components/catalog/` (13/23) und `components/assetCatalog/` (12/13) — die **kundenseitigen** Katalog-/Asset-Katalog-Viewer — sind größtenteils sauber über `vue-i18n` übersetzt (das o. g. `catalog`/`assetCatalog`-Namespace in `main.js` ist entsprechend gut ausgebaut). Das ist der einzige Bereich der Anwendung, der als Vorbild/Referenzimplementierung für den Rest dienen kann.

## 4. Menü (Sidebar) im Detail — `components/layout/AppSidebar.vue`

Die Sidebar definiert die Menüstruktur inline als JS-Array (`sections`). Stichprobe:

- **Bereichs-Überschriften:** `Content`, `E-Commerce`, `Publish`, `Plugins` sind zufällig bereits englisch lesbar (aber nicht via i18n — reines Zufallsprodukt, keine Übersetzung für DE-Nutzer nötig); `Übersetzungen`, `Projektmanagement`, `Konfiguration`, `Administration` sind hartkodiert Deutsch und im Englischen unverändert.
- **Menüpunkte mit `t('nav.…')`:** Schnellsuche, Profisuche, Produkte, Hierarchien, Medien, Hersteller, Produkttypen, Beziehungstypen, Attribut-Sichten/-Gruppen/-Werte, Wertelisten, Formatierungsregeln, Wörterbuch, Bildtypen, Preise, Preisregionen, Import, Export, Einstellungen, Benutzer, Hilfe — **22 Stück**.
- **Menüpunkte hartkodiert Deutsch** (Auszug): `Merkliste`, `Collections`, `Workflow`, `Planungskalender`, `Motive`, `Sitemap`, `Website-Vorschau`, `Seitentypen`, `Sektionstypen`, `Produkt-Widgets`, `Warenkörbe`, `Adressarten`, `Zahlungsarten`, `Bestellungen`, `Berichte`, `PDF-Vorlagen`, `Katalog-Vorlagen`, `Portale`, `Katalog-Demo`, `Social-Video`, `Plugin-Einstellungen`, `Connectoren` (inkl. aller Connector-Namen), `Übersetzungsjobs`, `Translation Memory`, `DeepL Einstellungen`, `Projekt-Dashboard`, `Workflows`, `Workflow-Status`, `Teams`, `Projekte`, `Referenz-Profile`, `Konformitäts-Report`, `Einheiten`, `Vergleichsoperatoren`, `Medien-Sprachen`, `Medien-Länder`, `Collection-Typen`, `JSON Export/Import`, `Sheet Designer`, `BMEcat Import/Export`, `Export-Jobs`, `Attribut-Mapping`, `Benutzer-Audit`, `Rollen`, `Cockpit-Layouts`, `Zugangslinks`, `Test anyPIM`, `API-Designer`, `MCP Playground`, `API Tester`, `Datenbank`, `Datenkonsistenz`, `Guard`, `Log Viewer`, `Artisan-Cockpit`, `Fehler`, `Journal` — **~68 Stück**.
- Zusätzlich sind Tooltips wie `title="Alle Menüs schließen"`, `title="Sidebar öffnen/schließen"` hartkodiert.

→ Rund **drei Viertel** aller Sidebar-Einträge sind aktuell nicht übersetzbar.

## 5. Backend (Laravel) — kein i18n-Mechanismus vorhanden

- `config/app.php`: `locale => 'de'`, `fallback_locale => 'en'` — **aber** kein `lang/`-Verzeichnis existiert im ganzen Projekt.
- `grep -rl "__(\|trans(\|Lang::get" app/` → **0 Treffer.** Das Laravel-eigene Übersetzungssystem wird nirgends genutzt.
- Von 126 FormRequest-Klassen definieren nur **3** eine eigene `messages()`-Methode (hartkodiert Deutsch, z. B. `StoreAccessLinkRequest`). Alle anderen 123 nutzen Laravels eingebaute Default-Validierungsmeldungen — die (mangels `lang/de/validation.php`) auf Englisch zurückfallen.
- **Konsequenz:** Validierungsfehler, die die API zurückgibt, sind je nach Feld **zufällig Deutsch oder Englisch**, unabhängig von der UI-Sprache des Nutzers.
- Mind. **153** hartkodierte deutsche Antwort-/Fehlermeldungen (`'message' => '…'`) direkt in `app/Http/Controllers` gefunden (Stichproben-Grep, keine Vollzählung — reale Zahl liegt höher, da nur ein einfaches Muster geprüft wurde).
- 3 Notification-Klassen (`SecurityAlertNotification`, `CriticalErrorAlert`, `ForwardedErrorNotification`) — keine Mail-Templates (`app/Mail` ist leer), Texte vermutlich inline und hartkodiert.

**Backend-Übersetzung ist ein komplett separates Vorhaben** (Laravel-Lang-Dateien einführen, `__()` durchziehen, Locale pro Request aus Header/Nutzer ableiten) und im Umfang mindestens so groß wie die Frontend-Arbeit.

## 6. Wichtige Abgrenzung: „Übersetzungen“ im Menü ≠ UI-Übersetzung

Der Menüpunkt **„Übersetzungen“** (`translation-jobs`, `translations`, DeepL-Connector) betrifft die **Datensprachen-Funktion** — also das Übersetzen von *Produktinhalten* (Attributwerte, Beschreibungen) zwischen Sprachen, verwaltet über `TranslationJobsStore`. Das ist ein bereits bestehendes, funktionierendes PIM-Feature und **hat nichts mit der hier untersuchten UI-Übersetzung** (Menü, Buttons, Labels der Bedienoberfläche selbst) zu tun. Beide Themen sollten in der Kommunikation klar getrennt bleiben, da der Name im Menü sonst verwirrt.

Getrennt davon existiert bereits eine **Datensprachen**-Infrastruktur fürs UI (`stores/locale.js`: `activeDataLocales`, `getLocalizedValue()`), die z. B. für mehrsprachige Produktnamen in Dropdowns genutzt wird — auch das ist unabhängig von der `vue-i18n`-UI-Übersetzung.

## 7. Weitere Frontends (kurzer Ausblick, vermutlich nicht im Scope)

- `catalog-embed/` (15 Dateien) und `portal-embed/` (7 Dateien) sind eigenständige, eingebettete Frontends (Katalog-Widget bzw. Portal-Einbettung) außerhalb von `pim-frontend`. Aktuell nicht auf `vue-i18n` geprüft verdrahtet gefunden.
- `marketing/`, `tms/`, `video-engine/`, `video-stories/` enthalten keine `.vue`/`.jsx`/`.tsx`-Dateien — vermutlich kein Frontend-i18n-Thema.
- Da der Auftrag explizit „Menü und verdrahtete Oberflächen/Masken“ sagt, ist davon auszugehen, dass primär die **Admin-PIM-Oberfläche** (`pim-frontend`) gemeint ist. Die kundenseitigen Katalog-Viewer sind — wie in Abschnitt 3 gezeigt — ohnehin schon der am besten übersetzte Teil der Anwendung.

## 8. Größenordnung der eigentlichen Übersetzungsarbeit

Nur als grobe Illustration der Textmenge (keine exakte Zählung): allein für **13 gängige Aktions-Wörter** (Speichern, Abbrechen, Löschen, Bearbeiten, Erstellen, Suchen, Einstellungen, Bestätigen, Hochladen, Herunterladen, Zurücksetzen, Exportieren, Importieren) finden sich in den 281 nicht-übersetzten Dateien **686 Fundstellen**. Die tatsächliche Zahl zu übersetzender Strings (Labels, Platzhalter, Tooltips, Tabellenüberschriften, Fehlermeldungen, leere Zustände, Bestätigungsdialoge …) liegt um ein Vielfaches höher — realistisch im **niedrigen bis mittleren vierstelligen Bereich** für das Frontend allein.

## 9. Empfehlung für das weitere Vorgehen (nur Vorschlag, noch keine Entscheidung)

1. **Zuerst die drei Mechanik-Bugs fixen** (Abschnitt „Executive Summary“, Punkte 1–3) — kostet wenig, behebt aber sichtbar kaputtes Verhalten sofort.
2. **Struktur umbauen:** `main.js`-Inline-Objekt auflösen in `src/locales/de.json` + `src/locales/en.json` (oder je Namespace-Datei), bevor der Umfang weiter wächst — sonst wird die Datei bei Vollausbau unhandhabbar.
3. **Frontend modulweise abarbeiten**, priorisiert nach Sichtbarkeit/Nutzungshäufigkeit: Sidebar/Menü → Dashboard → Produkte/Hierarchien/Attribute (Kernarbeitsbereiche) → Panels/Dialogs → Rest.
4. **Backend als eigenes Teilprojekt** einplanen (Laravel-Lang-Dateien, `__()`-Migration, Locale-Ermittlung pro Request) — unabhängig vom Frontend-Fortschritt planbar.
5. **Regressionsschutz:** ein einfacher Test, der de/en-Key-Struktur auf Parität prüft (hätte Bug 1 verhindert), plus ein Lint-Check, der neue hartkodierte Template-Strings in PRs auffällt.
