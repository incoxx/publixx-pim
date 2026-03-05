<script setup>
import { ref, onMounted, computed } from 'vue'
import { ChevronDown, ChevronRight, X } from 'lucide-vue-next'
import searchApi from '@/api/search'
import hierarchiesApi from '@/api/hierarchies'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({
      status: '',
      category_ids: [],
      attribute_filters: {},
      include_descendants: true,
    }),
  },
})

const emit = defineEmits(['update:modelValue'])

const hierarchyTree = ref([])
const searchableAttributes = ref([])
const showCategoryPicker = ref(false)

onMounted(async () => {
  try {
    const { data } = await hierarchiesApi.list()
    const hierarchies = data.data || data
    if (hierarchies.length > 0) {
      const { data: treeData } = await hierarchiesApi.getTree(hierarchies[0].id)
      hierarchyTree.value = treeData.data || treeData
    }
  } catch (e) { /* ignore */ }

  try {
    const { data } = await searchApi.searchableAttributes()
    searchableAttributes.value = data.data || data
  } catch (e) { /* ignore */ }
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
  flatten(hierarchyTree.value)
  return result
})

const activeFilterCount = computed(() => {
  let count = (props.modelValue.category_ids || []).length
  if (props.modelValue.status) count++
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

function clearAll() {
  emit('update:modelValue', {
    status: '',
    category_ids: [],
    attribute_filters: {},
    include_descendants: true,
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
      <div v-if="showCategoryPicker" class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded-lg p-2 space-y-0.5">
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
