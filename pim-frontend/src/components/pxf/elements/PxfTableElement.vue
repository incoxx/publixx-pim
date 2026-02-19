<script setup>
import { computed } from 'vue'
import { resolveBinding } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
})

const rows = computed(() => {
  const val = resolveBinding(props.data, props.element.bind) || []
  return Array.isArray(val) ? val : []
})

const ts = computed(() => props.element.tableStyle || {})
const s = computed(() => props.element.style || {})

const columns = computed(() => {
  if (rows.value.length === 0) return []
  // For smartTable with PTL
  if (props.element.ptl?.columns) {
    return props.element.ptl.columns.map(c => ({
      key: c.field || c.key,
      label: c.header || c.label || c.field || c.key,
    }))
  }
  // Auto-detect from first row
  return Object.keys(rows.value[0]).map(key => ({ key, label: key }))
})

const containerStyle = computed(() => ({
  fontSize: (s.value.fontSize || 12) + 'px',
  color: s.value.textColor || '#111111',
  padding: (s.value.padding || 0) + 'px',
  width: '100%',
  height: '100%',
  boxSizing: 'border-box',
  overflow: 'hidden',
}))

const borderStyle = computed(() => {
  if (!ts.value.borderVisible) return 'none'
  return `${ts.value.borderWidth || 1}px solid ${ts.value.borderColor || '#e5e7eb'}`
})
</script>

<template>
  <div :style="containerStyle">
    <table class="w-full border-collapse" :style="{ borderSpacing: 0 }">
      <thead v-if="ts.headerRow !== false && columns.length > 0">
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            :style="{
              border: borderStyle,
              padding: '4px 6px',
              textAlign: 'left',
              fontWeight: ts.headerBold !== false ? 700 : 400,
              backgroundColor: ts.headerBg || '#f3f4f6',
              fontSize: 'inherit',
            }"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, ri) in rows"
          :key="ri"
          :style="{
            backgroundColor: ts.alternatingRows && ri % 2 === 1 ? (ts.alternateColor || '#f9fafb') : 'transparent',
          }"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            :style="{
              border: borderStyle,
              padding: '3px 6px',
              fontSize: 'inherit',
            }"
          >
            {{ row[col.key] ?? '' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
