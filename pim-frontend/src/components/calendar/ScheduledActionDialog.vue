<script setup>
import { ref, computed, watch } from 'vue'
import { X } from 'lucide-vue-next'
import scheduledActionsApi from '@/api/scheduledActions'
import productsApi from '@/api/products'

const props = defineProps({
  visible: { type: Boolean, default: false },
  initialDate: { type: String, default: null },
  initialProductId: { type: String, default: null },
  editAction: { type: Object, default: null },
  exportJobs: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref(null)
const productSearch = ref('')
const productResults = ref([])
const searchTimeout = ref(null)

const form = ref({
  title: '',
  action_type: 'activate_product',
  scheduled_at: '',
  product_id: null,
  product_ids: [],
  payload: {},
  color: null,
})

const selectedProductName = ref('')

const ACTION_TYPES = [
  { value: 'activate_product', label: 'Produkt aktivieren' },
  { value: 'deactivate_product', label: 'Produkt deaktivieren' },
  { value: 'price_change', label: 'Preisänderung' },
  { value: 'data_change', label: 'Datenänderung' },
  { value: 'export', label: 'Export' },
]

const needsProduct = computed(() => {
  return ['activate_product', 'deactivate_product', 'price_change', 'data_change'].includes(form.value.action_type)
})

const isEdit = computed(() => !!props.editAction)
const dialogTitle = computed(() => isEdit.value ? 'Aktion bearbeiten' : 'Neue Aktion planen')

watch(() => props.visible, (val) => {
  if (val) {
    if (props.editAction) {
      form.value = {
        title: props.editAction.title,
        action_type: props.editAction.action_type,
        scheduled_at: props.editAction.scheduled_at?.slice(0, 16) || '',
        product_id: props.editAction.product_id,
        product_ids: props.editAction.product_ids || [],
        payload: props.editAction.payload || {},
        color: props.editAction.color,
      }
      selectedProductName.value = props.editAction.product_name || ''
    } else {
      form.value = {
        title: '',
        action_type: 'activate_product',
        scheduled_at: props.initialDate ? `${props.initialDate}T09:00` : '',
        product_id: props.initialProductId || null,
        product_ids: [],
        payload: {},
        color: null,
      }
      selectedProductName.value = ''
    }
    error.value = null
  }
})

watch(() => form.value.action_type, (type) => {
  // Initialize payload defaults based on type
  if (type === 'price_change') {
    form.value.payload = form.value.payload?.prices ? form.value.payload : { prices: [{ price_type_id: '', amount: 0, currency: 'EUR' }] }
  } else if (type === 'data_change') {
    form.value.payload = form.value.payload?.attributes ? form.value.payload : { attributes: [{ attribute_id: '', value_string: '' }] }
  } else if (type === 'export') {
    form.value.payload = form.value.payload?.export_job_id ? form.value.payload : { export_job_id: '' }
  } else {
    form.value.payload = form.value.payload?.target_status ? form.value.payload : { target_status: type === 'activate_product' ? 'active' : 'inactive' }
  }
})

async function searchProducts() {
  if (productSearch.value.length < 2) {
    productResults.value = []
    return
  }
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(async () => {
    try {
      const { data } = await productsApi.list({ search: productSearch.value, perPage: 10 })
      productResults.value = data.data || data || []
    } catch { productResults.value = [] }
  }, 300)
}

function selectProduct(product) {
  form.value.product_id = product.id
  selectedProductName.value = product.name || product.sku
  productSearch.value = ''
  productResults.value = []
}

function clearProduct() {
  form.value.product_id = null
  selectedProductName.value = ''
}

async function save() {
  saving.value = true
  error.value = null

  try {
    const payload = { ...form.value }
    if (payload.scheduled_at && !payload.scheduled_at.includes('T')) {
      payload.scheduled_at += 'T00:00:00'
    }

    if (isEdit.value) {
      await scheduledActionsApi.update(props.editAction.id, payload)
    } else {
      await scheduledActionsApi.create(payload)
    }
    emit('saved')
    emit('close')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>
      <div class="relative bg-[var(--color-bg-primary)] rounded-lg shadow-xl border border-[var(--color-border)] w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-[var(--color-border)]">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ dialogTitle }}</h3>
          <button class="p-1 rounded hover:bg-[var(--color-bg-hover)]" @click="emit('close')">
            <X class="w-4 h-4 text-[var(--color-text-tertiary)]" />
          </button>
        </div>

        <!-- Body -->
        <div class="p-4 space-y-4">
          <!-- Error -->
          <div v-if="error" class="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 rounded p-2">{{ error }}</div>

          <!-- Title -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Titel</label>
            <input v-model="form.title" type="text" class="pim-input text-xs w-full" placeholder="z.B. Sommerkollektion aktivieren" />
          </div>

          <!-- Action Type -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Aktionstyp</label>
            <select v-model="form.action_type" class="pim-input text-xs w-full">
              <option v-for="t in ACTION_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <!-- Date/Time -->
          <div>
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Zeitpunkt</label>
            <input v-model="form.scheduled_at" type="datetime-local" class="pim-input text-xs w-full" />
          </div>

          <!-- Product (wenn nötig) -->
          <div v-if="needsProduct">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Produkt</label>
            <div v-if="selectedProductName" class="flex items-center gap-2 pim-input text-xs">
              <span class="flex-1 truncate">{{ selectedProductName }}</span>
              <button @click="clearProduct" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
                <X class="w-3 h-3" />
              </button>
            </div>
            <div v-else class="relative">
              <input
                v-model="productSearch"
                type="text"
                class="pim-input text-xs w-full"
                placeholder="Produkt suchen..."
                @input="searchProducts"
              />
              <div v-if="productResults.length" class="absolute z-10 w-full mt-1 bg-[var(--color-bg-primary)] border border-[var(--color-border)] rounded shadow-lg max-h-40 overflow-y-auto">
                <div
                  v-for="p in productResults"
                  :key="p.id"
                  class="px-3 py-1.5 text-xs cursor-pointer hover:bg-[var(--color-bg-hover)]"
                  @click="selectProduct(p)"
                >
                  <span class="font-medium">{{ p.name || '—' }}</span>
                  <span class="text-[var(--color-text-tertiary)] ml-2">{{ p.sku }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Payload: Price Change -->
          <div v-if="form.action_type === 'price_change' && form.payload.prices">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Preis</label>
            <div class="flex gap-2">
              <input v-model="form.payload.prices[0].amount" type="number" step="0.01" class="pim-input text-xs flex-1" placeholder="Betrag" />
              <input v-model="form.payload.prices[0].currency" type="text" class="pim-input text-xs w-16" placeholder="EUR" />
            </div>
          </div>

          <!-- Payload: Export -->
          <div v-if="form.action_type === 'export'">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Export-Job</label>
            <select v-model="form.payload.export_job_id" class="pim-input text-xs w-full">
              <option value="">— Export-Job wählen —</option>
              <option v-for="job in exportJobs" :key="job.id" :value="job.id">{{ job.name }}</option>
            </select>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 p-4 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-ghost text-xs" @click="emit('close')">Abbrechen</button>
          <button class="pim-btn pim-btn-primary text-xs" @click="save" :disabled="saving || !form.title || !form.scheduled_at || (needsProduct && !form.product_id && (!form.product_ids || form.product_ids.length === 0))">
            {{ saving ? 'Speichern...' : (isEdit ? 'Aktualisieren' : 'Planen') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
