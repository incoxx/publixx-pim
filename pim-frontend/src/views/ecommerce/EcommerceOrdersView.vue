<script setup>
import { ref, computed, onMounted } from 'vue'
import { ecommerceOrders } from '@/api/ecommerce'
import { Package, X, ChevronLeft, ChevronRight, RefreshCw, CheckCircle, XCircle, Clock, Loader } from 'lucide-vue-next'

const orders = ref([])
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const retrying = ref(false)
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'Alle' },
  { value: 'pending', label: 'Ausstehend' },
  { value: 'processing', label: 'In Bearbeitung' },
  { value: 'confirmed', label: 'Bestätigt' },
  { value: 'failed', label: 'Fehlgeschlagen' },
]

const filterStatus = ref('')
const filterSearch = ref('')
const page = ref(1)

const STATUS_LABELS = {
  pending: 'Ausstehend',
  processing: 'In Bearbeitung',
  confirmed: 'Bestätigt',
  failed: 'Fehlgeschlagen',
}
const STATUS_BADGE = {
  pending: 'badge-warning',
  processing: 'badge-info',
  confirmed: 'badge-success',
  failed: 'badge-error',
}

async function load() {
  loading.value = true
  try {
    const res = await ecommerceOrders.list({
      page: page.value,
      status: filterStatus.value || undefined,
      search: filterSearch.value || undefined,
    })
    orders.value = res.data.data ?? []
    meta.value = res.data.meta ?? { current_page: 1, last_page: 1, total: 0 }
  } finally {
    loading.value = false
  }
}

function select(o) { current.value = o }
function close() { current.value = null }

function prevPage() { if (page.value > 1) { page.value--; load() } }
function nextPage() { if (page.value < meta.value.last_page) { page.value++; load() } }

function applyFilter() { page.value = 1; load() }

async function saveStatus() {
  if (!current.value) return
  saving.value = true
  try {
    const res = await ecommerceOrders.update(current.value.id, {
      status: current.value.status,
      internal_notes: current.value.internal_notes,
    })
    const updated = res.data.data
    const idx = orders.value.findIndex(o => o.id === updated.id)
    if (idx !== -1) orders.value[idx] = updated
    current.value = updated
  } finally {
    saving.value = false
  }
}

async function retryAdapter() {
  if (!current.value) return
  retrying.value = true
  try {
    const res = await ecommerceOrders.retry(current.value.id)
    const updated = res.data.data
    const idx = orders.value.findIndex(o => o.id === updated.id)
    if (idx !== -1) orders.value[idx] = updated
    current.value = updated
  } catch (e) {
    alert(e.response?.data?.message ?? 'Erneut senden fehlgeschlagen.')
  } finally {
    retrying.value = false
  }
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' })
}

function formatAmount(amount, currency) {
  if (amount == null) return '—'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: currency || 'EUR' }).format(amount)
}

const adapterLogItems = computed(() => {
  if (!current.value?.adapter_log) return []
  const log = current.value.adapter_log
  return Array.isArray(log) ? log : [log]
})

onMounted(load)
</script>

