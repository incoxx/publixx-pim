<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  ExternalLink, Info, ShieldCheck, Blocks, ListChecks, Copy, Check, Mail,
  Globe, Network, Braces, Lock, AlertTriangle,
} from 'lucide-vue-next'
import client from '@/api/client'
import apiTemplatesApi from '@/api/apiTemplates'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import { useClipboard } from '@/composables/useClipboard'

const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const { copy } = useClipboard()

const copiedKey = ref(null)
async function copyCode(key, code) {
  await copy(code)
  copiedKey.value = key
  setTimeout(() => { if (copiedKey.value === key) copiedKey.value = null }, 2000)
}

// Basis-URL dieser anyPIM-Instanz — als Kopiervorlage für die Agentur
const apiBase = `${window.location.origin}/api/v1`

const MODES = [
  { key: 'cors', icon: Globe, label: 'Getrennte Domains (CORS)' },
  { key: 'reverse_proxy', icon: Network, label: 'Reverse-Proxy (eine Domain)' },
  { key: 'api_designer', icon: Braces, label: 'Headless (API Designer)' },
]

// ── Betriebsmodus-Konfiguration (Setting "typo3_integration") ──
const loading = ref(true)
const notLicensed = ref(false)
const loadError = ref('')
const saving = ref(false)
const saveMessage = ref('')
const saveError = ref('')

const mode = ref('cors')
const corsOrigin = ref('')
const reverseProxyPath = ref('/pim-api')
const apiTemplateId = ref('')

const apiTemplates = ref([])
const apiTemplatesLoaded = ref(false)
const apiDesignerLicensed = computed(() => licenseStore.isModuleActive('api_designer'))
const selectedTemplate = computed(() => apiTemplates.value.find(t => t.id === apiTemplateId.value) || null)

async function load() {
  loading.value = true
  loadError.value = ''
  notLicensed.value = false
  try {
    const { data } = await client.get('/settings/typo3-integration')
    const payload = data.data || {}
    mode.value = payload.mode || 'cors'
    corsOrigin.value = payload.cors_origin || ''
    reverseProxyPath.value = payload.reverse_proxy_path || '/pim-api'
    apiTemplateId.value = payload.api_template_id || ''
    if (mode.value === 'api_designer') loadApiTemplates()
  } catch (e) {
    if (e.response?.status === 403) {
      notLicensed.value = true
    } else {
      loadError.value = e.response?.data?.message || 'Konfiguration konnte nicht geladen werden.'
    }
  } finally {
    loading.value = false
  }
}

async function loadApiTemplates() {
  if (apiTemplatesLoaded.value || !apiDesignerLicensed.value) return
  try {
    const { data } = await apiTemplatesApi.list()
    apiTemplates.value = data.data || data
    apiTemplatesLoaded.value = true
  } catch (e) { /* Manuelle Anleitung bleibt trotzdem nutzbar */ }
}

function selectMode(key) {
  mode.value = key
  if (key === 'api_designer') loadApiTemplates()
}

