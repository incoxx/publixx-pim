<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLocaleStore } from '@/stores/locale'
import {
  ArrowLeft, Save, RotateCcw, Check, Pencil,
  ChevronDown, ChevronRight, AlertCircle,
} from 'lucide-vue-next'
import bulkEditorApi from '@/api/bulkEditor'

const route = useRoute()
const router = useRouter()
const localeStore = useLocaleStore()

// --- State ---
const step = ref('select-attributes') // 'select-attributes' | 'edit'
const loading = ref(false)
const saving = ref(false)
const saveResult = ref(null)
const error = ref(null)

const language = ref('de')
const products = ref([])
const allAttributes = ref([])
const selectedAttributeIds = ref(new Set())
const originalValues = ref({}) // "productId|attributeId" -> value
const editedValues = reactive({}) // "productId|attributeId" -> value

// Product IDs from query string
const productIds = computed(() => {
  const ids = route.query.ids
  if (!ids) return []
  return ids.split(',').filter(Boolean)
})

const selectedAttributes = computed(() =>
  allAttributes.value.filter(a => selectedAttributeIds.value.has(a.id))
)

const hasChanges = computed(() => {
  for (const key in editedValues) {
    if (editedValues[key] !== originalValues.value[key]) return true
  }
  return false
})

const changeCount = computed(() => {
  let count = 0
  for (const key in editedValues) {
    if (editedValues[key] !== originalValues.value[key]) count++
  }
  return count
})

// Group attributes by type for easier selection
const groupedAttributes = computed(() => {
  const groups = {}
  for (const attr of allAttributes.value) {
    const type = attr.data_type || 'Other'
    if (!groups[type]) groups[type] = []
    groups[type].push(attr)
  }
  return groups
})