<template>
  <div class="flex h-full overflow-hidden">
    <!-- Linke Liste -->
    <aside class="w-80 shrink-0 border-r border-base-300 flex flex-col bg-base-100">
      <div class="px-4 py-3 border-b border-base-300">
        <span class="font-semibold text-sm">Bestellungen</span>
        <div class="flex gap-2 mt-2">
          <input
            v-model="filterSearch"
            class="input input-xs input-bordered flex-1"
            placeholder="Bestellnr. oder E-Mail…"
            @keyup.enter="applyFilter"
          />
          <select v-model="filterStatus" class="select select-xs select-bordered w-32" @change="applyFilter">
            <option v-for="opt in STATUS_FILTER_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="p-4 text-sm text-center opacity-50">Lädt…</div>
      <div v-else-if="!orders.length" class="p-4 text-sm text-center opacity-50">Keine Bestellungen gefunden.</div>

      <ul class="flex-1 overflow-y-auto">
        <li
          v-for="o in orders"
          :key="o.id"
          class="px-4 py-2.5 cursor-pointer hover:bg-base-200 border-b border-base-200 text-sm"
          :class="{ 'bg-primary/10': current?.id === o.id }"
          @click="select(o)"
        >
          <div class="flex items-center gap-2">
            <Package class="w-4 h-4 shrink-0 opacity-50" />
            <span class="font-mono font-medium truncate">{{ o.order_number }}</span>
            <span class="ml-auto badge badge-xs" :class="STATUS_BADGE[o.status]">{{ STATUS_LABELS[o.status] }}</span>
          </div>
          <div class="text-xs opacity-50 mt-0.5 pl-6">{{ formatDate(o.submitted_at) }} · {{ formatAmount(o.total_amount, o.currency) }}</div>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex items-center justify-between px-4 py-2 border-t border-base-300 text-xs opacity-60">
        <button class="btn btn-xs btn-ghost" :disabled="page === 1" @click="prevPage"><ChevronLeft class="w-3.5 h-3.5" /></button>
        <span>{{ page }} / {{ meta.last_page }}</span>
        <button class="btn btn-xs btn-ghost" :disabled="page >= meta.last_page" @click="nextPage"><ChevronRight class="w-3.5 h-3.5" /></button>
      </div>
    </aside>

    <!-- Detailansicht -->
    <main class="flex-1 overflow-y-auto p-6">
      <div v-if="!current" class="flex flex-col items-center justify-center h-full opacity-40 gap-2">
        <Package class="w-12 h-12" />
        <p class="text-sm">Bestellung auswählen</p>
      </div>

      <template v-else>
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-lg font-semibold font-mono">{{ current.order_number }}</h2>
            <p class="text-xs opacity-50">{{ formatDate(current.submitted_at) }}</p>
          </div>
          <div class="flex gap-2">
            <button
              v-if="current.status === 'failed'"
              class="btn btn-sm btn-warning btn-outline"
              :disabled="retrying"
              @click="retryAdapter"
            >
              <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': retrying }" />
              Erneut senden
            </button>
            <button class="btn btn-sm btn-primary" :disabled="saving" @click="saveStatus">
              {{ saving ? 'Speichert…' : 'Speichern' }}
            </button>
            <button class="btn btn-sm btn-ghost btn-circle" @click="close"><X class="w-4 h-4" /></button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6 max-w-3xl">
          <!-- Status & Notizen -->
          <div class="form-control">
            <label class="label label-text text-xs">Status</label>
            <select v-model="current.status" class="select select-bordered select-sm">
              <option v-for="opt in STATUS_FILTER_OPTIONS.slice(1)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <div class="form-control">
            <label class="label label-text text-xs">Gesamtbetrag</label>
            <div class="input input-bordered input-sm flex items-center font-medium">
              {{ formatAmount(current.total_amount, current.currency) }}
            </div>
          </div>

          <div class="form-control col-span-2">
            <label class="label label-text text-xs">Interne Notizen</label>
            <textarea v-model="current.internal_notes" class="textarea textarea-bordered textarea-sm" rows="2" placeholder="Nur intern sichtbar…"></textarea>
          </div>

          <!-- Artikel-Snapshot -->
          <div class="col-span-2">
            <label class="label label-text text-xs mb-1">Bestellpositionen</label>
            <div class="overflow-x-auto rounded-lg border border-base-300">
              <table class="table table-xs table-zebra w-full">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Bezeichnung</th>
                    <th class="text-right">Menge</th>
                    <th class="text-right">Einzelpreis</th>
                    <th class="text-right">Gesamt</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in current.items_snapshot" :key="idx">
                    <td class="font-mono">{{ item.sku }}</td>
                    <td>{{ item.name }}</td>
                    <td class="text-right">{{ item.quantity }}</td>
                    <td class="text-right">{{ item.unit_price != null ? formatAmount(item.unit_price, item.currency) : '—' }}</td>
                    <td class="text-right font-medium">{{ item.line_price != null ? formatAmount(item.line_price, item.currency) : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Adressen -->
          <div v-if="current.billing_address">
            <label class="label label-text text-xs mb-1">Rechnungsadresse</label>
            <div class="bg-base-200 rounded-lg p-3 text-xs space-y-0.5">
              <div v-for="(val, key) in current.billing_address" :key="key" class="flex gap-2">
                <span class="opacity-50 w-24 shrink-0">{{ key }}</span>
                <span>{{ val }}</span>
              </div>
            </div>
          </div>

          <div v-if="current.shipping_address">
            <label class="label label-text text-xs mb-1">Lieferadresse</label>
            <div class="bg-base-200 rounded-lg p-3 text-xs space-y-0.5">
              <div v-for="(val, key) in current.shipping_address" :key="key" class="flex gap-2">
                <span class="opacity-50 w-24 shrink-0">{{ key }}</span>
                <span>{{ val }}</span>
              </div>
            </div>
          </div>

          <!-- Kundennotiz -->
          <div v-if="current.customer_notes" class="col-span-2">
            <label class="label label-text text-xs mb-1">Kundennotiz</label>
            <div class="bg-base-200 rounded-lg p-3 text-xs">{{ current.customer_notes }}</div>
          </div>

          <!-- Adapter-Log -->
          <div v-if="adapterLogItems.length" class="col-span-2">
            <label class="label label-text text-xs mb-1">Übermittlungs-Log</label>
            <div class="space-y-2">
              <div
                v-for="(entry, idx) in adapterLogItems"
                :key="idx"
                class="bg-base-200 rounded-lg p-3 text-xs font-mono"
                :class="{ 'border border-error/40': entry.success === false, 'border border-success/40': entry.success === true }"
              >
                <div class="flex items-center gap-2 mb-1">
                  <CheckCircle v-if="entry.success" class="w-3.5 h-3.5 text-success" />
                  <XCircle v-else class="w-3.5 h-3.5 text-error" />
                  <span>{{ entry.success ? 'Erfolg' : 'Fehler' }}</span>
                  <span v-if="entry.status_code" class="opacity-50">HTTP {{ entry.status_code }}</span>
                </div>
                <pre v-if="entry.response_body" class="text-[10px] opacity-70 whitespace-pre-wrap break-all">{{ entry.response_body }}</pre>
              </div>
            </div>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
