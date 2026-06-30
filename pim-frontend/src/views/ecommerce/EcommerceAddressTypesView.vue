<script setup>
import { ref, onMounted } from 'vue'
import { ecommerceAddressTypes } from '@/api/ecommerce'
import { MapPin, Plus, Trash2, Save, X, GripVertical } from 'lucide-vue-next'

function clone(v) { return v == null ? v : JSON.parse(JSON.stringify(v)) }

const types = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const errors = ref({})

const ROLE_OPTIONS = [
  { value: 'billing', label: 'Rechnungsadresse' },
  { value: 'shipping', label: 'Lieferadresse' },
  { value: 'general', label: 'Allgemeine Adresse' },
]

const FIELD_TYPES = ['text', 'email', 'phone', 'textarea', 'select', 'checkbox']

async function load() {
  loading.value = true
  try {
    const res = await ecommerceAddressTypes.list()
    types.value = res.data.data ?? []
  } finally {
    loading.value = false
  }
}

function select(t) {
  errors.value = {}
  current.value = clone(t)
  if (!current.value.field_schema) current.value.field_schema = []
}

function createNew() {
  errors.value = {}
  current.value = {
    id: null, technical_name: '', name_de: '', name_en: '', role: 'billing',
    field_schema: defaultFields(), is_active: true, sort_order: 0,
  }
}

function defaultFields() {
  return [
    { key: 'company', label_de: 'Firma', type: 'text', required: false, sort_order: 1 },
    { key: 'first_name', label_de: 'Vorname', type: 'text', required: true, sort_order: 2 },
    { key: 'last_name', label_de: 'Nachname', type: 'text', required: true, sort_order: 3 },
    { key: 'street', label_de: 'Straße', type: 'text', required: true, sort_order: 4 },
    { key: 'zip', label_de: 'PLZ', type: 'text', required: true, sort_order: 5 },
    { key: 'city', label_de: 'Ort', type: 'text', required: true, sort_order: 6 },
    { key: 'email', label_de: 'E-Mail', type: 'email', required: true, sort_order: 7 },
    { key: 'phone', label_de: 'Telefon', type: 'phone', required: false, sort_order: 8 },
  ]
}

function addField() {
  const nextSort = Math.max(0, ...(current.value.field_schema ?? []).map(f => f.sort_order)) + 1
  current.value.field_schema.push({ key: '', label_de: '', type: 'text', required: false, sort_order: nextSort })
}

function removeField(idx) {
  current.value.field_schema.splice(idx, 1)
}

function discard() { current.value = null; errors.value = {} }

async function save() {
  if (!current.value) return
  saving.value = true; errors.value = {}
  try {
    if (current.value.id) {
      const res = await ecommerceAddressTypes.update(current.value.id, current.value)
      const idx = types.value.findIndex(t => t.id === current.value.id)
      if (idx !== -1) types.value[idx] = res.data.data
      current.value = clone(res.data.data)
    } else {
      const res = await ecommerceAddressTypes.create(current.value)
      types.value.push(res.data.data)
      current.value = clone(res.data.data)
    }
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!current.value?.id || !confirm('Adressart wirklich löschen?')) return
  try {
    await ecommerceAddressTypes.delete(current.value.id)
    types.value = types.value.filter(t => t.id !== current.value.id)
    current.value = null
  } catch (e) {
    alert(e.response?.data?.message ?? 'Löschen fehlgeschlagen.')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex h-full overflow-hidden">
    <aside class="w-64 shrink-0 border-r border-base-300 flex flex-col bg-base-100">
      <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
        <span class="font-semibold text-sm">Adressarten</span>
        <button class="btn btn-xs btn-primary" @click="createNew"><Plus class="w-3.5 h-3.5" /> Neu</button>
      </div>
      <div v-if="loading" class="p-4 text-sm text-center opacity-50">Lädt…</div>
      <ul class="flex-1 overflow-y-auto">
        <li v-for="t in types" :key="t.id"
          class="flex items-center gap-2 px-4 py-2.5 cursor-pointer hover:bg-base-200 border-b border-base-200 text-sm"
          :class="{ 'bg-primary/10 font-medium': current?.id === t.id }"
          @click="select(t)">
          <MapPin class="w-4 h-4 shrink-0 opacity-60" />
          <span class="truncate">{{ t.name_de }}</span>
          <span class="ml-auto badge badge-xs badge-ghost">{{ t.role }}</span>
        </li>
      </ul>
    </aside>

    <main class="flex-1 overflow-y-auto p-6">
      <div v-if="!current" class="flex flex-col items-center justify-center h-full opacity-40 gap-2">
        <MapPin class="w-12 h-12" />
        <p class="text-sm">Adressart auswählen oder neu erstellen</p>
      </div>
      <template v-else>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold">{{ current.id ? current.name_de : 'Neue Adressart' }}</h2>
          <div class="flex gap-2">
            <button class="btn btn-sm btn-ghost" @click="discard"><X class="w-4 h-4" /></button>
            <button v-if="current.id" class="btn btn-sm btn-error btn-outline" @click="remove"><Trash2 class="w-4 h-4" /></button>
            <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
              <Save class="w-4 h-4" /> {{ saving ? 'Speichert…' : 'Speichern' }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 max-w-2xl">
          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Bezeichnung (DE) *</label>
            <input v-model="current.name_de" class="input input-bordered input-sm" />
          </div>
          <div class="form-control">
            <label class="label label-text text-xs">Technical Name *</label>
            <input v-model="current.technical_name" class="input input-bordered input-sm font-mono" :disabled="!!current.id" />
          </div>
          <div class="form-control">
            <label class="label label-text text-xs">Rolle</label>
            <select v-model="current.role" class="select select-bordered select-sm">
              <option v-for="opt in ROLE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <!-- Feldeditor -->
          <div class="col-span-2">
            <div class="flex items-center justify-between mb-2">
              <label class="label-text text-xs font-medium">Felder</label>
              <button class="btn btn-xs btn-ghost" @click="addField"><Plus class="w-3 h-3" /> Feld</button>
            </div>
            <div class="space-y-2">
              <div v-for="(field, idx) in current.field_schema" :key="idx"
                class="flex items-center gap-2 bg-base-200 rounded-lg px-3 py-2">
                <GripVertical class="w-4 h-4 opacity-30 shrink-0 cursor-grab" />
                <input v-model="field.key" class="input input-xs input-bordered w-28 font-mono" placeholder="key" />
                <input v-model="field.label_de" class="input input-xs input-bordered flex-1" placeholder="Label (DE)" />
                <select v-model="field.type" class="select select-xs select-bordered w-28">
                  <option v-for="ft in FIELD_TYPES" :key="ft" :value="ft">{{ ft }}</option>
                </select>
                <label class="flex items-center gap-1 text-xs cursor-pointer">
                  <input type="checkbox" v-model="field.required" class="checkbox checkbox-xs" />
                  Pflicht
                </label>
                <button class="btn btn-xs btn-ghost text-error" @click="removeField(idx)">
                  <Trash2 class="w-3 h-3" />
                </button>
              </div>
            </div>
          </div>

          <div class="form-control flex-row items-center gap-2 pt-2">
            <input type="checkbox" v-model="current.is_active" class="toggle toggle-sm toggle-primary" />
            <span class="text-sm">Aktiv</span>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
