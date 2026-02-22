<script setup>
import { ref, computed, watch } from 'vue'
import { ArrowUp, ArrowDown, ArrowUpDown, MoreHorizontal } from 'lucide-vue-next'

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  sortField: { type: String, default: '' },
  sortOrder: { type: String, default: 'asc' },
  selectable: { type: Boolean, default: false },
  rowKey: { type: String, default: 'id' },
  stickyHeader: { type: Boolean, default: true },
  emptyText: { type: String, default: 'Keine Einträge' },
  onRowClick: { type: Function, default: null },
})

const emit = defineEmits(['sort', 'select', 'row-click', 'row-action'])

const selectedIds = ref(new Set())

const allSelected = computed(() =>
  props.rows.length > 0 && props.rows.every(r => selectedIds.value.has(r[props.rowKey]))
)

function toggleAll() {
  if (allSelected.value) {
    selectedIds.value.clear()
  } else {
    props.rows.forEach(r => selectedIds.value.add(r[props.rowKey]))
  }
  emit('select', [...selectedIds.value])
}

function toggleRow(row) {
  const id = row[props.rowKey]
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
  emit('select', [...selectedIds.value])
}

function handleSort(col) {
  if (!col.sortable) return
  const newOrder = props.sortField === col.key && props.sortOrder === 'asc' ? 'desc' : 'asc'
  emit('sort', col.key, newOrder)
}

function handleRowClick(row) {
  if (props.onRowClick) props.onRowClick(row)
  emit('row-click', row)
}

function getCellValue(row, col) {
  if (col.render) return col.render(row)
  const keys = col.key.split('.')
  let val = row
  for (const k of keys) val = val?.[k]
  return val ?? '—'
}
</script>

<template>
  <div class="pim-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <thead :class="stickyHeader ? 'sticky top-0 z-10' : ''">
          <tr class="bg-[var(--color-bg)] border-b border-[var(--color-border)]">
            <!-- Select all -->
            <th v-if="selectable" class="w-10 px-3 py-2.5">
              <input
                type="checkbox"
                :checked="allSelected"
                @change="toggleAll"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
            </th>
            <!-- Columns -->
            <th
              v-for="col in columns"
              :key="col.key"
              :class="[
                'px-3 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-[var(--color-text-tertiary)]',
                col.sortable ? 'cursor-pointer select-none hover:text-[var(--color-text-secondary)]' : '',
                col.width ? `w-[${col.width}]` : '',
                col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '',
              ]"
              @click="handleSort(col)"
            >
              <div class="flex items-center gap-1" :class="col.align === 'right' ? 'justify-end' : ''">
                <span>{{ col.label }}</span>
                <template v-if="col.sortable">
                  <ArrowUp v-if="sortField === col.key && sortOrder === 'asc'" class="w-3 h-3 text-[var(--color-accent)]" />
                  <ArrowDown v-else-if="sortField === col.key && sortOrder === 'desc'" class="w-3 h-3 text-[var(--color-accent)]" />
                  <ArrowUpDown v-else class="w-3 h-3 opacity-30" />
                </template>
              </div>
            </th>
            <!-- Actions column -->
            <th class="w-10"></th>
          </tr>
        </thead>

        <tbody>
          <!-- Loading skeleton -->
          <template v-if="loading">
            <tr v-for="i in 8" :key="'skel-' + i" class="border-b border-[var(--color-border)]">
              <td v-if="selectable" class="px-3 py-3"><div class="pim-skeleton h-4 w-4 rounded" /></td>
              <td v-for="col in columns" :key="col.key" class="px-3 py-3">
                <div class="pim-skeleton h-4 rounded" :style="{ width: (40 + Math.random() * 60) + '%' }" />
              </td>
              <td class="px-3 py-3"><div class="pim-skeleton h-4 w-4 rounded" /></td>
            </tr>
          </template>

          <!-- Empty state -->
          <tr v-else-if="rows.length === 0">
            <td :colspan="columns.length + (selectable ? 2 : 1)" class="py-16 text-center">
              <p class="text-sm text-[var(--color-text-tertiary)]">{{ emptyText }}</p>
            </td>
          </tr>

          <!-- Rows -->
          <tr
            v-else
            v-for="(row, index) in rows"
            :key="row[rowKey] ?? index"
            class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors cursor-pointer group"
            @click="handleRowClick(row)"
          >
            <td v-if="selectable" class="px-3 py-2.5" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.has(row[rowKey])"
                @change="toggleRow(row)"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
            </td>
            <td
              v-for="col in columns"
              :key="col.key"
              :class="[
                'px-3 py-2.5',
                col.mono ? 'font-mono text-xs text-[var(--color-text-secondary)]' : '',
                col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '',
              ]"
            >
              <slot :name="'cell-' + col.key" :row="row" :value="getCellValue(row, col)">
                {{ getCellValue(row, col) }}
              </slot>
            </td>
            <td class="px-2 py-2.5" @click.stop>
              <button
                class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-[var(--color-border)] transition-all"
                @click="$emit('row-action', row)"
              >
                <MoreHorizontal class="w-4 h-4 text-[var(--color-text-tertiary)]" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination slot -->
    <slot name="pagination" />
  </div>
</template>
