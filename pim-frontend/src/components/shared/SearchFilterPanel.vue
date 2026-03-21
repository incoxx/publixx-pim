<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { ChevronDown, ChevronRight, X } from 'lucide-vue-next'
import searchApi from '@/api/search'
import hierarchiesApi from '@/api/hierarchies'
import { priceRegions } from '@/api/priceRegions'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      status: '',
      category_ids: [],
      attribute_filters: {},
      include_descendants: true,
      price_region_id: '',
      missing_translation: null,
    }),
  },
})

const emit = defineEmits(['update:modelValue'])

const hierarchies = ref([])
const hierarchyTrees = ref({})
const selectedHierarchyId = ref(null)
const searchableAttributes = ref([])
const showCategoryPicker = ref(false)
const priceRegionsList = ref([])

onMounted(async () => {
  try {
    const { data } = await hierarchiesApi.list()
    hierarchies.value = data.data || data
    if (hierarchies.value.length > 0) {
      selectedHierarchyId.value = hierarchies.value[0].id
      const trees = {}
      await Promise.all(hierarchies.value.map(async (h) => {
        const { data: treeData } = await hierarchiesApi.getTree(h.id)
        trees[h.id] = treeData.data || treeData
      }))
      hierarchyTrees.value = trees
    }
  } catch (e) { /* ignore */ }

  try {
    const { data } = await searchApi.searchableAttributes()
    searchableAttributes.value = data.data || data
  } catch (e) { /* ignore */ }

  try {
    const { data } = await priceRegions.list()
    priceRegionsList.value = data.data || data
  } catch (e) { /* ignore */ }
})

const currentHierarchyTree = computed(() => {
  if (!selectedHierarchyId.value) return []
  return hierarchyTrees.value[selectedHierarchyId.value] || []
})

const flatCategoryNodes = computed(() => {
  const result = []
  function flatten(nodes, prefix = '') {
    for (const node of nodes) {
      const name = node.name_de || node.name_en || node.id
      result.push({ id: node.id, label: prefix + name })
      if (node.children?.length) flatten(node.children, prefix + name + ' > ')
    }
  }
  flatten(currentHierarchyTree.value)
  return result
})

// Clear categories that don't belong to the new hierarchy
watch(selectedHierarchyId, () => {
  const validIds = new Set(flatCategoryNodes.value.map(n => n.id))
  const current = props.modelValue.category_ids || []
  const filtered = current.filter(id => validIds.has(id))
  if (filtered.length !== current.length) {
    update('category_ids', filtered)
  }
})

const translatableAttributes = computed(() => {
  return searchableAttributes.value.filter(a => a.is_translatable && (a.data_type === 'String' || a.data_type === 'RichText'))
})

const availableLanguages = [
  { code: 'de', label: 'Deutsch' },
  { code: 'en', label: 'Englisch' },
  { code: 'fr', label: 'Französisch' },
  { code: 'it', label: 'Italienisch' },
  { code: 'es', label: 'Spanisch' },
  { code: 'nl', label: 'Niederländisch' },
  { code: 'pl', label: 'Polnisch' },
  { code: 'pt', label: 'Portugiesisch' },
  { code: 'cs', label: 'Tschechisch' },
  { code: 'da', label: 'Dänisch' },
  { code: 'sv', label: 'Schwedisch' },
]

const activeFilterCount = computed(() => {
  let count = (props.modelValue.category_ids || []).length
  if (props.modelValue.status) count++
  if (props.modelValue.price_region_id) count++
  if (props.modelValue.missing_translation?.attribute_id && props.modelValue.missing_translation?.target_language) count++
  for (const val of Object.values(props.modelValue.attribute_filters || {})) {
    if (val !== '' && val !== null && val !== undefined) count++
  }
  return count
})

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function toggleCategory(id) {
  const ids = [...(props.modelValue.category_ids || [])]
  const idx = ids.indexOf(id)
  if (idx === -1) ids.push(id)
  else ids.splice(idx, 1)
  update('category_ids', ids)
}

function updateAttributeFilter(attrId, value) {
  const filters = { ...(props.modelValue.attribute_filters || {}) }
  if (value === '' || value === null || value === undefined) {
    delete filters[attrId]
  } else {
    filters[attrId] = value
  }
  update('attribute_filters', filters)
}

function updateMissingTranslation(key, value) {
  const current = props.modelValue.missing_translation || {}
  const updated = { ...current, [key]: value }
  if (!updated.attribute_id && !updated.target_language) {
    update('missing_translation', null)
  } else {
    update('missing_translation', updated)
  }
}

function clearAll() {
  emit('update:modelValue', {
    status: '',
    category_ids: [],
    attribute_filters: {},
    include_descendants: true,
    price_region_id: '',
    missing_translation: null,
  })
}

function getFilterInputType(dataType) {
  switch (dataType) {
    case 'Number': case 'Float': return 'number'
    case 'Date': return 'date'
    default: return 'text'
  }
}
</script>

