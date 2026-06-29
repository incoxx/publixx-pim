<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'
import widgetsApi from '@/api/productWidgets'
import attributesApi from '@/api/attributes'
import mediaUsageTypesApi from '@/api/mediaUsageTypes'
import { Plus, Trash2, GripVertical, Save, LayoutGrid } from 'lucide-vue-next'

// Reaktivitätssicherer Deep-Clone (structuredClone wirft auf Vue-Proxies).
function clone(v) { return v == null ? v : JSON.parse(JSON.stringify(v)) }

const widgets = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const errors = ref({})

// Referenzdaten für die Datenquellen-Auswahl
const attributes = ref([])
const priceTypes = ref([])
const mediaUsageTypes = ref([])

// "Anzeige als" — wo ein Element in der Produktkarte erscheint
const ROLES = [
  { value: 'image', label: 'Bild' },
  { value: 'title', label: 'Titel' },
  { value: 'subtitle', label: 'Untertitel' },
  { value: 'price', label: 'Preis' },
  { value: 'badge', label: 'Badge' },
  { value: 'rating', label: 'Bewertung' },
  { value: 'cta', label: 'Button' },
]
const LAYOUTS = [
  { value: 'card', label: 'Karte (Bild oben, Text darunter)' },
  { value: 'hero', label: 'Hero (groß, Bild + Text nebeneinander)' },
  { value: 'list', label: 'Listenzeile (kompakt)' },
]
const RATIOS = ['1/1', '4/3', '16/9', '3/4']

// Gruppierte Datenquellen (Produkt / Bild / Preis / Attribut)
const sourceGroups = computed(() => [
  { label: 'Produkt', items: [
    { source: 'product:name', label: 'Name', type: 'text' },
    { source: 'product:sku', label: 'SKU', type: 'text' },
    { source: 'product:ean', label: 'EAN', type: 'text' },
  ] },
  { label: 'Bild', items: mediaUsageTypes.value.map((m) => ({ source: 'media:' + m.technical_name, label: m.name_de || m.technical_name, type: 'media_url' })) },
  { label: 'Preis', items: priceTypes.value.map((p) => ({ source: 'prices:' + p.technical_name, label: p.name_de || p.technical_name, type: 'price' })) },
  { label: 'Attribut', items: attributes.value.map((a) => ({ source: 'attribute:' + a.technical_name, label: a.name_de || a.technical_name, type: (a.data_type === 'Number' || a.data_type === 'Float') ? 'unit_value' : 'text' })) },
])
const sourceMeta = computed(() => {
  const m = {}
  for (const g of sourceGroups.value) for (const it of g.items) m[it.source] = it
  return m
})

