<script setup>
import { ref, computed, onMounted } from 'vue'
import contentApi from '@/api/content'
import { Plus, Trash2, GripVertical, Save, LayoutTemplate, X } from 'lucide-vue-next'

const types = ref([])
const sectionTypes = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const errors = ref({})

const LAYOUTS = ['', 'landing', 'article', 'legal', 'overview']

async function load() {
  loading.value = true
  try {
    const [{ data: t }, { data: s }] = await Promise.all([
      contentApi.listTypes({ perPage: 200 }),
      contentApi.listSectionTypes({ perPage: 200 }),
    ])
    types.value = t.data ?? t
    sectionTypes.value = s.data ?? s
  } finally {
    loading.value = false
  }
}

function selectType(t) {
  errors.value = {}
  current.value = {
    ...structuredClone(t),
    allowed_section_types: [...(t.allowed_section_types || [])],
    default_sections: structuredClone(t.default_sections || []),
  }
}

function newType() {
  errors.value = {}
  current.value = {
    id: null, technical_name: '', name_de: '', name_en: '', icon: '', color: '#3B82F6',
    layout_hint: '', is_active: true, sort_order: 0,
    allowed_section_types: [], default_sections: [],
  }
}

function toggleAllowed(tn) {
  const set = new Set(current.value.allowed_section_types)
  set.has(tn) ? set.delete(tn) : set.add(tn)
  current.value.allowed_section_types = [...set]
  // aus default_sections entfernen, wenn nicht mehr erlaubt
  if (!set.has(tn)) {
    current.value.default_sections = current.value.default_sections.filter((d) => d.section_type !== tn)
  }
}

function addDefault(tn) {
  if (!tn) return
  current.value.default_sections.push({ section_type: tn })
}
function removeDefault(idx) {
  current.value.default_sections.splice(idx, 1)
}

const dragIdx = ref(null)
function onDragStart(i) { dragIdx.value = i }
function onDrop(i) {
  const from = dragIdx.value; dragIdx.value = null
  if (from === null || from === i) return
  const d = current.value.default_sections
  const [m] = d.splice(from, 1); d.splice(i, 0, m)
}

