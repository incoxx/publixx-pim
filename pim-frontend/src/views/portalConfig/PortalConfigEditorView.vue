<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import {
  Save, ExternalLink, Plus, Trash2, GripVertical, Copy, Check,
  Globe, Languages, ChevronDown, LayoutGrid, Monitor, Tablet, Smartphone,
} from 'lucide-vue-next'
import portalConfigApi from '@/api/portalConfig'
import api from '@/api/api'

const route = useRoute()
const router = useRouter()

const portal = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const successMsg = ref('')
const activeTab = ref('settings')
const isDirty = ref(false)
const urlCopied = ref(false)
const previewDevice = ref('desktop')

const form = ref({
  name: '',
  slug: '',
  description: '',
  html_template: '',
  catalog_template_id: null,
  filter_steps: [],
  branding: { title: '', subtitle: '', hero_text: '', features: [] },
  css_variables: {},
  custom_css: '',
  default_locale: 'de',
  is_active: true,
  is_shared: true,
})

const catalogTemplates = ref([])
const attributes = ref([])
const attrSearch = ref('')

const tabs = [
  { key: 'settings', label: 'Einstellungen', icon: Globe },
  { key: 'branding', label: 'Branding', icon: LayoutGrid },
  { key: 'filters', label: 'Filter-Steps', icon: ChevronDown },
  { key: 'template', label: 'HTML-Template', icon: Monitor },
  { key: 'css', label: 'CSS', icon: Languages },
]

const WIDGET_SNIPPETS = [
  { name: 'Branding / Hero', tag: 'branding', snippet: '<div data-portal="branding"></div>' },
  { name: 'Laender-Karten', tag: 'country-select', snippet: '<div data-portal="country-select"></div>' },
  { name: 'Sprach-Badges', tag: 'language-select', snippet: '<div data-portal="language-select"></div>' },
  { name: 'Filter-Dropdown', tag: 'filter-dropdown', snippet: '<div data-portal="filter-dropdown"></div>' },
  { name: 'Filter-Karten', tag: 'filter-cards', snippet: '<div data-portal="filter-cards"></div>' },
  { name: 'Weiter-Button', tag: 'submit-button', snippet: '<div data-portal="submit-button"></div>' },
]

const portalUrl = computed(() =>
  portal.value ? `${window.location.origin}/portal/${form.value.slug}` : ''
)

const filteredAttributes = computed(() => {
  const term = attrSearch.value.toLowerCase()
  if (!term) return attributes.value
  return attributes.value.filter(a =>
    (a.name_de || '').toLowerCase().includes(term) ||
    (a.technical_name || '').toLowerCase().includes(term)
  )
})

const previewWidth = computed(() => {
  if (previewDevice.value === 'tablet') return '768px'
  if (previewDevice.value === 'mobile') return '375px'
  return '100%'
})

// ── Lifecycle ──

