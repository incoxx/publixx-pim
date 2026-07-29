<script setup>
import { ref, onMounted } from 'vue'
import { ecommercePaymentTypes } from '@/api/ecommerce'
import { CreditCard, Plus, Trash2, Save, X, ChevronLeft } from 'lucide-vue-next'

function clone(v) { return v == null ? v : JSON.parse(JSON.stringify(v)) }

const types = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const errors = ref({})

async function load() {
  loading.value = true
  try {
    const res = await ecommercePaymentTypes.list()
    types.value = res.data.data ?? []
  } finally {
    loading.value = false
  }
}

function select(t) { errors.value = {}; current.value = clone(t) }

function createNew() {
  errors.value = {}
  current.value = { id: null, technical_name: '', name_de: '', name_en: '', description_de: '', icon: '', is_active: true, sort_order: 0 }
}

function discard() { current.value = null; errors.value = {} }

async function save() {
  if (!current.value) return
  saving.value = true; errors.value = {}
  try {
    if (current.value.id) {
      const res = await ecommercePaymentTypes.update(current.value.id, current.value)
      const idx = types.value.findIndex(t => t.id === current.value.id)
      if (idx !== -1) types.value[idx] = res.data.data
      current.value = clone(res.data.data)
    } else {
      const res = await ecommercePaymentTypes.create(current.value)
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
  if (!current.value?.id || !confirm('Zahlungsart wirklich löschen?')) return
  try {
    await ecommercePaymentTypes.delete(current.value.id)
    types.value = types.value.filter(t => t.id !== current.value.id)
    current.value = null
  } catch (e) {
    alert(e.response?.data?.message ?? 'Löschen fehlgeschlagen.')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full overflow-hidden">
    <aside
      :class="[
        'w-full lg:w-64 lg:shrink-0 lg:flex-none border-b lg:border-b-0 lg:border-r border-base-300 flex-col bg-base-100 min-h-0',
        current ? 'hidden lg:flex' : 'flex flex-1',
      ]"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
        <span class="font-semibold text-sm">Zahlungsarten</span>
        <button class="btn btn-xs btn-primary text-white" @click="createNew"><Plus class="w-3.5 h-3.5" /> Neu</button>
      </div>
      <div v-if="loading" class="p-4 text-sm text-center opacity-50">Lädt…</div>
      <ul class="flex-1 overflow-y-auto">
        <li v-for="t in types" :key="t.id"
          class="flex items-center gap-2 px-4 py-2.5 cursor-pointer hover:bg-base-200 border-b border-base-200 text-sm"
          :class="{ 'bg-primary/10 font-medium': current?.id === t.id }"
          @click="select(t)">
          <CreditCard class="w-4 h-4 shrink-0 opacity-60" />
          <span class="truncate">{{ t.name_de }}</span>
        </li>
      </ul>
    </aside>

    <main :class="['flex-1 overflow-y-auto p-4 lg:p-6 min-h-0', current ? '' : 'hidden lg:block']">
      <div v-if="!current" class="flex flex-col items-center justify-center h-full opacity-40 gap-2">
        <CreditCard class="w-12 h-12" />
        <p class="text-sm">Zahlungsart auswählen oder neu erstellen</p>
      </div>
      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-6">
          <div class="flex items-center gap-2 min-w-0">
            <button class="btn btn-sm btn-ghost btn-circle lg:hidden shrink-0" title="Zurück" @click="discard">
              <ChevronLeft class="w-4 h-4" />
            </button>
            <h2 class="text-lg font-semibold truncate">{{ current.id ? current.name_de : 'Neue Zahlungsart' }}</h2>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="btn btn-sm btn-ghost" @click="discard"><X class="w-4 h-4" /></button>
            <button v-if="current.id" class="btn btn-sm btn-error btn-outline" @click="remove"><Trash2 class="w-4 h-4" /></button>
            <button class="btn btn-sm btn-primary text-white" :disabled="saving" @click="save">
              <Save class="w-4 h-4" /> {{ saving ? 'Speichert…' : 'Speichern' }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl">
          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Bezeichnung (DE) *</label>
            <input v-model="current.name_de" class="input input-bordered input-sm" :class="{ 'input-error': errors.name_de }" />
          </div>
          <div class="form-control">
            <label class="label label-text text-xs">Bezeichnung (EN)</label>
            <input v-model="current.name_en" class="input input-bordered input-sm" />
          </div>
          <div class="form-control">
            <label class="label label-text text-xs">Technical Name *</label>
            <input v-model="current.technical_name" class="input input-bordered input-sm font-mono" :disabled="!!current.id" />
          </div>
          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Beschreibung</label>
            <textarea v-model="current.description_de" class="textarea textarea-bordered textarea-sm" rows="2"></textarea>
          </div>
          <div class="form-control">
            <label class="label label-text text-xs">Icon (Lucide-Name)</label>
            <input v-model="current.icon" class="input input-bordered input-sm" placeholder="CreditCard" />
          </div>
          <div class="form-control">
            <label class="label label-text text-xs">Sortierung</label>
            <input v-model.number="current.sort_order" type="number" class="input input-bordered input-sm w-24" />
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
