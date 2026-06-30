<script setup>
import { ref, computed, onMounted } from 'vue'
import { ecommerceCartTypes } from '@/api/ecommerce'
import { ShoppingCart, Plus, Trash2, Save, X, ChevronDown, ChevronRight } from 'lucide-vue-next'

function clone(v) { return v == null ? v : JSON.parse(JSON.stringify(v)) }

const types = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const errors = ref({})

const TYPE_OPTIONS = [
  { value: 'wishlist', label: 'Merkliste', description: 'Einfache Produktliste ohne Menge und Preis' },
  { value: 'pdf_list', label: 'PDF-Liste', description: 'Merkliste mit PDF-Export-Funktion' },
  { value: 'ecommerce', label: 'Warenkorb', description: 'Mit Mengenangabe, Preisanzeige und Bestellfunktion' },
]

const ADAPTER_OPTIONS = [
  { value: 'none', label: 'Kein Adapter' },
  { value: 'email', label: 'E-Mail' },
  { value: 'rest_api', label: 'REST API (Webhook)' },
]

async function load() {
  loading.value = true
  try {
    const res = await ecommerceCartTypes.list()
    types.value = res.data.data ?? []
  } finally {
    loading.value = false
  }
}

function select(t) {
  errors.value = {}
  current.value = clone(t)
  if (!current.value.adapter_config) {
    current.value.adapter_config = {}
  }
}

function createNew() {
  errors.value = {}
  current.value = {
    id: null,
    technical_name: '',
    name_de: '',
    name_en: '',
    type: 'ecommerce',
    price_type_id: null,
    show_prices: false,
    show_quantity: true,
    show_total: false,
    allow_checkout: false,
    adapter_type: 'none',
    adapter_config: {},
    is_active: true,
    sort_order: 0,
  }
}

function discard() {
  current.value = null
  errors.value = {}
}

async function save() {
  if (!current.value) return
  saving.value = true
  errors.value = {}
  try {
    if (current.value.id) {
      const res = await ecommerceCartTypes.update(current.value.id, current.value)
      const idx = types.value.findIndex((t) => t.id === current.value.id)
      if (idx !== -1) types.value[idx] = res.data.data
      current.value = clone(res.data.data)
    } else {
      const res = await ecommerceCartTypes.create(current.value)
      types.value.push(res.data.data)
      current.value = clone(res.data.data)
    }
  } catch (e) {
    errors.value = e.response?.data?.errors ?? {}
    if (e.response?.data?.message) {
      errors.value._general = e.response.data.message
    }
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!current.value?.id || !confirm('Warenkorbtyp wirklich löschen?')) return
  deleting.value = true
  try {
    await ecommerceCartTypes.delete(current.value.id)
    types.value = types.value.filter((t) => t.id !== current.value.id)
    current.value = null
  } catch (e) {
    alert(e.response?.data?.message ?? 'Löschen fehlgeschlagen.')
  } finally {
    deleting.value = false
  }
}

const isEcommerce = computed(() => current.value?.type === 'ecommerce')
const hasAdapter = computed(() => current.value?.adapter_type !== 'none')

onMounted(load)
</script>