<template>
  <div class="pim-card p-4 space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Suchfilter</h3>
      <button
        v-if="activeFilterCount > 0"
        class="text-xs text-[var(--color-accent)] hover:underline"
        @click="clearAll"
      >
        Alle zurücksetzen ({{ activeFilterCount }})
      </button>
    </div>

    <!-- Status -->
    <div>
      <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Produkt-Status</p>
      <select class="pim-input text-xs w-48" :value="modelValue.status" @change="update('status', $event.target.value)">
        <option value="">— Alle —</option>
        <option value="active">Aktiv</option>
        <option value="draft">Entwurf</option>
        <option value="inactive">Inaktiv</option>
        <option value="discontinued">Auslaufend</option>
      </select>
    </div>

    <!-- Price Region -->
    <div v-if="priceRegionsList.length > 0">
      <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Preisregion</p>
      <select class="pim-input text-xs w-48" :value="modelValue.price_region_id" @change="update('price_region_id', $event.target.value)">
        <option value="">— Alle —</option>
        <option v-for="region in priceRegionsList" :key="region.id" :value="region.id">{{ region.name }} ({{ region.code }})</option>
      </select>
    </div>

    <!-- Categories -->
    <div>
      <button
        class="flex items-center gap-2 text-[12px] font-medium text-[var(--color-text-secondary)] mb-2 cursor-pointer"
        @click="showCategoryPicker = !showCategoryPicker"
      >
        <component :is="showCategoryPicker ? ChevronDown : ChevronRight" class="w-3.5 h-3.5" />
        Kategorien
        <span v-if="(modelValue.category_ids || []).length > 0" class="pim-badge bg-[var(--color-accent-light)] text-[var(--color-accent)] text-[10px] px-1.5">
          {{ (modelValue.category_ids || []).length }}
        </span>
      </button>
      <div v-if="showCategoryPicker" class="space-y-2">
        <!-- Hierarchy selector -->
        <div v-if="hierarchies.length > 1" class="flex items-center gap-2">
          <label class="text-[11px] font-medium text-[var(--color-text-tertiary)]">Hierarchie:</label>
          <select
            class="pim-input text-xs flex-1"
            :value="selectedHierarchyId"
            @change="selectedHierarchyId = $event.target.value"
          >
            <option v-for="h in hierarchies" :key="h.id" :value="h.id">
              {{ h.name_de || h.name_en || h.technical_name }}
            </option>
          </select>
        </div>
        <!-- Category nodes -->
        <div class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
          <label
            v-for="cat in flatCategoryNodes"
            :key="cat.id"
            class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] cursor-pointer text-xs"
          >
            <input
              type="checkbox"
              :checked="(modelValue.category_ids || []).includes(cat.id)"
              @change="toggleCategory(cat.id)"
              class="rounded border-[var(--color-border)]"
            />
            <span>{{ cat.label }}</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Missing Translation Filter -->
    <div v-if="translatableAttributes.length > 0">
      <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Fehlende Übersetzung</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">Attribut</label>
          <select
            class="pim-input text-xs"
            :value="modelValue.missing_translation?.attribute_id || ''"
            @change="updateMissingTranslation('attribute_id', $event.target.value || null)"
          >
            <option value="">— Keins —</option>
            <option v-for="attr in translatableAttributes" :key="attr.id" :value="attr.id">
              {{ attr.name_de || attr.technical_name }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">Zielsprache</label>
          <select
            class="pim-input text-xs"
            :value="modelValue.missing_translation?.target_language || ''"
            @change="updateMissingTranslation('target_language', $event.target.value || null)"
          >
            <option value="">— Wählen —</option>
            <option v-for="lang in availableLanguages" :key="lang.code" :value="lang.code">
              {{ lang.label }} ({{ lang.code }})
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Attributes -->
    <div v-if="searchableAttributes.length > 0">
      <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Attribute</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div v-for="attr in searchableAttributes" :key="attr.id">
          <label class="block text-[11px] font-medium text-[var(--color-text-tertiary)] mb-1">
            {{ attr.name_de || attr.technical_name }}
          </label>
          <template v-if="attr.data_type === 'Flag'">
            <select
              class="pim-input text-xs"
              :value="(modelValue.attribute_filters || {})[attr.id] ?? ''"
              @change="updateAttributeFilter(attr.id, $event.target.value)"
            >
              <option value="">— Alle —</option>
              <option value="true">Ja</option>
              <option value="false">Nein</option>
            </select>
          </template>
          <template v-else-if="(attr.data_type === 'Selection' || attr.data_type === 'Dictionary') && attr.value_list?.entries?.length">
            <select
              class="pim-input text-xs"
              :value="(modelValue.attribute_filters || {})[attr.id] ?? ''"
              @change="updateAttributeFilter(attr.id, $event.target.value)"
            >
              <option value="">— Alle —</option>
              <option v-for="entry in attr.value_list.entries" :key="entry.id" :value="entry.id">
                {{ entry.display_value_de || entry.code }}
              </option>
            </select>
          </template>
          <template v-else>
            <input
              class="pim-input text-xs"
              :type="getFilterInputType(attr.data_type)"
              :value="(modelValue.attribute_filters || {})[attr.id] ?? ''"
              placeholder="Wert eingeben..."
              @input="updateAttributeFilter(attr.id, $event.target.value)"
            />
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
