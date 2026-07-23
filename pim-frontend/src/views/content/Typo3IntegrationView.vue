<script setup>
import { ref } from 'vue'
import {
  ExternalLink, Info, ShieldCheck, Blocks, Palette, ListChecks, Copy, Check, Mail,
} from 'lucide-vue-next'
import { useClipboard } from '@/composables/useClipboard'

const { copy } = useClipboard()
const copiedKey = ref(null)

async function copyCode(key, code) {
  await copy(code)
  copiedKey.value = key
  setTimeout(() => { if (copiedKey.value === key) copiedKey.value = null }, 2000)
}

// Basis-URL dieser anyPIM-Instanz — als Kopiervorlage für die Agentur
const apiBase = `${window.location.origin}/api/v1`

const widgets = [
  { name: 'search', desc: 'Freitextsuche' },
  { name: 'categories', desc: 'Kategoriebaum / Navigation' },
  { name: 'facets', desc: 'Facetten-/Filterpanel (inkl. Smart Graying)' },
  { name: 'active-filters', desc: 'Anzeige & Entfernen aktiver Filter' },
  { name: 'toolbar', desc: 'Sortierung, Ansicht (Raster/Liste), Trefferzahl' },
  { name: 'product-grid', desc: 'Produktkachel-Raster (Trefferliste)' },
  { name: 'pagination', desc: 'Seitennavigation' },
  { name: 'product-detail', desc: 'Produktdetail (öffnet als Overlay)' },
  { name: 'compare', desc: 'Produktvergleich (öffnet als Overlay)' },
  { name: 'wishlist', desc: 'Merkliste-Drawer (PDF-/Excel-Export)' },
  { name: 'wishlist-button', desc: 'Merkliste-Button, z. B. im Header' },
  { name: 'locale', desc: 'Sprachumschalter' },
  { name: 'sidebar-toggle', desc: 'Mobil: Kategorie-Sidebar ein-/ausblenden' },
]

const codeExamples = [
  {
    key: 'assets',
    title: '1. Bundle & Styles einbinden',
    code: `<link rel="stylesheet" href="/fileadmin/catalog-embed/catalog-embed.css">
<script src="/fileadmin/catalog-embed/catalog-embed.umd.js"><\/script>`,
  },
  {
    key: 'markup',
    title: '2. Platzhalter im Fluid-Template',
    code: `<!-- Kopfbereich: Suche, Sprache, Merkliste -->
<div data-catalog="search"></div>
<div data-catalog="locale"></div>
<div data-catalog="wishlist-button"></div>

<!-- Sidebar: Kategorien -->
<div data-catalog="categories"></div>

<!-- Hauptbereich: Toolbar, aktive Filter, Trefferliste, Paginierung -->
<div data-catalog="toolbar"></div>
<div data-catalog="active-filters"></div>
<div data-catalog="product-grid"></div>
<div data-catalog="pagination"></div>

<!-- Facetten-Panel -->
<div data-catalog="facets"></div>

<!-- Unsichtbar, bis benötigt: öffnen sich als Overlay/Drawer -->
<div data-catalog="wishlist"></div>
<div data-catalog="product-detail"></div>
<div data-catalog="compare"></div>`,
  },
  {
    key: 'init',
    title: '3. Initialisierung',
    code: `<script>
  PublixxCatalog.init({
    api: '${apiBase}',
    locale: 'de',
    perPage: 24,
  })
<\/script>`,
  },
  {
    key: 'theme',
    title: '4. Theming per CSS-Variablen (optional)',
    code: `:root {
  --pxc-primary: #2563eb;
  --pxc-primary-text: #ffffff;
  --pxc-accent: #dc2626;
  --pxc-font: 'Segoe UI', system-ui, sans-serif;
  --pxc-radius: 12px;
}`,
  },
]
</script>