async function load() {
  loading.value = true
  try {
    const { data } = await widgetsApi.list({ perPage: 200 })
    widgets.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

function withDefaults(config) {
  return { fields: [], show_sku: false, layout: 'card', image_ratio: '4/3', cta: { enabled: false, label: '' }, ...clone(config || {}) }
}

function selectWidget(w) {
  errors.value = {}
  current.value = { ...clone(w), config: withDefaults(w.config) }
}

function newWidget() {
  errors.value = {}
  current.value = {
    id: null, technical_name: '', name_de: '', description: '',
    is_system: false, is_active: true, config: withDefaults(null),
  }
}

function addField() {
  current.value.config.fields.push({ role: 'title', source: '', type: 'text' })
}
function removeField(idx) { current.value.config.fields.splice(idx, 1) }

function onSourcePick(f, source) {
  f.source = source
  f.type = sourceMeta.value[source]?.type || 'text'
}

// Drag-Sortierung
const dragIdx = ref(null)
function onDragStart(i) { dragIdx.value = i }
function onDrop(i) {
  const from = dragIdx.value
  dragIdx.value = null
  if (from === null || from === i) return
  const f = current.value.config.fields
  const [moved] = f.splice(from, 1)
  f.splice(i, 0, moved)
}

async function save() {
  errors.value = {}
  const cleanFields = (current.value.config.fields || []).filter((f) => f.source && f.source.trim())
  if (!cleanFields.length) {
    errors.value._general = 'Mindestens eine Datenquelle wählen (z. B. Name, Bild, Preis).'
    return
  }
  current.value.config.fields = cleanFields

  saving.value = true
  const payload = {
    technical_name: current.value.technical_name,
    name_de: current.value.name_de,
    description: current.value.description,
    is_active: current.value.is_active,
    config: current.value.config,
  }
  try {
    if (current.value.id) {
      const { data } = await widgetsApi.update(current.value.id, payload)
      Object.assign(current.value, data.data ?? data)
    } else {
      const { data } = await widgetsApi.create(payload)
      current.value = { ...current.value, ...(data.data ?? data) }
    }
    // Defensive: config immer vollständig halten (kein Render-Crash bei Teil-config)
    current.value.config = withDefaults(current.value.config)
    await load()
  } catch (e) {
    if (e.response?.status === 422) {
      const se = e.response.data.errors || {}
      for (const [k, v] of Object.entries(se)) errors.value[k] = Array.isArray(v) ? v[0] : v
      if (Object.keys(se).some((k) => k.startsWith('config.fields'))) {
        errors.value._general = 'Bitte für jedes Feld eine Datenquelle und Anzeige-Position wählen.'
      }
    } else {
      errors.value._general = e.response?.data?.message || 'Speichern fehlgeschlagen.'
    }
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!current.value?.id || current.value.is_system) return
  if (!confirm(`Widget "${current.value.name_de}" löschen?`)) return
  await widgetsApi.delete(current.value.id)
  current.value = null
  await load()
}

onMounted(async () => {
  load()
  try {
    const [a, m, p] = await Promise.all([
      attributesApi.list({ perPage: 500 }),
      mediaUsageTypesApi.list(),
      client.get('/price-types', { params: { per_page: 200 } }),
    ])
    attributes.value = a.data.data ?? a.data
    mediaUsageTypes.value = m.data.data ?? m.data
    priceTypes.value = p.data.data ?? p.data
  } catch { /* Auswahllisten optional */ }
})
</script>

<template>
  <div class="space-y-4" data-testid="product-widgets">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <LayoutGrid class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Produkt-Widgets</h2>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="newWidget">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neues Widget
      </button>
    </div>
    <p class="text-[11px] text-[var(--color-text-tertiary)] -mt-2">
      Wähle, welche Produktdaten gezeigt werden (Name, Bild, Preis, Attribute) und wo sie erscheinen. Getrennt von der Katalog-Vorschau.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Liste -->
      <div class="md:col-span-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-1 self-start">
        <div v-if="loading" class="space-y-1 p-1">
          <div v-for="i in 4" :key="i" class="pim-skeleton h-9 rounded" />
        </div>
        <button
          v-for="w in widgets" :key="w.id"
          class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-left transition-colors"
          :class="current?.id === w.id ? 'bg-[var(--color-bg)]' : 'hover:bg-[var(--color-bg)]'"
          @click="selectWidget(w)"
        >
          <span class="min-w-0">
            <span class="block text-xs font-medium truncate">{{ w.name_de }}</span>
            <span class="block text-[10px] text-[var(--color-text-tertiary)] font-mono truncate">{{ w.technical_name }}</span>
          </span>
          <span v-if="w.is_system" class="text-[9px] px-1.5 py-0.5 rounded-full bg-[var(--color-bg)] text-[var(--color-text-tertiary)] shrink-0">System</span>
        </button>
        <p v-if="!loading && !widgets.length" class="text-xs text-[var(--color-text-tertiary)] p-3">Noch keine Widgets.</p>
      </div>

      <!-- Editor -->
      <div v-if="current" class="md:col-span-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
        <p v-if="errors._general" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ errors._general }}</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name *</label>
            <input v-model="current.technical_name" :disabled="current.is_system || !!current.id" class="pim-input text-xs w-full" placeholder="z. B. card" />
            <p v-if="errors.technical_name" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.technical_name }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name *</label>
            <input v-model="current.name_de" class="pim-input text-xs w-full" />
            <p v-if="errors.name_de" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.name_de }}</p>
          </div>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beschreibung</label>
          <input v-model="current.description" class="pim-input text-xs w-full" />
        </div>

        <!-- Layout-Vorlage -->
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Layout-Vorlage</label>
          <select v-model="current.config.layout" class="pim-input text-xs w-full">
            <option v-for="l in LAYOUTS" :key="l.value" :value="l.value">{{ l.label }}</option>
          </select>
        </div>

        <!-- Datenpunkte: Anzeige-Position → Datenquelle -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="text-[12px] font-medium text-[var(--color-text-secondary)]">Angezeigte Daten</label>
            <button class="pim-btn pim-btn-secondary text-[11px]" @click="addField"><Plus class="w-3 h-3" :stroke-width="2" /> Datenpunkt</button>
          </div>
          <div class="grid items-center gap-1.5 mb-1 px-5 text-[10px] text-[var(--color-text-tertiary)] uppercase tracking-wide" style="grid-template-columns: 8rem minmax(0,1fr) 1.75rem;">
            <span>Anzeige als</span><span>Datenquelle</span><span></span>
          </div>
          <div class="space-y-1.5">
            <div
              v-for="(f, idx) in current.config.fields" :key="idx"
              class="grid items-center gap-1.5"
              style="grid-template-columns: 1.25rem 8rem minmax(0,1fr) 1.75rem;"
              draggable="true" @dragstart="onDragStart(idx)" @dragover.prevent @drop="onDrop(idx)"
            >
              <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] cursor-grab" :stroke-width="2" />
              <select v-model="f.role" class="pim-input text-xs min-w-0">
                <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <select :value="f.source" class="pim-input text-xs min-w-0 w-full" @change="onSourcePick(f, $event.target.value)">
                <option value="">— Datenquelle wählen —</option>
                <optgroup v-for="g in sourceGroups" :key="g.label" :label="g.label">
                  <option v-for="it in g.items" :key="it.source" :value="it.source">{{ it.label }}</option>
                </optgroup>
              </select>
              <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeField(idx)">
                <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
            <p v-if="!current.config.fields.length" class="text-xs text-[var(--color-text-tertiary)]">Noch keine Daten. „Datenpunkt" hinzufügen.</p>
          </div>
        </div>

        <!-- Optionen -->
        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-[var(--color-border)]">
          <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
            <input type="checkbox" v-model="current.config.show_sku" class="pim-checkbox" /> SKU anzeigen
          </label>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Bildformat</label>
            <select v-model="current.config.image_ratio" class="pim-input text-xs w-full">
              <option v-for="r in RATIOS" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
          <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
            <input type="checkbox" v-model="current.config.cta.enabled" class="pim-checkbox" /> Button (CTA)
          </label>
          <div v-if="current.config.cta.enabled">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Button-Text</label>
            <input v-model="current.config.cta.label" class="pim-input text-xs w-full" placeholder="Details" />
          </div>
        </div>

        <div class="flex items-center gap-2 pt-3 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="save">
            <Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ saving ? 'Speichern…' : 'Speichern' }}
          </button>
          <button v-if="current.id && !current.is_system" class="pim-btn pim-btn-secondary text-xs" @click="remove">
            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> Löschen
          </button>
        </div>
      </div>

      <div v-else class="md:col-span-2 flex items-center justify-center text-xs text-[var(--color-text-tertiary)] border border-dashed border-[var(--color-border)] rounded-lg p-8">
        Wähle links ein Widget oder lege ein neues an.
      </div>
    </div>
  </div>
</template>
