<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  diffData: { type: Object, required: true },
})

const { t } = useI18n()

const fieldLabels = {
  name: 'Name',
  sku: 'SKU',
  ean: 'EAN',
  status: 'Status',
  master_hierarchy_node_id: t('product.version.hierarchyNode'),
}

const statusLabels = {
  draft: t('product.version.draft'),
  active: t('product.version.active'),
  inactive: 'Inaktiv',
  discontinued: 'Auslaufend',
}

const changedCount = computed(() =>
  props.diffData.fields.filter(f => f.changed).length
)

function formatValue(field, value) {
  if (value === null || value === undefined || value === '') return '—'
  if (field === 'status') return statusLabels[value] || value
  return String(value)
}
</script>

<template>
  <div class="space-y-3">
    <!-- Header -->
    <div class="flex items-center justify-between text-xs text-[var(--color-text-tertiary)]">
      <span>
        Version {{ diffData.left.version_number }} → Version {{ diffData.right.version_number }}
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
              Version {{ diffData.left.version_number }}
              <span class="font-normal text-[var(--color-text-tertiary)]">
                ({{ diffData.left.created_at ? new Date(diffData.left.created_at).toLocaleDateString('de-DE') : '—' }})
              </span>
            </th>
            <th class="text-left px-3 py-2 text-[11px] font-semibold text-[var(--color-text-secondary)] w-2/5">
              Version {{ diffData.right.version_number }}
              <span class="font-normal text-[var(--color-text-tertiary)]">
                ({{ diffData.right.created_at ? new Date(diffData.right.created_at).toLocaleDateString('de-DE') : '—' }})
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="field in diffData.fields"
            :key="field.field"
            :class="[
              'border-t border-[var(--color-border)]',
              field.changed ? '' : 'opacity-60',
            ]"
          >
            <td class="px-3 py-2 font-medium text-[var(--color-text-secondary)]">
              {{ fieldLabels[field.field] || field.field }}
            </td>
            <td
              :class="[
                'px-3 py-2',
                field.changed ? 'bg-red-50 text-red-800' : 'text-[var(--color-text-primary)]',
              ]"
            >
              <span :class="field.field === 'sku' || field.field === 'ean' ? 'font-mono' : ''">
                {{ formatValue(field.field, field.old_value) }}
              </span>
            </td>
            <td
              :class="[
                'px-3 py-2',
                field.changed ? 'bg-green-50 text-green-800' : 'text-[var(--color-text-primary)]',
              ]"
            >
              <span :class="field.field === 'sku' || field.field === 'ean' ? 'font-mono' : ''">
                {{ formatValue(field.field, field.new_value) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