function stName(tn) {
  const s = sectionTypes.value.find((x) => x.technical_name === tn)
  return s ? s.name_de : tn
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    technical_name: current.value.technical_name,
    name_de: current.value.name_de,
    name_en: current.value.name_en || null,
    icon: current.value.icon || null,
    color: current.value.color || null,
    layout_hint: current.value.layout_hint || null,
    is_active: current.value.is_active,
    sort_order: current.value.sort_order,
    allowed_section_types: current.value.allowed_section_types,
    default_sections: current.value.default_sections,
  }
  try {
    if (current.value.id) {
      const { data } = await contentApi.updateType(current.value.id, payload)
      Object.assign(current.value, data.data ?? data)
    } else {
      const { data } = await contentApi.createType(payload)
      current.value = { ...current.value, ...(data.data ?? data) }
    }
    await load()
  } catch (e) {
    if (e.response?.status === 422) {
      const se = e.response.data.errors || {}
      for (const [k, v] of Object.entries(se)) errors.value[k] = Array.isArray(v) ? v[0] : v
    } else {
      errors.value._general = e.response?.data?.message || 'Speichern fehlgeschlagen.'
    }
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!current.value?.id) return
  if (!confirm(`Seitentyp "${current.value.name_de}" löschen?`)) return
  try {
    await contentApi.deleteType(current.value.id)
    current.value = null
    await load()
  } catch (e) {
    errors.value._general = e.response?.status === 409
      ? 'Seitentyp wird noch von Content-Seiten verwendet.'
      : 'Löschen fehlgeschlagen.'
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-4" data-testid="content-types">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <LayoutTemplate class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Seitentypen</h2>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="newType"><Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neuer Seitentyp</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Liste -->
      <div class="md:col-span-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-1 self-start">
        <div v-if="loading" class="space-y-1 p-1"><div v-for="i in 5" :key="i" class="pim-skeleton h-9 rounded" /></div>
        <button v-for="t in types" :key="t.id"
          class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-left transition-colors"
          :class="current?.id === t.id ? 'bg-[var(--color-bg)]' : 'hover:bg-[var(--color-bg)]'"
          @click="selectType(t)">
          <span class="w-2 h-2 rounded-full shrink-0" :style="`background:${t.color || 'var(--color-border)'}`" />
          <span class="min-w-0">
            <span class="block text-xs font-medium truncate">{{ t.name_de }}</span>
            <span class="block text-[10px] text-[var(--color-text-tertiary)] font-mono truncate">{{ t.technical_name }}</span>
          </span>
          <span v-if="!t.is_active" class="ml-auto text-[9px] text-[var(--color-text-tertiary)]">inaktiv</span>
        </button>
        <p v-if="!loading && !types.length" class="text-xs text-[var(--color-text-tertiary)] p-3">Noch keine Seitentypen.</p>
      </div>

      <!-- Editor -->
      <div v-if="current" class="md:col-span-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
        <p v-if="errors._general" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">{{ errors._general }}</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name *</label>
            <input v-model="current.technical_name" :disabled="!!current.id" class="pim-input text-xs w-full" placeholder="z. B. campaign-page" />
            <p v-if="errors.technical_name" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.technical_name }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (DE) *</label>
            <input v-model="current.name_de" class="pim-input text-xs w-full" />
            <p v-if="errors.name_de" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.name_de }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (EN)</label>
            <input v-model="current.name_en" class="pim-input text-xs w-full" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Layout-Hinweis</label>
            <select v-model="current.layout_hint" class="pim-input text-xs w-full">
              <option v-for="l in LAYOUTS" :key="l" :value="l">{{ l || '—' }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Icon (Lucide)</label>
            <input v-model="current.icon" class="pim-input text-xs w-full" placeholder="z. B. Megaphone" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Farbe</label>
            <input v-model="current.color" type="color" class="h-8 w-16 rounded border border-[var(--color-border)]" />
          </div>
        </div>

        <!-- Erlaubte Sektionstypen -->
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Erlaubte Sektionstypen</label>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="s in sectionTypes" :key="s.id" type="button"
              class="px-2 py-1 rounded-full text-[11px] border transition-colors"
              :class="current.allowed_section_types.includes(s.technical_name)
                ? 'bg-[var(--color-accent)] text-white border-transparent'
                : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
              @click="toggleAllowed(s.technical_name)">{{ s.name_de }}</button>
          </div>
        </div>

        <!-- Standard-Sektionen -->
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Standard-Sektionen (Vorbelegung beim Anlegen)</label>
          <div class="space-y-1.5">
            <div v-for="(d, idx) in current.default_sections" :key="idx" class="flex items-center gap-2"
              draggable="true" @dragstart="onDragStart(idx)" @dragover.prevent @drop="onDrop(idx)">
              <GripVertical class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] cursor-grab shrink-0" :stroke-width="2" />
              <span class="flex-1 text-xs px-2 py-1 rounded bg-[var(--color-bg)]">{{ stName(d.section_type) }}</span>
              <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="removeDefault(idx)"><X class="w-3.5 h-3.5" :stroke-width="2.5" /></button>
            </div>
            <select class="pim-input text-xs w-full" @change="addDefault($event.target.value); $event.target.value=''">
              <option value="">+ Standard-Sektion hinzufügen…</option>
              <option v-for="tn in current.allowed_section_types" :key="tn" :value="tn">{{ stName(tn) }}</option>
            </select>
          </div>
        </div>

        <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
          <input type="checkbox" v-model="current.is_active" class="pim-checkbox" /> Aktiv
        </label>

        <div class="flex items-center gap-2 pt-3 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="save"><Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ saving ? 'Speichern…' : 'Speichern' }}</button>
          <button v-if="current.id" class="pim-btn pim-btn-secondary text-xs" @click="remove"><Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> Löschen</button>
        </div>
      </div>

      <div v-else class="md:col-span-2 flex items-center justify-center text-xs text-[var(--color-text-tertiary)] border border-dashed border-[var(--color-border)] rounded-lg p-8">
        Wähle links einen Seitentyp oder lege einen neuen an.
      </div>
    </div>
  </div>
</template>
