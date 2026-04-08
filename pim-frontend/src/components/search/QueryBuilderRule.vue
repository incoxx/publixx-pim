<script setup>
import { computed } from 'vue'
import { X } from 'lucide-vue-next'
import { translationLanguages } from '@/config/languages'

const props = defineProps({
  rule: { type: Object, required: true },
  attributes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update', 'remove'])

const selectedAttribute = computed(() =>
  props.attributes.find(a => a.id === props.rule.attribute_id)
)

// Group attributes by attribute_type for the dropdown
const groupedAttributes = computed(() => {
  if (props.attributes.length < 10) return null
  const groups = {}
  for (const attr of props.attributes) {
    const groupName = attr.attribute_type?.name_de || 'Sonstige'
    if (!groups[groupName]) groups[groupName] = { label: groupName, items: [] }
    groups[groupName].items.push(attr)
  }
  return Object.values(groups)
    .sort((a, b) => a.label.localeCompare(b.label))
    .map(group => ({
      ...group,
      items: group.items.slice().sort((a, b) =>
        (a.name_de || a.technical_name).localeCompare(b.name_de || b.technical_name)
      ),
    }))
})

// Sorted flat list for the non-grouped case (< 10 attributes)
const sortedAttributes = computed(() =>
  props.attributes.slice().sort((a, b) =>
    (a.name_de || a.technical_name).localeCompare(b.name_de || b.technical_name)
  )
)

const operatorOptions = computed(() => {
  const dt = selectedAttribute.value?.data_type
  if (!dt) return [{ value: 'eq', label: '=' }]

  const ops = []

  switch (dt) {
    case 'String':
    case 'RichText':
      ops.push(
        { value: 'like', label: 'enthält' },
        { value: 'eq', label: 'ist genau' },
        { value: 'starts_with', label: 'beginnt mit' },
        { value: 'ends_with', label: 'endet mit' },
        { value: 'neq', label: 'ist nicht' },
        { value: 'regex', label: 'Regex' },
        { value: 'soundex', label: 'klingt wie' },
      )
      break
    case 'Number':
    case 'Float':
      ops.push(
        { value: 'eq', label: '=' },
        { value: 'neq', label: '≠' },
        { value: 'gt', label: '>' },
        { value: 'lt', label: '<' },
        { value: 'gte', label: '≥' },
        { value: 'lte', label: '≤' },
        { value: 'between', label: 'zwischen' },
      )
      break
    case 'Date':
      ops.push(
        { value: 'eq', label: '=' },
        { value: 'gt', label: 'nach' },
        { value: 'lt', label: 'vor' },
        { value: 'between', label: 'zwischen' },
      )
      break
    case 'Selection':
    case 'Dictionary':
      ops.push(
        { value: 'eq', label: 'ist' },
        { value: 'in', label: 'ist einer von' },
      )
      break
    case 'Flag':
      ops.push({ value: 'eq', label: 'ist' })
      break
    default:
      ops.push({ value: 'eq', label: '=' })
  }

  ops.push(
    { value: 'exists', label: 'vorhanden' },
    { value: 'not_exists', label: 'nicht vorhanden' },
  )

  if (selectedAttribute.value?.is_translatable) {
    ops.push(
      { value: 'exists_lang', label: 'Sprache vorhanden' },
      { value: 'not_exists_lang', label: 'Sprache fehlt' },
    )
  }

  return ops
})

const needsValueInput = computed(() => !['exists', 'not_exists'].includes(props.rule.operator))
const isLanguageOp = computed(() => ['exists_lang', 'not_exists_lang'].includes(props.rule.operator))
const isBetween = computed(() => props.rule.operator === 'between')
const isMultiSelect = computed(() => props.rule.operator === 'in')
const isFlag = computed(() => selectedAttribute.value?.data_type === 'Flag')
const isSelection = computed(() => ['Selection', 'Dictionary'].includes(selectedAttribute.value?.data_type))
const valueListEntries = computed(() => selectedAttribute.value?.value_list?.entries || [])

function update(field, value) {
  const updated = { ...props.rule, [field]: value }
  if (field === 'attribute_id') {
    updated.operator = operatorOptions.value[0]?.value || 'eq'
    updated.value = ''
  }
  if (field === 'operator') {
    if (value === 'between') updated.value = ['', '']
    else if (value === 'in') updated.value = []
    else if (['exists', 'not_exists'].includes(value)) updated.value = null
    else if (['exists_lang', 'not_exists_lang'].includes(value)) updated.value = ''
    else updated.value = ''
  }
  emit('update', updated)
}

function updateBetweenValue(index, val) {
  const arr = Array.isArray(props.rule.value) ? [...props.rule.value] : ['', '']
  arr[index] = val
  emit('update', { ...props.rule, value: arr })
}

function toggleInValue(entryId) {
  const arr = Array.isArray(props.rule.value) ? [...props.rule.value] : []
  const idx = arr.indexOf(entryId)
  if (idx === -1) arr.push(entryId)
  else arr.splice(idx, 1)
  emit('update', { ...props.rule, value: arr })
}

function getInputType() {
  const dt = selectedAttribute.value?.data_type
  if (dt === 'Number' || dt === 'Float') return 'number'
  if (dt === 'Date') return 'date'
  return 'text'
}
</script>

<template>
  <div class="flex flex-wrap items-start gap-2 p-2 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)]">
    <!-- Attribute selector -->
    <select class="pim-input text-xs min-w-[140px] flex-1" :value="rule.attribute_id" @change="update('attribute_id', $event.target.value)">
      <option value="">— Attribut —</option>
      <template v-if="groupedAttributes">
        <optgroup v-for="group in groupedAttributes" :key="group.label" :label="group.label">
          <option v-for="attr in group.items" :key="attr.id" :value="attr.id">
            {{ attr.name_de || attr.technical_name }}
          </option>
        </optgroup>
      </template>
      <template v-else>
        <option v-for="attr in sortedAttributes" :key="attr.id" :value="attr.id">
          {{ attr.name_de || attr.technical_name }}
        </option>
      </template>
    </select>

    <!-- Operator -->
    <select v-if="rule.attribute_id" class="pim-input text-xs min-w-[100px]" :value="rule.operator" @change="update('operator', $event.target.value)">
      <option v-for="op in operatorOptions" :key="op.value" :value="op.value">{{ op.label }}</option>
    </select>

    <!-- Value input -->
    <template v-if="rule.attribute_id && needsValueInput">
      <!-- Language -->
      <select v-if="isLanguageOp" class="pim-input text-xs min-w-[120px]" :value="rule.value || ''" @change="update('value', $event.target.value)">
        <option value="">— Sprache —</option>
        <option v-for="lang in translationLanguages" :key="lang.code" :value="lang.code">{{ lang.label }} ({{ lang.code }})</option>
      </select>

      <!-- Flag -->
      <select v-else-if="isFlag" class="pim-input text-xs min-w-[80px]" :value="rule.value" @change="update('value', $event.target.value)">
        <option value="true">Ja</option>
        <option value="false">Nein</option>
      </select>

      <!-- Selection single -->
      <select v-else-if="isSelection && !isMultiSelect" class="pim-input text-xs min-w-[140px] flex-1" :value="rule.value" @change="update('value', $event.target.value)">
        <option value="">— Wert —</option>
        <option v-for="entry in valueListEntries" :key="entry.id" :value="entry.id">{{ entry.display_value_de || entry.code }}</option>
      </select>

      <!-- Multi-select chips -->
      <div v-else-if="isMultiSelect" class="flex flex-wrap gap-1 flex-1 min-w-[140px]">
        <label
          v-for="entry in valueListEntries" :key="entry.id"
          class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded border cursor-pointer select-none"
          :class="(rule.value || []).includes(entry.id)
            ? 'bg-[var(--color-accent-light)] border-[var(--color-accent)]/30 text-[var(--color-accent)]'
            : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
        >
          <input type="checkbox" class="hidden" :checked="(rule.value || []).includes(entry.id)" @change="toggleInValue(entry.id)" />
          {{ entry.display_value_de || entry.code }}
        </label>
      </div>

      <!-- Between -->
      <template v-else-if="isBetween">
        <input class="pim-input text-xs w-20" :type="getInputType()" :value="Array.isArray(rule.value) ? rule.value[0] : ''" placeholder="Von" @input="updateBetweenValue(0, $event.target.value)" />
        <span class="text-xs text-[var(--color-text-tertiary)] self-center">–</span>
        <input class="pim-input text-xs w-20" :type="getInputType()" :value="Array.isArray(rule.value) ? rule.value[1] : ''" placeholder="Bis" @input="updateBetweenValue(1, $event.target.value)" />
      </template>

      <!-- Default input -->
      <input v-else class="pim-input text-xs min-w-[120px] flex-1" :type="getInputType()" :value="rule.value" placeholder="Wert..." @input="update('value', $event.target.value)" />
    </template>

    <!-- Remove -->
    <button class="p-1 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)] shrink-0 self-center" @click="$emit('remove')">
      <X class="w-3.5 h-3.5" />
    </button>
  </div>
</template>