onMounted(async () => {
  const id = route.params.id
  try {
    const [portalRes, templatesRes, attrsRes] = await Promise.all([
      portalConfigApi.show(id),
      api.get('/catalog-templates'),
      api.get('/attributes', { params: { per_page: 500 } }),
    ])

    portal.value = portalRes.data.data
    catalogTemplates.value = templatesRes.data.data || []
    attributes.value = attrsRes.data.data || []

    Object.assign(form.value, {
      name: portal.value.name || '',
      slug: portal.value.slug || '',
      description: portal.value.description || '',
      html_template: portal.value.html_template || '',
      catalog_template_id: portal.value.catalog_template_id || null,
      filter_steps: portal.value.filter_steps || [],
      branding: portal.value.branding || { title: '', subtitle: '', hero_text: '', features: [] },
      css_variables: portal.value.css_variables || {},
      custom_css: portal.value.custom_css || '',
      default_locale: portal.value.default_locale || 'de',
      is_active: portal.value.is_active ?? true,
      is_shared: portal.value.is_shared ?? true,
    })

    await nextTick()
    isDirty.value = false
  } catch (e) {
    error.value = 'Portal konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
})

watch(form, () => { isDirty.value = true }, { deep: true })

// Unsaved changes protection
function onBeforeUnload(e) {
  if (isDirty.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

onBeforeRouteLeave(() => {
  if (isDirty.value && !confirm('Es gibt ungespeicherte Aenderungen. Seite wirklich verlassen?')) {
    return false
  }
})

// ── Actions ──

async function save() {
  saving.value = true
  error.value = ''
  successMsg.value = ''
  try {
    const { data } = await portalConfigApi.update(route.params.id, form.value)
    portal.value = data.data
    isDirty.value = false
    successMsg.value = 'Gespeichert!'
    setTimeout(() => { successMsg.value = '' }, 2000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Speichern.'
  } finally {
    saving.value = false
  }
}

function addFilterStep() {
  form.value.filter_steps.push({
    key: '',
    attribute_id: '',
    widget: 'filter-dropdown',
    label: '',
    derive_locale: false,
  })
}

function removeFilterStep(index) {
  form.value.filter_steps.splice(index, 1)
}

function moveFilterStep(from, to) {
  if (to < 0 || to >= form.value.filter_steps.length) return
  const steps = form.value.filter_steps
  const [item] = steps.splice(from, 1)
  steps.splice(to, 0, item)
}

function addFeature() {
  if (!form.value.branding.features) form.value.branding.features = []
  form.value.branding.features.push('')
}

function removeFeature(index) {
  form.value.branding.features.splice(index, 1)
}

function insertSnippet(snippet) {
  // Ans Ende des Templates anfuegen
  if (form.value.html_template && !form.value.html_template.endsWith('\n')) {
    form.value.html_template += '\n'
  }
  form.value.html_template += snippet + '\n'
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(portalUrl.value)
    urlCopied.value = true
    setTimeout(() => { urlCopied.value = false }, 2000)
  } catch {
    // Fallback
    const input = document.createElement('input')
    input.value = portalUrl.value
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    urlCopied.value = true
    setTimeout(() => { urlCopied.value = false }, 2000)
  }
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <button class="text-xs text-base-content/50 hover:text-primary mb-1" @click="router.push('/portal-config')">
          &larr; Alle Portale
        </button>
        <h1 class="text-xl font-bold flex items-center gap-2">
          {{ form.name || 'Portal Editor' }}
          <span v-if="isDirty" class="badge badge-warning badge-xs">Nicht gespeichert</span>
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <span v-if="successMsg" class="text-sm text-success font-medium">{{ successMsg }}</span>
        <span v-if="error" class="text-sm text-error">{{ error }}</span>
        <a v-if="portalUrl" :href="portalUrl" target="_blank" class="btn btn-sm btn-ghost" title="Portal oeffnen">
          <ExternalLink class="w-4 h-4" />
        </a>
        <button class="btn btn-sm btn-primary" :disabled="saving || !isDirty" @click="save">
          <Save class="w-4 h-4" />
          {{ saving ? 'Speichern...' : 'Speichern' }}
        </button>
      </div>
    </div>

    <!-- Portal URL (kopierbar) -->
    <div v-if="portalUrl && !loading" class="mb-6 flex items-center gap-2 p-3 bg-base-200 rounded-lg">
      <Globe class="w-4 h-4 text-base-content/40 flex-shrink-0" />
      <code class="text-xs flex-1 text-base-content/70 truncate">{{ portalUrl }}</code>
      <button class="btn btn-ghost btn-xs" @click="copyUrl" :title="urlCopied ? 'Kopiert!' : 'URL kopieren'">
        <Check v-if="urlCopied" class="w-3.5 h-3.5 text-success" />
        <Copy v-else class="w-3.5 h-3.5" />
      </button>
      <a :href="portalUrl" target="_blank" class="btn btn-ghost btn-xs" title="Oeffnen">
        <ExternalLink class="w-3.5 h-3.5" />
      </a>
    </div>

    <div v-if="loading" class="text-center py-12 opacity-50">Lade...</div>

    <template v-else>
      <!-- Tabs -->
      <div class="tabs tabs-bordered mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab gap-1.5"
          :class="{ 'tab-active': activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <component :is="tab.icon" class="w-3.5 h-3.5" />
          {{ tab.label }}
        </button>
      </div>

      <!-- ═══ Tab: Einstellungen ═══ -->
      <div v-show="activeTab === 'settings'" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label text-sm font-semibold">Name</label>
            <input v-model="form.name" class="input input-bordered w-full input-sm" />
          </div>
          <div>
            <label class="label text-sm font-semibold">Slug</label>
            <input v-model="form.slug" class="input input-bordered w-full input-sm" placeholder="auto-generiert" />
          </div>
        </div>
        <div>
          <label class="label text-sm font-semibold">Beschreibung</label>
          <textarea v-model="form.description" class="textarea textarea-bordered w-full textarea-sm" rows="2"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label text-sm font-semibold">Katalogvorlage</label>
            <select v-model="form.catalog_template_id" class="select select-bordered w-full select-sm">
              <option :value="null">— Keine —</option>
              <option v-for="t in catalogTemplates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.slug }})</option>
            </select>
            <p class="text-xs text-base-content/50 mt-1">Nach der Vorschaltseite wird der Benutzer hierhin weitergeleitet.</p>
          </div>
          <div>
            <label class="label text-sm font-semibold">Standard-Sprache</label>
            <select v-model="form.default_locale" class="select select-bordered w-full select-sm">
              <option value="de">Deutsch</option>
              <option value="en">English</option>
            </select>
          </div>
        </div>
        <div class="flex gap-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="toggle toggle-sm toggle-primary" />
            <span class="text-sm">Aktiv</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_shared" class="toggle toggle-sm" />
            <span class="text-sm">Geteilt</span>
          </label>
        </div>
      </div>

      <!-- ═══ Tab: Branding ═══ -->
      <div v-show="activeTab === 'branding'" class="space-y-4">
        <div>
          <label class="label text-sm font-semibold">Titel</label>
          <input v-model="form.branding.title" class="input input-bordered w-full input-sm" placeholder="z.B. Produktdokumentation" />
        </div>
        <div>
          <label class="label text-sm font-semibold">Untertitel</label>
          <input v-model="form.branding.subtitle" class="input input-bordered w-full input-sm" placeholder="z.B. Digitale Produktdokumentation" />
        </div>
        <div>
          <label class="label text-sm font-semibold">Hero-Text</label>
          <textarea v-model="form.branding.hero_text" class="textarea textarea-bordered w-full textarea-sm" rows="3" placeholder="Beschreibungstext fuer die Vorschaltseite..."></textarea>
        </div>
        <div>
          <label class="label text-sm font-semibold">Feature-Liste</label>
          <p class="text-xs text-base-content/50 mb-2">Vorteile/Features die auf der Vorschaltseite mit Haekchen angezeigt werden.</p>
          <div class="space-y-2">
            <div v-for="(feature, i) in (form.branding.features || [])" :key="i" class="flex gap-2 items-center">
              <span class="text-base-content/30 text-xs w-5 text-center">{{ i + 1 }}</span>
              <input v-model="form.branding.features[i]" class="input input-bordered flex-1 input-sm" placeholder="z.B. Sofortiger digitaler Zugriff" />
              <button class="btn btn-ghost btn-sm text-error" @click="removeFeature(i)" title="Entfernen">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
            <button class="btn btn-ghost btn-xs" @click="addFeature">
              <Plus class="w-3.5 h-3.5" /> Feature hinzufuegen
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ Tab: Filter-Steps ═══ -->
      <div v-show="activeTab === 'filters'" class="space-y-4">
        <div class="p-3 bg-base-200 rounded-lg text-sm text-base-content/70">
          Filter-Steps definieren die Auswahl-Schritte auf der Vorschaltseite.
          Der Benutzer trifft seine Auswahl und wird dann mit vorgegebenen Filtern zur Katalogvorlage weitergeleitet.
        </div>

        <div v-if="form.filter_steps.length === 0" class="text-center py-8 border-2 border-dashed border-base-300 rounded-lg">
          <ChevronDown class="w-8 h-8 mx-auto mb-2 opacity-20" />
          <p class="text-sm text-base-content/50 mb-3">Noch keine Filter-Steps definiert</p>
          <button class="btn btn-sm btn-primary" @click="addFilterStep">
            <Plus class="w-4 h-4" /> Ersten Filter-Step anlegen
          </button>
        </div>

        <div v-for="(step, i) in form.filter_steps" :key="i" class="p-4 border border-base-300 rounded-lg space-y-3 bg-base-100">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="flex flex-col gap-0.5">
                <button class="btn btn-ghost btn-xs px-1" :disabled="i === 0" @click="moveFilterStep(i, i - 1)" title="Nach oben">&#9650;</button>
                <button class="btn btn-ghost btn-xs px-1" :disabled="i === form.filter_steps.length - 1" @click="moveFilterStep(i, i + 1)" title="Nach unten">&#9660;</button>
              </div>
              <span class="badge badge-sm badge-primary">Step {{ i + 1 }}</span>
              <span v-if="step.label" class="text-sm font-medium">{{ step.label }}</span>
            </div>
            <button class="btn btn-ghost btn-xs text-error" @click="removeFilterStep(i)" title="Step entfernen">
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold mb-1 block">Key <span class="font-normal text-base-content/40">(URL-Parameter)</span></label>
              <input v-model="step.key" class="input input-bordered w-full input-xs" placeholder="z.B. country" />
            </div>
            <div>
              <label class="text-xs font-semibold mb-1 block">Label <span class="font-normal text-base-content/40">(Anzeige)</span></label>
              <input v-model="step.label" class="input input-bordered w-full input-xs" placeholder="z.B. Land waehlen" />
            </div>
            <div>
              <label class="text-xs font-semibold mb-1 block">Attribut</label>
              <input v-model="attrSearch" class="input input-bordered w-full input-xs mb-1" placeholder="Attribut suchen..." />
              <select v-model="step.attribute_id" class="select select-bordered w-full select-xs">
                <option value="">— Attribut waehlen —</option>
                <option v-for="attr in filteredAttributes" :key="attr.id" :value="attr.id">
                  {{ attr.name_de || attr.technical_name }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold mb-1 block">Widget-Typ</label>
              <div class="grid grid-cols-2 gap-1.5 mt-1">
                <button
                  v-for="w in [
                    { value: 'country-select', label: 'Laender', icon: '🌍' },
                    { value: 'language-select', label: 'Sprache', icon: '🗣' },
                    { value: 'filter-dropdown', label: 'Dropdown', icon: '▼' },
                    { value: 'filter-cards', label: 'Karten', icon: '▦' },
                  ]"
                  :key="w.value"
                  class="btn btn-xs"
                  :class="step.widget === w.value ? 'btn-primary' : 'btn-ghost border border-base-300'"
                  @click="step.widget = w.value"
                >
                  {{ w.icon }} {{ w.label }}
                </button>
              </div>
            </div>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="step.derive_locale" class="checkbox checkbox-xs" />
            <span class="text-xs">Sprache automatisch aus Auswahl ableiten (z.B. DE → Deutsch)</span>
          </label>
        </div>

        <button v-if="form.filter_steps.length > 0" class="btn btn-sm btn-outline" @click="addFilterStep">
          <Plus class="w-4 h-4" /> Weiteren Step hinzufuegen
        </button>
      </div>

      <!-- ═══ Tab: HTML-Template ═══ -->
      <div v-show="activeTab === 'template'">
        <!-- Widget-Palette -->
        <div class="mb-4 p-3 bg-base-200 rounded-lg">
          <p class="text-xs font-semibold mb-2">Widget einfuegen:</p>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="w in WIDGET_SNIPPETS"
              :key="w.tag"
              class="btn btn-xs btn-ghost border border-base-300 font-mono"
              @click="insertSnippet(w.snippet)"
              :title="w.snippet"
            >
              {{ w.name }}
            </button>
          </div>
        </div>

        <div class="grid gap-4" :class="previewDevice !== 'off' ? 'grid-cols-2' : ''">
          <!-- Editor -->
          <div>
            <textarea
              v-model="form.html_template"
              class="textarea textarea-bordered w-full font-mono text-xs leading-relaxed"
              rows="30"
              spellcheck="false"
            ></textarea>
          </div>

          <!-- Live Preview -->
          <div v-if="previewDevice !== 'off'">
            <div class="flex items-center gap-1 mb-2">
              <span class="text-xs font-semibold mr-2">Vorschau:</span>
              <button class="btn btn-ghost btn-xs" :class="{ 'btn-active': previewDevice === 'desktop' }" @click="previewDevice = 'desktop'" title="Desktop"><Monitor class="w-3.5 h-3.5" /></button>
              <button class="btn btn-ghost btn-xs" :class="{ 'btn-active': previewDevice === 'tablet' }" @click="previewDevice = 'tablet'" title="Tablet"><Tablet class="w-3.5 h-3.5" /></button>
              <button class="btn btn-ghost btn-xs" :class="{ 'btn-active': previewDevice === 'mobile' }" @click="previewDevice = 'mobile'" title="Mobile"><Smartphone class="w-3.5 h-3.5" /></button>
            </div>
            <div class="border border-base-300 rounded-lg overflow-hidden bg-white" :style="{ maxWidth: previewWidth }">
              <iframe
                :srcdoc="form.html_template"
                class="w-full border-0"
                style="height: 500px;"
                sandbox="allow-scripts"
              ></iframe>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ Tab: CSS ═══ -->
      <div v-show="activeTab === 'css'" class="space-y-4">
        <div class="p-3 bg-base-200 rounded-lg text-xs text-base-content/60">
          <p class="font-semibold mb-1">Verfuegbare CSS-Variablen:</p>
          <code class="block">--pe-primary, --pe-primary-text, --pe-dark, --pe-surface, --pe-border, --pe-text, --pe-text-muted, --pe-radius, --pe-font</code>
        </div>
        <textarea
          v-model="form.custom_css"
          class="textarea textarea-bordered w-full font-mono text-xs leading-relaxed"
          rows="20"
          spellcheck="false"
          placeholder=":root { --pe-primary: #00AEEF; --pe-dark: #004D6D; }"
        ></textarea>
      </div>
    </template>
  </div>
</template>
