<script setup>
import { ref, computed } from 'vue'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { Copy, Check, Code, Play } from 'lucide-vue-next'

const store = useApiDesignerStore()
const copied = ref(false)
const showMode = ref('schema') // 'schema' | 'query'

const displayText = computed(() => {
  if (!store.schemaPreview) {
    return generateSchemaPreview()
  }
  return showMode.value === 'schema'
    ? store.schemaPreview.sdl
    : store.schemaPreview.sample_query
})

function copyToClipboard() {
  navigator.clipboard.writeText(displayText.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

/**
 * Generiert eine statische Schema-Vorschau aus der Template-Struktur (ohne Backend-Aufruf).
 */
function generateSchemaPreview() {
  const groups = store.templateJson?.groups || []
  if (groups.length === 0) {
    return `type Query {
  generated_at: String
  total: Int
  groups: [Group]
}

type Group {
  count: Int
  products: [Product]
}

type Product {
  sku: String
  name: String
  status: String
}`
  }

  const lines = ['type Query {', '  generated_at: String', '  total: Int', '  groups: [GroupLevel1]', '}', '']
  buildGroupTypePreview(groups, 1, lines)
  return lines.join('\n')
}

function buildGroupTypePreview(groups, level, lines) {
  const g = groups[0] || {}
  lines.push(`type GroupLevel${level} {`)
  lines.push('  field: String')
  lines.push('  value: String')

  const detailElements = g.detail?.elements || []
  const typeName = level === 1 ? 'Product' : `ProductLevel${level}`
  lines.push(`  products: [${typeName}]`)

  const subGroups = g.groups || []
  if (subGroups.length > 0 && level < 3) {
    lines.push(`  groups: [GroupLevel${level + 1}]`)
  }

  lines.push('  count: Int')
  lines.push('}')
  lines.push('')

  // Product type
  lines.push(`type ${typeName} {`)
  if (detailElements.length === 0) {
    lines.push('  sku: String')
    lines.push('  name: String')
    lines.push('  status: String')
  } else {
    detailElements.forEach(el => {
      const key = sanitize(el.jsonKey || el.field || el.attributeId || 'field')
      const gqlType = mapType(el.dataType || 'string')
      lines.push(`  ${key}: ${gqlType}`)
    })
  }
  lines.push('}')

  if (subGroups.length > 0 && level < 3) {
    lines.push('')
    buildGroupTypePreview(subGroups, level + 1, lines)
  }
}

function sanitize(name) {
  let s = name.replace(/[^a-zA-Z0-9_]/g, '_')
  if (/^[0-9]/.test(s)) s = '_' + s
  return s || '_field'
}

function mapType(dt) {
  return { number: 'Float', integer: 'Int', boolean: 'Boolean' }[dt] || 'String'
}
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)]">
      <span class="text-xs font-semibold text-[var(--color-text-primary)]">GraphQL-Vorschau</span>
      <div class="flex items-center gap-1.5">
        <!-- Toggle: Schema / Query -->
        <div class="flex rounded overflow-hidden border border-[var(--color-border)]">
          <button
            class="px-2 py-0.5 text-[10px] transition-colors"
            :class="showMode === 'schema' ? 'bg-purple-600 text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
            @click="showMode = 'schema'"
            title="Schema (SDL)"
          >
            <Code class="w-3 h-3 inline" :stroke-width="2" />
          </button>
          <button
            class="px-2 py-0.5 text-[10px] transition-colors"
            :class="showMode === 'query' ? 'bg-purple-600 text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'"
            @click="showMode = 'query'"
            title="Beispiel-Query"
          >
            <Play class="w-3 h-3 inline" :stroke-width="2" />
          </button>
        </div>

        <button
          class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]"
          @click="copyToClipboard"
          :title="copied ? 'Kopiert!' : 'Kopieren'"
        >
          <Check v-if="copied" class="w-3.5 h-3.5 text-green-500" :stroke-width="2" />
          <Copy v-else class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Info wenn kein Schema geladen -->
    <div v-if="showMode === 'query' && !store.schemaPreview" class="px-3 py-4 text-center text-[11px] text-[var(--color-text-tertiary)]">
      Klicke "Vorschau" in der Toolbar, um das Schema vom Server zu laden.
    </div>

    <!-- Schema/Query Content -->
    <pre
      v-else
      class="flex-1 overflow-auto p-3 text-[11px] font-mono text-[var(--color-text-secondary)] leading-relaxed whitespace-pre"
    >{{ displayText }}</pre>
  </div>
</template>