async function save() {
  saving.value = true
  saveMessage.value = ''
  saveError.value = ''
  try {
    const { data } = await client.put('/settings/typo3-integration', {
      mode: mode.value,
      cors_origin: corsOrigin.value || null,
      reverse_proxy_path: reverseProxyPath.value || null,
      api_template_id: apiTemplateId.value || null,
    })
    saveMessage.value = data.message || 'Gespeichert.'
    setTimeout(() => { saveMessage.value = '' }, 3000)
  } catch (e) {
    const errors = e.response?.data?.errors
    saveError.value = (errors ? Object.values(errors).flat().join(' ') : '') || e.response?.data?.message || 'Speichern fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}

onMounted(load)

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

// ── Code-Beispiele je Betriebsmodus ──
const widgetCodeExamples = computed(() => {
  const initApi = mode.value === 'reverse_proxy' ? reverseProxyPath.value || '/pim-api' : apiBase
  const examples = [
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
    api: '${initApi}',
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

  if (mode.value === 'reverse_proxy') {
    examples.push({
      key: 'proxy',
      title: '5. Reverse-Proxy im Webserver (Beispiel nginx)',
      code: `location ${reverseProxyPath.value || '/pim-api'}/ {
    proxy_pass ${apiBase}/;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}`,
    })
  }

  return examples
})

const streamUrl = computed(() => {
  if (!selectedTemplate.value) return `${apiBase}/api-streams/<slug-des-templates>`
  return `${apiBase}/api-streams/${selectedTemplate.value.slug}`
})

const streamCurlExample = computed(() => {
  const url = `${streamUrl.value}?limit=24`
  const tmpl = selectedTemplate.value
  if (!tmpl || tmpl.auth_type === 'none') {
    return `curl '${url}'`
  }
  if (tmpl.auth_type === 'api_key') {
    return `curl -H "X-Api-Key: <IHR-API-KEY>" '${url}'`
  }
  return `# auth_type "bearer" nutzt eine anyPIM-Benutzersitzung — für ein\n# externes CMS ist "api_key" die passende Wahl (im API Designer umstellbar).\ncurl -H "Authorization: Bearer <TOKEN>" '${url}'`
})

const streamPhpExample = computed(() => `<?php
// Extbase-Controller oder eigener Service — serverseitiger Pull, kein CORS nötig
$response = (new \\GuzzleHttp\\Client())->get('${streamUrl.value}', [
    'query' => ['limit' => 24],
    'headers' => ['X-Api-Key' => getenv('ANYPIM_API_KEY')],
]);
$products = json_decode((string) $response->getBody(), true)['data'] ?? [];`)
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
      Anleitung für die Agentur: So wird der anyPIM-Produktkatalog in eine TYPO3-Website eingebettet —
      ohne iFrame und ohne separates Fenster. Diese Seite kann 1:1 an das TYPO3-Team weitergegeben werden.
    </p>

    <!-- Nicht lizenziert -->
    <div v-if="notLicensed" class="rounded-lg border border-dashed border-[var(--color-border)] p-6 flex items-start gap-3">
      <Lock class="w-5 h-5 mt-0.5 text-[var(--color-text-tertiary)] flex-shrink-0" :stroke-width="1.75" />
      <p class="text-sm text-[var(--color-text-secondary)]">
        Das Modul „TYPO3-Integration" ist für diese Instanz nicht lizenziert. Bitte beim anyPIM-Betrieb aktivieren lassen.
      </p>
    </div>

    <template v-else>
      <!-- Betriebsmodus-Auswahl -->
      <section class="space-y-3">
        <div class="flex items-center gap-2">
          <ShieldCheck class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Betriebsmodus</h3>
        </div>

        <div class="flex border-b border-[var(--color-border)]">
          <button
            v-for="m in MODES"
            :key="m.key"
            :class="[
              'flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 -mb-px transition-colors',
              mode === m.key
                ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
                : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]',
            ]"
            @click="selectMode(m.key)"
          >
            <component :is="m.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ m.label }}
          </button>
        </div>

        <div v-if="loading" class="text-xs text-[var(--color-text-tertiary)]">Lädt…</div>
        <div v-else class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
          <!-- Modus-spezifische Konfigurationsfelder -->
          <div v-if="mode === 'cors'" class="space-y-2">
            <label class="text-xs font-medium text-[var(--color-text-primary)]">TYPO3-Domain (Origin)</label>
            <input
              v-model="corsOrigin"
              type="text"
              placeholder="https://www.kunde.de"
              :disabled="!authStore.isAdmin"
              class="w-full max-w-sm rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-2.5 py-1.5 text-sm text-[var(--color-text-primary)] disabled:opacity-60"
            >
            <p class="text-xs text-[var(--color-text-tertiary)]">
              Wird als zusätzliche erlaubte CORS-Origin freigeschaltet — nur Schema + Host + optionaler Port, kein Pfad.
            </p>
          </div>

          <div v-else-if="mode === 'reverse_proxy'" class="space-y-2">
            <label class="text-xs font-medium text-[var(--color-text-primary)]">Proxy-Pfad auf der TYPO3-Domain</label>
            <input
              v-model="reverseProxyPath"
              type="text"
              placeholder="/pim-api"
              :disabled="!authStore.isAdmin"
              class="w-full max-w-sm rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-2.5 py-1.5 text-sm text-[var(--color-text-primary)] disabled:opacity-60"
            >
            <p class="text-xs text-[var(--color-text-tertiary)]">
              Rein informativ für die Code-Beispiele unten — der Webserver-Proxy selbst wird beim Kunden/der Agentur eingerichtet, nicht hier.
            </p>
          </div>

          <div v-else class="space-y-2">
            <label class="text-xs font-medium text-[var(--color-text-primary)]">API-Designer-Profil</label>
            <div v-if="!apiDesignerLicensed" class="flex items-center gap-2 text-xs text-[var(--color-text-tertiary)]">
              <AlertTriangle class="w-3.5 h-3.5" :stroke-width="1.75" />
              Zusätzlich benötigt: Enterprise-Modul „API-Designer".
            </div>
            <select
              v-else
              v-model="apiTemplateId"
              :disabled="!authStore.isAdmin"
              class="w-full max-w-sm rounded border border-[var(--color-border)] bg-[var(--color-bg)] px-2.5 py-1.5 text-sm text-[var(--color-text-primary)] disabled:opacity-60"
            >
              <option value="">— Profil wählen —</option>
              <option v-for="t in apiTemplates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.output_format }})</option>
            </select>
            <p class="text-xs text-[var(--color-text-tertiary)]">
              Profile werden im <router-link to="/api-designer" class="text-[var(--color-accent)] hover:underline">API Designer</router-link> angelegt — dort wird auch der API-Key vergeben.
            </p>
          </div>

          <div v-if="authStore.isAdmin" class="flex items-center gap-2 pt-1">
            <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="save">
              {{ saving ? 'Speichert…' : 'Speichern' }}
            </button>
            <span v-if="saveMessage" class="text-xs text-[var(--color-success)]">{{ saveMessage }}</span>
            <span v-if="saveError" class="text-xs text-[var(--color-error)]">{{ saveError }}</span>
          </div>
        </div>
      </section>

      <!-- Überblick -->
      <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2">
        <div class="flex items-center gap-2">
          <Info class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Überblick</h3>
        </div>
        <p v-if="mode !== 'api_designer'" class="text-sm text-[var(--color-text-secondary)] leading-relaxed">
          Der Katalog wird als fertiges JavaScript-Widget (<code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">catalog-embed</code>)
          ausgeliefert und mountet sich direkt in normale <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">&lt;div&gt;</code>-Platzhalter
          auf der TYPO3-Seite. Es gibt keinen iFrame und kein Popup-Fenster — das Layout (Header, Sidebar, Footer)
          bleibt vollständig TYPO3, nur die Katalogflächen werden vom Widget befüllt.
          <template v-if="mode === 'cors'">Es läuft dabei gegen die öffentliche API-Domain dieser anyPIM-Instanz, die TYPO3-Domain muss dafür als CORS-Origin freigeschaltet sein (s. o.).</template>
          <template v-else>Die TYPO3-Domain reicht Anfragen unter einem eigenen Pfad an anyPIM durch — dieselbe Domain, kein CORS nötig.</template>
        </p>
        <p v-else class="text-sm text-[var(--color-text-secondary)] leading-relaxed">
          Statt des JS-Widgets zieht TYPO3 hier die Produktdaten <strong>serverseitig</strong> über ein selbst
          definiertes API-Designer-Profil — mit exakt den Feldnamen und der Verschachtelung, die das
          Fluid-Template braucht (statt des festen Katalog-Vertrags). Geeignet für SSR-Produktseiten, Cron-Importe
          oder eigene Such-/Listenlogik. <strong>Kein Ersatz</strong> für interaktive Facettensuche, Vergleich oder
          Merkliste — dafür bleibt der Widget-Weg (CORS/Reverse-Proxy) zuständig.
        </p>
      </section>

      <!-- Weg 1+2: Widget-Einbindung -->
      <template v-if="mode !== 'api_designer'">
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
            <li v-if="mode === 'cors'">CORS-Freigabe der TYPO3-Domain (oben konfiguriert) — bitte vor dem Go-Live speichern.</li>
            <li v-else>Reverse-Proxy-Regel auf dem TYPO3-Webserver, die den Pfad oben an <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">{{ apiBase }}</code> durchreicht (Beispiel unten).</li>
            <li>Optional: Bearer-Token, falls der Katalog nicht öffentlich zugänglich sein soll
              (<code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">PublixxCatalog.init({ token: '…' })</code>).</li>
          </ul>
        </section>

        <section class="space-y-3">
          <div class="flex items-center gap-2">
            <ListChecks class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Schritt für Schritt</h3>
          </div>

          <div v-for="ex in widgetCodeExamples" :key="ex.key" class="rounded-lg border border-[var(--color-border)] overflow-hidden">
            <div class="flex items-center justify-between px-3 py-2 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
              <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ ex.title }}</span>
              <button class="pim-btn pim-btn-secondary text-[11px] py-1 px-2" @click="copyCode(ex.key, ex.code)">
                <component :is="copiedKey === ex.key ? Check : Copy" class="w-3 h-3" :stroke-width="1.75" />
                {{ copiedKey === ex.key ? 'Kopiert' : 'Kopieren' }}
              </button>
            </div>
            <pre class="m-0 p-3 overflow-x-auto text-[11px] leading-relaxed bg-[var(--color-bg)] text-[var(--color-text-primary)]"><code>{{ ex.code }}</code></pre>
          </div>
        </section>

        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
          <div class="flex items-center gap-2">
            <Blocks class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Verfügbare Widgets (data-catalog)</h3>
          </div>
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

        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Deeplinks & Verhalten</h3>
          <ul class="text-sm text-[var(--color-text-secondary)] space-y-1.5 list-disc pl-5">
            <li>Teilbare Such-Links funktionieren ohne Zutun: <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?sku=</code> (öffnet Produktdetail),
              <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?cat=</code>,
              <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?filters[…]=</code>,
              <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">?wishlist=</code>.</li>
            <li>Die Merkliste wird clientseitig (localStorage) geführt, kein TYPO3-Login nötig.</li>
            <li>Mobil klappt <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">sidebar-toggle</code> die Kategorie-Sidebar als Drawer.</li>
          </ul>
        </section>
      </template>

      <!-- Weg 3: API Designer -->
      <template v-else>
        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Stream-URL</h3>
          <p class="text-sm text-[var(--color-text-secondary)]">
            <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)] break-all">{{ streamUrl }}</code>
          </p>
          <p v-if="selectedTemplate" class="text-xs text-[var(--color-text-tertiary)]">
            Auth: <strong>{{ selectedTemplate.auth_type }}</strong> · Format: <strong>{{ selectedTemplate.output_format }}</strong> ·
            <router-link :to="`/api-designer/${selectedTemplate.id}`" class="text-[var(--color-accent)] hover:underline">im API Designer öffnen →</router-link>
          </p>
          <p v-else class="text-xs text-[var(--color-text-tertiary)]">
            Noch kein Profil ausgewählt — oben ein bestehendes wählen oder im
            <router-link to="/api-designer" class="text-[var(--color-accent)] hover:underline">API Designer</router-link> ein neues anlegen.
          </p>
        </section>

        <section class="space-y-3">
          <div v-for="ex in [
            { key: 'curl', title: 'Beispiel: curl', code: streamCurlExample },
            { key: 'php', title: 'Beispiel: serverseitiger Abruf (Extbase/PHP)', code: streamPhpExample },
          ]" :key="ex.key" class="rounded-lg border border-[var(--color-border)] overflow-hidden">
            <div class="flex items-center justify-between px-3 py-2 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
              <span class="text-xs font-medium text-[var(--color-text-primary)]">{{ ex.title }}</span>
              <button class="pim-btn pim-btn-secondary text-[11px] py-1 px-2" @click="copyCode(ex.key, ex.code)">
                <component :is="copiedKey === ex.key ? Check : Copy" class="w-3 h-3" :stroke-width="1.75" />
                {{ copiedKey === ex.key ? 'Kopiert' : 'Kopieren' }}
              </button>
            </div>
            <pre class="m-0 p-3 overflow-x-auto text-[11px] leading-relaxed bg-[var(--color-bg)] text-[var(--color-text-primary)]"><code>{{ ex.code }}</code></pre>
          </div>
        </section>

        <section class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-2">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Unterstützte Query-Parameter</h3>
          <ul class="text-sm text-[var(--color-text-secondary)] space-y-1.5 list-disc pl-5">
            <li><code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">limit</code> / <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">offset</code> — Paginierung</li>
            <li><code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">since</code> — nur seit diesem ISO-Datum geänderte Produkte (Delta-Sync)</li>
            <li><code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">sort</code> / <code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">order</code> — Sortierung</li>
            <li><code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">fields</code> — nur bestimmte JSON-Schlüssel liefern</li>
            <li><code class="text-xs px-1 py-0.5 rounded bg-[var(--color-bg)] border border-[var(--color-border)]">lang</code> — Sprache überschreiben</li>
          </ul>
          <p class="text-xs text-[var(--color-text-tertiary)] pt-1">
            Filterung erfolgt über das im Profil hinterlegte Such-Profil (nicht per Request) — keine Facetten, keine Freitextsuche auf diesem Weg.
          </p>
        </section>
      </template>

      <!-- Kontakt -->
      <section class="rounded-lg border border-dashed border-[var(--color-border)] p-4 flex items-start gap-3">
        <Mail class="w-4 h-4 mt-0.5 text-[var(--color-text-tertiary)] flex-shrink-0" :stroke-width="1.75" />
        <p class="text-sm text-[var(--color-text-secondary)]">
          Fragen zu Bundle-Auslieferung, Reverse-Proxy-Einrichtung oder individuellem Theming bitte an den
          anyPIM-Betrieb richten — nicht direkt in dieser Ansicht konfigurierbar.
        </p>
      </section>
    </template>
  </div>
</template>