<template>
  <div class="flex h-full overflow-hidden">
    <!-- Linke Liste -->
    <aside class="w-72 shrink-0 border-r border-base-300 flex flex-col bg-base-100">
      <div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
        <span class="font-semibold text-sm">Warenkorbarten</span>
        <button class="btn btn-xs btn-primary" @click="createNew">
          <Plus class="w-3.5 h-3.5" />
          Neu
        </button>
      </div>

      <div v-if="loading" class="p-4 text-center text-sm opacity-50">Lädt…</div>
      <div v-else-if="!types.length" class="p-4 text-center text-sm opacity-50">Noch keine Warenkorbarten definiert.</div>

      <ul class="flex-1 overflow-y-auto">
        <li
          v-for="t in types"
          :key="t.id"
          class="flex items-center gap-2 px-4 py-2.5 cursor-pointer hover:bg-base-200 border-b border-base-200 text-sm"
          :class="{ 'bg-primary/10 font-medium': current?.id === t.id }"
          @click="select(t)"
        >
          <ShoppingCart class="w-4 h-4 shrink-0 opacity-60" />
          <span class="truncate">{{ t.name_de }}</span>
          <span class="ml-auto badge badge-xs" :class="{
            'badge-ghost': t.type === 'wishlist',
            'badge-info': t.type === 'pdf_list',
            'badge-success': t.type === 'ecommerce',
          }">{{ t.type }}</span>
        </li>
      </ul>
    </aside>

    <!-- Rechte Detailansicht -->
    <main class="flex-1 overflow-y-auto p-6">
      <div v-if="!current" class="flex flex-col items-center justify-center h-full opacity-40 gap-2">
        <ShoppingCart class="w-12 h-12" />
        <p class="text-sm">Warenkorbtyp auswählen oder neu erstellen</p>
      </div>

      <template v-else>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold">
            {{ current.id ? current.name_de : 'Neuer Warenkorbtyp' }}
          </h2>
          <div class="flex gap-2">
            <button class="btn btn-sm btn-ghost" @click="discard">
              <X class="w-4 h-4" /> Abbrechen
            </button>
            <button v-if="current.id" class="btn btn-sm btn-error btn-outline" :disabled="deleting" @click="remove">
              <Trash2 class="w-4 h-4" />
            </button>
            <button class="btn btn-sm btn-primary" :disabled="saving" @click="save">
              <Save class="w-4 h-4" />
              {{ saving ? 'Speichert…' : 'Speichern' }}
            </button>
          </div>
        </div>

        <div v-if="errors._general" class="alert alert-error mb-4 text-sm">{{ errors._general }}</div>

        <div class="grid grid-cols-2 gap-4 max-w-2xl">
          <!-- Basisfelder -->
          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Bezeichnung (DE) *</label>
            <input v-model="current.name_de" class="input input-bordered input-sm" :class="{ 'input-error': errors.name_de }" />
            <span v-if="errors.name_de" class="text-error text-xs mt-1">{{ errors.name_de[0] }}</span>
          </div>

          <div class="form-control">
            <label class="label label-text text-xs">Bezeichnung (EN)</label>
            <input v-model="current.name_en" class="input input-bordered input-sm" />
          </div>

          <div class="form-control">
            <label class="label label-text text-xs">Technical Name *</label>
            <input v-model="current.technical_name" class="input input-bordered input-sm font-mono" :class="{ 'input-error': errors.technical_name }" :disabled="!!current.id" />
            <span v-if="errors.technical_name" class="text-error text-xs mt-1">{{ errors.technical_name[0] }}</span>
          </div>

          <!-- Typ -->
          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Typ *</label>
            <div class="flex gap-3 flex-wrap">
              <label v-for="opt in TYPE_OPTIONS" :key="opt.value" class="flex items-start gap-2 cursor-pointer border border-base-300 rounded-lg p-3 flex-1 min-w-40 hover:border-primary"
                :class="{ 'border-primary bg-primary/5': current.type === opt.value }">
                <input type="radio" v-model="current.type" :value="opt.value" class="radio radio-primary radio-sm mt-0.5" />
                <div>
                  <div class="text-sm font-medium">{{ opt.label }}</div>
                  <div class="text-xs opacity-60">{{ opt.description }}</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Anzeigeoptionen -->
          <div class="col-span-2">
            <label class="label label-text text-xs">Anzeigeoptionen</label>
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="current.show_prices" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm">Preise anzeigen</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="current.show_quantity" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm">Mengenfeld</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="current.show_total" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm">Gesamtsumme</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="current.allow_checkout" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm">Checkout / Bestellen erlauben</span>
              </label>
            </div>
          </div>

          <!-- Adapter -->
          <div class="col-span-2 divider text-xs opacity-50">Übermittlung</div>

          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Adapter</label>
            <select v-model="current.adapter_type" class="select select-bordered select-sm max-w-xs">
              <option v-for="opt in ADAPTER_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <!-- Email-Adapter-Konfiguration -->
          <template v-if="current.adapter_type === 'email'">
            <div class="form-control col-span-2">
              <label class="label label-text text-xs">Empfänger (E-Mail)</label>
              <input v-model="current.adapter_config.to" class="input input-bordered input-sm" type="email" placeholder="bestellung@firma.de" />
            </div>
            <div class="form-control col-span-2">
              <label class="label label-text text-xs">Betreff</label>
              <input v-model="current.adapter_config.subject_de" class="input input-bordered input-sm" placeholder="Neue Bestellung" />
            </div>
          </template>

          <!-- REST-Adapter-Konfiguration -->
          <template v-if="current.adapter_type === 'rest_api'">
            <div class="form-control col-span-2">
              <label class="label label-text text-xs">Webhook-URL</label>
              <input v-model="current.adapter_config.url" class="input input-bordered input-sm font-mono" placeholder="https://api.firma.de/orders" />
            </div>
            <div class="form-control">
              <label class="label label-text text-xs">HTTP-Methode</label>
              <select v-model="current.adapter_config.method" class="select select-bordered select-sm">
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
              </select>
            </div>
          </template>

          <!-- Sonstige -->
          <div class="col-span-2 divider text-xs opacity-50">Allgemein</div>

          <div class="form-control">
            <label class="label label-text text-xs">Sortierung</label>
            <input v-model.number="current.sort_order" type="number" class="input input-bordered input-sm w-24" />
          </div>

          <div class="form-control flex-row items-center gap-2 pt-6">
            <input type="checkbox" v-model="current.is_active" class="toggle toggle-sm toggle-primary" />
            <span class="text-sm">Aktiv</span>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
