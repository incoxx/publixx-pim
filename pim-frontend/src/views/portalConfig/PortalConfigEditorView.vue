<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Save, Eye, ExternalLink, Plus, Trash2, GripVertical } from 'lucide-vue-next'
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

// Form-Daten
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

const tabs = [
  { key: 'settings', label: 'Einstellungen' },
  { key: 'branding', label: 'Branding' },
  { key: 'filters', label: 'Filter-Steps' },
  { key: 'template', label: 'HTML-Template' },
  { key: 'css', label: 'CSS' },
]

const portalUrl = computed(() =>
  portal.value ? `${window.location.origin}/portal/${form.value.slug}` : ''
)

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

    // Form fuellen
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
  } catch (e) {
    error.value = 'Portal konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
})

watch(form, () => { isDirty.value = true }, { deep: true })

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

function addFeature() {
  if (!form.value.branding.features) form.value.branding.features = []
  form.value.branding.features.push('')
}

function removeFeature(index) {
  form.value.branding.features.splice(index, 1)
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <button class="text-xs text-base-content/50 hover:text-primary mb-1" @click="router.push('/portal-config')">
          &larr; Alle Portale
        </button>
        <h1 class="text-xl font-bold">{{ form.name || 'Portal Editor' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <span v-if="successMsg" class="text-sm text-success">{{ successMsg }}</span>
        <span v-if="error" class="text-sm text-error">{{ error }}</span>
        <a v-if="portalUrl" :href="portalUrl" target="_blank" class="btn btn-sm btn-ghost" title="Vorschau">
          <ExternalLink class="w-4 h-4" />
        </a>
        <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
          <Save class="w-4 h-4" />
          {{ saving ? 'Speichern...' : 'Speichern' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 opacity-50">Lade...</div>

    <template v-else>
      <!-- Tabs -->
      <div class="tabs tabs-bordered mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab"
          :class="{ 'tab-active': activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab: Einstellungen -->
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
            <input type="checkbox" v-model="form.is_active" class="checkbox checkbox-sm" />
            <span class="text-sm">Aktiv</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_shared" class="checkbox checkbox-sm" />
            <span class="text-sm">Geteilt</span>
          </label>
        </div>
      </div>

      <!-- Tab: Branding -->
      <div v-show="activeTab === 'branding'" class="space-y-4">
        <div>
          <label class="label text-sm font-semibold">Titel</label>
          <input v-model="form.branding.title" class="input input-bordered w-full input-sm" placeholder="Produktdokumentation" />
        </div>
        <div>
          <label class="label text-sm font-semibold">Untertitel</label>
          <input v-model="form.branding.subtitle" class="input input-bordered w-full input-sm" placeholder="Digitale Produktdokumentation" />
        </div>
        <div>
          <label class="label text-sm font-semibold">Hero-Text</label>
          <textarea v-model="form.branding.hero_text" class="textarea textarea-bordered w-full textarea-sm" rows="2"></textarea>
        </div>
        <div>
          <label class="label text-sm font-semibold">Features</label>
          <div class="space-y-2">
            <div v-for="(feature, i) in (form.branding.features || [])" :key="i" class="flex gap-2">
              <input v-model="form.branding.features[i]" class="input input-bordered flex-1 input-sm" />
              <button class="btn btn-ghost btn-sm text-error" @click="removeFeature(i)">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
            <button class="btn btn-ghost btn-xs" @click="addFeature">
              <Plus class="w-3.5 h-3.5" /> Feature hinzufuegen
            </button>
          </div>
        </div>
      </div>

      <!-- Tab: Filter-Steps -->
      <div v-show="activeTab === 'filters'" class="space-y-4">
        <p class="text-sm text-base-content/60">
          Filter-Steps definieren, welche Attribute auf der Vorschaltseite als Auswahl angezeigt werden.
          Nach der Auswahl wird der Benutzer zur Katalogvorlage mit vorgegebenen Filtern weitergeleitet.
        </p>

        <div v-for="(step, i) in form.filter_steps" :key="i" class="p-4 border border-base-300 rounded-lg space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold">Step {{ i + 1 }}</span>
            <button class="btn btn-ghost btn-xs text-error" @click="removeFilterStep(i)">
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold">Key</label>
              <input v-model="step.key" class="input input-bordered w-full input-xs" placeholder="z.B. country" />
            </div>
            <div>
              <label class="text-xs font-semibold">Label</label>
              <input v-model="step.label" class="input input-bordered w-full input-xs" placeholder="z.B. Land waehlen" />
            </div>
            <div>
              <label class="text-xs font-semibold">Attribut</label>
              <select v-model="step.attribute_id" class="select select-bordered w-full select-xs">
                <option value="">— Attribut waehlen —</option>
                <option v-for="attr in attributes" :key="attr.id" :value="attr.id">
                  {{ attr.name_de || attr.technical_name }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold">Widget-Typ</label>
              <select v-model="step.widget" class="select select-bordered w-full select-xs">
                <option value="country-select">Laender-Karten</option>
                <option value="language-select">Sprach-Badges</option>
                <option value="filter-dropdown">Dropdown</option>
                <option value="filter-cards">Karten</option>
              </select>
            </div>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="step.derive_locale" class="checkbox checkbox-xs" />
            <span class="text-xs">Sprache aus Auswahl ableiten</span>
          </label>
        </div>

        <button class="btn btn-sm btn-outline" @click="addFilterStep">
          <Plus class="w-4 h-4" />
          Filter-Step hinzufuegen
        </button>
      </div>

      <!-- Tab: HTML-Template -->
      <div v-show="activeTab === 'template'">
        <p class="text-sm text-base-content/60 mb-3">
          HTML-Template mit <code>data-portal="..."</code> Widgets.
          Verfuegbare Widgets: branding, country-select, language-select, filter-dropdown, filter-cards, submit-button
        </p>
        <textarea
          v-model="form.html_template"
          class="textarea textarea-bordered w-full font-mono text-xs"
          rows="25"
          spellcheck="false"
        ></textarea>
      </div>

      <!-- Tab: CSS -->
      <div v-show="activeTab === 'css'" class="space-y-4">
        <div>
          <label class="label text-sm font-semibold">Custom CSS</label>
          <textarea
            v-model="form.custom_css"
            class="textarea textarea-bordered w-full font-mono text-xs"
            rows="15"
            spellcheck="false"
            placeholder=":root { --pe-primary: #00AEEF; }"
          ></textarea>
        </div>
      </div>
    </template>
  </div>
</template>