<template>
  <div class="space-y-6 max-w-4xl mx-auto pb-12" data-testid="content-integration-typo3">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <ExternalLink class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">TYPO3-Integration</h2>
      <span class="pim-badge text-[10px] bg-[var(--color-success)]/10 text-[var(--color-success)]">Enterprise</span>
    </div>
    <p class="text-sm text-[var(--color-text-secondary)] -mt-3">
      Anleitung für die Agentur: So wird der anyPIM-Produktkatalog direkt in eine TYPO3-Website eingebettet —
      ohne iFrame und ohne separates Fenster. Diese Seite kann 1:1 an das TYPO3-Team weitergegeben werden.
    </p>

    <!-- Überblick -->
    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2">
      <div class="flex items-center gap-2">
        <Info class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Überblick</h3>
      </div>
      <p class="text-sm text-[var(--color-text-secondary)] leading-relaxed">
        Der Katalog wird als fertiges JavaScript-Widget (<code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">catalog-embed</code>)
        ausgeliefert und mountet sich direkt in normale <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">&lt;div&gt;</code>-Platzhalter
        auf der TYPO3-Seite. Es gibt keinen iFrame und kein Popup-Fenster — das Layout (Header, Sidebar, Footer)
        bleibt vollständig TYPO3, nur die Katalogflächen (Suche, Facetten, Trefferliste, Produktdetail, Merkliste,
        Vergleich) werden vom Widget befüllt und per REST-API gegen diese anyPIM-Instanz betrieben.
      </p>
      <p class="text-sm text-[var(--color-text-secondary)] leading-relaxed">
        Design/Theming erfolgt über CSS-Variablen, sodass sich der Katalog optisch in jedes TYPO3-Template
        einfügen lässt.
      </p>
    </section>

    <!-- Voraussetzungen -->
    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
      <div class="flex items-center gap-2">
        <ShieldCheck class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Voraussetzungen</h3>
      </div>
      <ul class="text-sm text-[var(--color-text-secondary)] space-y-1.5 list-disc pl-5">
        <li>
          API-Basis-URL dieser anyPIM-Instanz:
          <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">{{ apiBase }}</code>
        </li>
        <li>Aktuelles <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">catalog-embed.umd.js</code> +
          <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">catalog-embed.css</code> — bitte beim anyPIM-Betrieb anfordern
          und im TYPO3-Fileadmin bzw. als Extension-Ressource ablegen.</li>
        <li>CORS-Freigabe der TYPO3-Domain auf dieser anyPIM-Instanz (<code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">Access-Control-Allow-Origin</code>) —
          bitte vom anyPIM-Betrieb einrichten lassen, bevor die Integration produktiv geht.</li>
        <li>Optional: Bearer-Token, falls der Katalog nicht öffentlich zugänglich sein soll
          (<code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">PublixxCatalog.init({ token: '…' })</code>).</li>
      </ul>
    </section>

    <!-- Schritt für Schritt -->
    <section class="space-y-3">
      <div class="flex items-center gap-2">
        <ListChecks class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Schritt für Schritt</h3>
      </div>

      <div v-for="ex in codeExamples" :key="ex.key" class="rounded-lg border border-[var(--color-border)] overflow-hidden">
        <div class="flex items-center justify-between px-3 py-2 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
          <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ ex.title }}</span>
          <button
            class="pim-btn pim-btn-secondary text-[11px] py-1 px-2"
            @click="copyCode(ex.key, ex.code)"
          >
            <component :is="copiedKey === ex.key ? Check : Copy" class="w-3 h-3" :stroke-width="1.75" />
            {{ copiedKey === ex.key ? 'Kopiert' : 'Kopieren' }}
          </button>
        </div>
        <pre class="m-0 p-3 overflow-x-auto text-[11px] leading-relaxed bg-[var(--color-bg)] text-[var(--color-text-primary)]"><code>{{ ex.code }}</code></pre>
      </div>
    </section>

    <!-- Verfügbare Widgets -->
    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
      <div class="flex items-center gap-2">
        <Blocks class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Verfügbare Widgets (data-catalog)</h3>
      </div>
      <p class="text-sm text-[var(--color-text-secondary)]">
        Jeder Platzhalter wird über <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">data-catalog="…"</code> aktiviert
        und kann frei im TYPO3-Template platziert werden — Reihenfolge und Umgebung sind beliebig:
      </p>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-[var(--color-text-tertiary)] border-b border-[var(--color-border)]">
              <th class="py-1.5 pr-4 font-medium">data-catalog</th>
              <th class="py-1.5 font-medium">Funktion</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in widgets" :key="w.name" class="border-b border-[var(--color-border)] last:border-0">
              <td class="py-1.5 pr-4 font-mono text-[var(--color-accent)]">{{ w.name }}</td>
              <td class="py-1.5 text-[var(--color-text-secondary)]">{{ w.desc }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Theming Hinweis -->
    <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2">
      <div class="flex items-center gap-2">
        <Palette class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Deeplinks & Verhalten</h3>
      </div>
      <ul class="text-sm text-[var(--color-text-secondary)] space-y-1.5 list-disc pl-5">
        <li>Teilbare Such-Links funktionieren ohne Zutun: <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?sku=</code> (öffnet Produktdetail),
          <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?cat=</code>,
          <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?filters[…]=</code>,
          <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?wishlist=</code>.</li>
        <li>Die Merkliste wird clientseitig (localStorage) geführt, kein TYPO3-Login nötig.</li>
        <li>Mobil klappt <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">sidebar-toggle</code> die Kategorie-Sidebar als Drawer.</li>
      </ul>
    </section>

    <!-- Kontakt -->
    <section class="rounded-lg border border-dashed border-[var(--color-border)] p-4 flex items-start gap-3">
      <Mail class="w-4 h-4 mt-0.5 text-[var(--color-text-tertiary)] flex-shrink-0" :stroke-width="1.75" />
      <p class="text-sm text-[var(--color-text-secondary)]">
        Fragen zu Bundle-Auslieferung, CORS-Freigabe oder individuellem Theming bitte an den anyPIM-Betrieb
        richten — nicht direkt in dieser Ansicht konfigurierbar.
      </p>
    </section>
  </div>
</template>
