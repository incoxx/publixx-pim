<script setup>
import { computed } from 'vue'

const props = defineProps({
  diffData: { type: Object, required: true },
})

const fieldLabels = {
  name: 'Name',
  sku: 'SKU',
  ean: 'EAN',
  status: 'Status',
  master_hierarchy_node_id: 'Hierarchie-Knoten',
}

const statusLabels = {
  current: 'Aktueller Stand',
  draft: 'Entwurf',
  active: 'Aktiv',
  inactive: 'Inaktiv',
  discontinued: 'Auslaufend',
}

const baseFields = computed(() =>
  props.diffData.fields.filter(f => f.type !== 'attribute')
)
const attributeFields = computed(() =>
  props.diffData.fields.filter(f => f.type === 'attribute')
)

const changedCount = computed(() =>
  props.diffData.fields.filter(f => f.changed).length
)

function getFieldLabel(field) {
  return field.label || fieldLabels[field.field] || field.field
}

function formatValue(field, value) {
  if (value === null || value === undefined || value === '') return '—'
  if (field.field === 'status') return statusLabels[value] || value
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
</script>

<template>
  <div class="space-y-3">
    <!-- Header -->
    <div class="flex items-center justify-between text-xs text-[var(--color-text-tertiary)]">
      <span>
        {{ diffData.left.version_number ? 'Version ' + diffData.left.version_number : 'Aktueller Stand' }}
        →
        {{ diffData.right.version_number ? 'Version ' + diffData.right.version_number : 'Aktueller Stand' }}
      </span>
      <span>
        {{ changedCount }} {{ changedCount === 1 ? 'Änderung' : 'Änderungen' }}
      </span>
    </div>

    <!-- Diff Table -->
    <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
      <table class="w-full text-[13px]">
        <thead>
          <tr class="bg-[var(--color-bg)]">
            <th class="text-left px-3 py-2 text-[11px] font-semibold text-[var(--color-text-secondary)] w-1/5">
              Feld
            </th>
            <th class="text-left px-3 py-2 text-[11px] font-semibold text-[var(--color-text-secondary)] w-2/5">
              {{ diffData.left.version_number ? 'Version ' + diffData.left.version_number : 'Aktueller Stand' }}
              <span class="font-normal text-[var(--color-text-tertiary)]">
                ({{ diffData.left.created_at ? new Date(diffData.left.created_at).toLocaleDateString('de-DE') : 'jetzt' }})
              </span>
            </th>
            <th class="text-left px-3 py-2 text-[11px] font-semibold text-[var(--color-text-secondary)] w-2/5">
              {{ diffData.right.version_number ? 'Version ' + diffData.right.version_number : 'Aktueller Stand' }}
              <span class="font-normal text-[var(--color-text-tertiary)]">
                ({{ diffData.right.created_at ? new Date(diffData.right.created_at).toLocaleDateString('de-DE') : 'jetzt' }})
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <!-- Base fields -->
          <tr
            v-for="field in baseFields"
            :key="field.field"
            :class="[
              'border-t border-[var(--color-border)]',
              field.changed ? '' : 'opacity-60',
            ]"
          >
            <td class="px-3 py-2 font-medium text-[var(--color-text-secondary)]">
              {{ getFieldLabel(field) }}
            </td>
            <td
              :class="[
                'px-3 py-2',
                field.changed ? 'bg-red-50 text-red-800' : 'text-[var(--color-text-primary)]',
              ]"
            >
              <span :class="field.field === 'sku' || field.field === 'ean' ? 'font-mono' : ''">
                {{ formatValue(field, field.old_value) }}
              </span>
            </td>
            <td
              :class="[
                'px-3 py-2',
                field.changed ? 'bg-green-50 text-green-800' : 'text-[var(--color-text-primary)]',
              ]"
            >
              <span :class="field.field === 'sku' || field.field === 'ean' ? 'font-mono' : ''">
                {{ formatValue(field, field.new_value) }}
              </span>
            </td>
          </tr>

          <!-- Attribute fields separator -->
          <tr v-if="attributeFields.length > 0" class="border-t border-[var(--color-border)]">
            <td colspan="3" class="px-3 py-1.5 bg-[var(--color-bg)] text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider">
              Attributwerte
            </td>
          </tr>

          <!-- Attribute fields -->
          <tr
            v-for="field in attributeFields"
            :key="field.field"
            :class="[
              'border-t border-[var(--color-border)]',
              field.changed ? '' : 'opacity-60',
            ]"
          >
            <td class="px-3 py-2 font-medium text-[var(--color-text-secondary)]">
              {{ getFieldLabel(field) }}
            </td>
            <td
              :class="[
                'px-3 py-2',
                field.changed ? 'bg-red-50 text-red-800' : 'text-[var(--color-text-primary)]',
              ]"
            >
              {{ formatValue(field, field.old_value) }}
            </td>
            <td
              :class="[
                'px-3 py-2',
                field.changed ? 'bg-green-50 text-green-800' : 'text-[var(--color-text-primary)]',
              ]"
            >
              {{ formatValue(field, field.new_value) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