// --- Load ---
onMounted(async () => {
  if (productIds.value.length === 0) {
    error.value = 'Keine Produkte ausgewählt'
    return
  }

  loading.value = true
  try {
    // First load: get all available attributes for these products
    const { data } = await bulkEditorApi.load({
      productIds: productIds.value,
      language: language.value,
    })
    products.value = data.products || []
    allAttributes.value = data.attributes || []
    originalValues.value = data.values || {}

    // Copy original to edited
    for (const key in data.values) {
      editedValues[key] = data.values[key]
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
})

// --- Actions ---
function toggleAttribute(attrId) {
  if (selectedAttributeIds.value.has(attrId)) {
    selectedAttributeIds.value.delete(attrId)
  } else {
    selectedAttributeIds.value.add(attrId)
  }
  selectedAttributeIds.value = new Set(selectedAttributeIds.value) // trigger reactivity
}

function selectAllAttributes() {
  for (const attr of allAttributes.value) {
    selectedAttributeIds.value.add(attr.id)
  }
  selectedAttributeIds.value = new Set(selectedAttributeIds.value)
}

function deselectAllAttributes() {
  selectedAttributeIds.value = new Set()
}

function startEditing() {
  step.value = 'edit'
}

function goBackToSelection() {
  step.value = 'select-attributes'
}

function getCellKey(productId, attributeId) {
  return `${productId}|${attributeId}`
}

function getCellValue(productId, attributeId) {
  const key = getCellKey(productId, attributeId)
  return editedValues[key] ?? null
}

function setCellValue(productId, attributeId, value) {
  const key = getCellKey(productId, attributeId)
  editedValues[key] = value
}

function isCellChanged(productId, attributeId) {
  const key = getCellKey(productId, attributeId)
  return editedValues[key] !== originalValues.value[key]
}

function resetCell(productId, attributeId) {
  const key = getCellKey(productId, attributeId)
  editedValues[key] = originalValues.value[key] ?? null
}

function resetAll() {
  for (const key in originalValues.value) {
    editedValues[key] = originalValues.value[key]
  }
  // Also reset keys not in original
  for (const key in editedValues) {
    if (!(key in originalValues.value)) {
      editedValues[key] = null
    }
  }
  saveResult.value = null
}

async function saveChanges() {
  saving.value = true
  saveResult.value = null

  const changes = []
  for (const key in editedValues) {
    if (editedValues[key] === originalValues.value[key]) continue
    const [productId, attributeId] = key.split('|')
    const attr = allAttributes.value.find(a => a.id === attributeId)
    changes.push({
      product_id: productId,
      attribute_id: attributeId,
      value: editedValues[key],
      language: attr?.is_translatable ? language.value : null,
    })
  }

  if (changes.length === 0) {
    saving.value = false
    return
  }

  try {
    const { data } = await bulkEditorApi.save(changes)
    saveResult.value = data

    // Update originals to match new state
    for (const key in editedValues) {
      originalValues.value[key] = editedValues[key]
    }
  } catch (e) {
    saveResult.value = {
      message: 'Speichern fehlgeschlagen',
      errors: [e.response?.data?.message || e.message],
    }
  } finally {
    saving.value = false
  }
}

async function changeLanguage(lang) {
  language.value = lang
  loading.value = true
  try {
    const { data } = await bulkEditorApi.load({
      productIds: productIds.value,
      attributeIds: [...selectedAttributeIds.value],
      language: lang,
    })
    originalValues.value = data.values || {}
    for (const key in data.values) {
      editedValues[key] = data.values[key]
    }
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-3">
        <button class="pim-btn pim-btn-ghost text-xs" @click="router.back()">
          <ArrowLeft class="w-4 h-4" :stroke-width="1.75" />
        </button>
        <div class="w-8 h-8 rounded-lg bg-[var(--color-accent-light)]/20 flex items-center justify-center">
          <Pencil class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="2" />
        </div>
        <div>
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Bulk-Editor</h2>
          <p class="text-xs text-[var(--color-text-tertiary)]">{{ products.length }} Produkt{{ products.length !== 1 ? 'e' : '' }} bearbeiten</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <!-- Language selector -->
        <div v-if="step === 'edit'" class="flex items-center gap-1">
          <span class="text-[11px] text-[var(--color-text-tertiary)]">Sprache:</span>
          <select
            class="pim-input text-xs w-24"
            :value="language"
            @change="changeLanguage($event.target.value)"
          >
            <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">
              {{ loc.label }}
            </option>
          </select>
        </div>
        <button
          v-if="step === 'edit' && hasChanges"
          class="pim-btn pim-btn-ghost text-xs"
          @click="resetAll"
        >
          <RotateCcw class="w-3.5 h-3.5" :stroke-width="1.75" />
          Zurücksetzen
        </button>
        <button
          v-if="step === 'edit'"
          class="pim-btn pim-btn-primary text-xs"
          :disabled="!hasChanges || saving"
          @click="saveChanges"
        >
          <Save class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ saving ? 'Speichere…' : `Speichern (${changeCount})` }}
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="flex items-center gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)]">
      <AlertCircle class="w-4 h-4 shrink-0" :stroke-width="2" />
      <p class="text-xs">{{ error }}</p>
    </div>

    <!-- Save result -->
    <div v-if="saveResult" class="text-xs p-3 rounded-lg" :class="saveResult.errors?.length ? 'bg-[var(--color-warning-light)] text-[var(--color-warning)]' : 'bg-[var(--color-success-light)] text-[var(--color-success)]'">
      <p class="font-medium">{{ saveResult.message }}</p>
      <p v-if="saveResult.updated !== undefined">{{ saveResult.updated }} Werte gespeichert</p>
      <ul v-if="saveResult.errors?.length" class="mt-1 list-disc list-inside">
        <li v-for="(err, i) in saveResult.errors" :key="i">{{ err }}</li>
      </ul>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="pim-card p-8">
      <div class="space-y-3">
        <div v-for="i in 6" :key="i" class="pim-skeleton h-8 rounded" />
      </div>
    </div>

    <!-- Step 1: Attribute selection -->
    <template v-else-if="step === 'select-attributes' && allAttributes.length > 0">
      <div class="pim-card p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Attribute auswählen</h3>
          <div class="flex gap-2">
            <button class="text-xs text-[var(--color-accent)] hover:underline" @click="selectAllAttributes">Alle</button>
            <button class="text-xs text-[var(--color-text-tertiary)] hover:underline" @click="deselectAllAttributes">Keine</button>
          </div>
        </div>
        <p class="text-xs text-[var(--color-text-tertiary)]">
          Wähle die Attribute aus, die du für alle {{ products.length }} Produkte bearbeiten möchtest.
        </p>

        <div v-for="(attrs, typeName) in groupedAttributes" :key="typeName" class="space-y-2">
          <p class="text-[11px] font-medium text-[var(--color-text-secondary)] uppercase tracking-wider">{{ typeName }}</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1">
            <label
              v-for="attr in attrs"
              :key="attr.id"
              :class="[
                'flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer text-xs transition-colors',
                selectedAttributeIds.has(attr.id) ? 'bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20' : 'hover:bg-[var(--color-bg)] border border-transparent'
              ]"
            >
              <input
                type="checkbox"
                :checked="selectedAttributeIds.has(attr.id)"
                @change="toggleAttribute(attr.id)"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]"
              />
              <span class="text-[var(--color-text-primary)]">{{ attr.name_de || attr.technical_name }}</span>
              <span v-if="attr.is_translatable" class="pim-badge bg-[var(--color-info-light)] text-[var(--color-info)] text-[9px] px-1">i18n</span>
            </label>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button
          class="pim-btn pim-btn-primary"
          :disabled="selectedAttributeIds.size === 0"
          @click="startEditing"
        >
          <Pencil class="w-4 h-4" :stroke-width="1.75" />
          {{ selectedAttributeIds.size }} Attribute bearbeiten
        </button>
      </div>
    </template>

    <!-- Step 2: Excel-like editor -->
    <template v-else-if="step === 'edit'">
      <div class="flex items-center gap-2 mb-2">
        <button class="text-xs text-[var(--color-accent)] hover:underline" @click="goBackToSelection">
          ← Attribute ändern
        </button>
        <span class="text-[11px] text-[var(--color-text-tertiary)]">
          {{ selectedAttributes.length }} Attribute × {{ products.length }} Produkte
        </span>
      </div>

      <div class="pim-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-[13px] border-collapse">
            <thead class="sticky top-0 z-10">
              <tr class="bg-[var(--color-bg)] border-b border-[var(--color-border)]">
                <th class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] sticky left-0 z-20 bg-[var(--color-bg)] min-w-[140px] border-r border-[var(--color-border)]">
                  SKU / Name
                </th>
                <th
                  v-for="attr in selectedAttributes"
                  :key="attr.id"
                  class="px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)] min-w-[160px]"
                  :title="attr.technical_name"
                >
                  {{ attr.name_de || attr.technical_name }}
                  <span v-if="attr.is_translatable" class="text-[var(--color-info)] ml-0.5">({{ language }})</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in products"
                :key="product.id"
                class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)]/50"
              >
                <!-- Product info (sticky column) -->
                <td class="px-3 py-1.5 sticky left-0 z-10 bg-[var(--color-surface)] border-r border-[var(--color-border)]">
                  <div class="font-mono text-[11px] text-[var(--color-text-secondary)]">{{ product.sku }}</div>
                  <div class="text-xs text-[var(--color-text-primary)] truncate max-w-[180px]">{{ product.name }}</div>
                </td>
                <!-- Attribute cells -->
                <td
                  v-for="attr in selectedAttributes"
                  :key="attr.id"
                  :class="[
                    'px-1 py-1',
                    isCellChanged(product.id, attr.id) ? 'bg-amber-50' : '',
                  ]"
                >
                  <!-- String -->
                  <input
                    v-if="attr.data_type === 'String'"
                    type="text"
                    class="pim-input text-xs w-full"
                    :value="getCellValue(product.id, attr.id) ?? ''"
                    @input="setCellValue(product.id, attr.id, $event.target.value)"
                    @dblclick="resetCell(product.id, attr.id)"
                  />
                  <!-- Number / Float -->
                  <input
                    v-else-if="attr.data_type === 'Number' || attr.data_type === 'Float'"
                    type="number"
                    step="any"
                    class="pim-input text-xs w-full"
                    :value="getCellValue(product.id, attr.id)"
                    @input="setCellValue(product.id, attr.id, $event.target.value ? Number($event.target.value) : null)"
                    @dblclick="resetCell(product.id, attr.id)"
                  />
                  <!-- Date -->
                  <input
                    v-else-if="attr.data_type === 'Date'"
                    type="date"
                    class="pim-input text-xs w-full"
                    :value="getCellValue(product.id, attr.id) ?? ''"
                    @input="setCellValue(product.id, attr.id, $event.target.value || null)"
                    @dblclick="resetCell(product.id, attr.id)"
                  />
                  <!-- Flag -->
                  <div v-else-if="attr.data_type === 'Flag'" class="flex items-center justify-center h-8">
                    <input
                      type="checkbox"
                      :checked="!!getCellValue(product.id, attr.id)"
                      @change="setCellValue(product.id, attr.id, $event.target.checked)"
                      class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]"
                    />
                  </div>
                  <!-- Selection / Dictionary -->
                  <select
                    v-else-if="(attr.data_type === 'Selection' || attr.data_type === 'Dictionary') && attr.value_list?.entries?.length"
                    class="pim-input text-xs w-full"
                    :value="getCellValue(product.id, attr.id) ?? ''"
                    @change="setCellValue(product.id, attr.id, $event.target.value || null)"
                  >
                    <option value="">—</option>
                    <option
                      v-for="entry in attr.value_list.entries"
                      :key="entry.id"
                      :value="entry.id"
                    >
                      {{ entry.display_value_de || entry.code }}
                    </option>
                  </select>
                  <!-- Fallback: text -->
                  <input
                    v-else
                    type="text"
                    class="pim-input text-xs w-full"
                    :value="getCellValue(product.id, attr.id) ?? ''"
                    @input="setCellValue(product.id, attr.id, $event.target.value)"
                    @dblclick="resetCell(product.id, attr.id)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Change summary footer -->
      <div v-if="hasChanges" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)] border border-[var(--color-accent)]/20 rounded-lg">
        <span class="text-xs text-[var(--color-text-secondary)]">
          {{ changeCount }} Änderung{{ changeCount !== 1 ? 'en' : '' }} vorgemerkt
        </span>
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-ghost text-xs" @click="resetAll">
            <RotateCcw class="w-3.5 h-3.5" :stroke-width="1.75" />
            Zurücksetzen
          </button>
          <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="saveChanges">
            <Save class="w-3.5 h-3.5" :stroke-width="1.75" />
            {{ saving ? 'Speichere…' : 'Alle speichern' }}
          </button>
        </div>
      </div>
    </template>

    <!-- No products -->
    <div v-else-if="!loading && products.length === 0 && !error" class="text-center py-16">
      <Pencil class="w-10 h-10 mx-auto mb-3 text-[var(--color-border-strong)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Produkte für den Bulk-Editor ausgewählt</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">
        Wähle Produkte in der Produktliste, Suche oder Merkliste aus
      </p>
    </div>
  </div>
</template>
