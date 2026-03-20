<script setup>
defineOptions({ name: 'CatalogTemplateDesignerView' })

import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import {
  ArrowLeft, Save, Eye, Code, Settings, Maximize2, Minimize2,
  Monitor, Tablet, Smartphone, RefreshCw, Globe, EyeOff, Copy,
  Search, LayoutGrid, Package, FolderTree, SlidersHorizontal, Filter,
  Wrench, ChevronsLeftRight, Heart, Columns, Languages,
  PanelTop, PanelLeft, PanelBottom, Rows3, Type, Image,
  Puzzle, Undo2, Redo2, ClipboardCopy, ClipboardPaste,
} from 'lucide-vue-next'
import catalogTemplatesApi from '@/api/catalogTemplates'

const route = useRoute()
const router = useRouter()

const template = ref(null)
const htmlCode = ref('')
const saving = ref(false)
const error = ref('')
const loadError = ref(false)
const isDirty = ref(false)
const activeTab = ref('editor') // editor | settings
const previewDevice = ref('desktop') // desktop | tablet | mobile
const editorFullscreen = ref(false)
const showCssVars = ref(false)

// Settings form
const settingsForm = ref({
  name: '',
  description: '',
  slug: '',
  is_active: true,
  is_shared: true,
})

// CSS variable editor
const cssVars = ref({})
const defaultCssVars = {
  '--pxc-primary': '#2563eb',
  '--pxc-primary-text': '#ffffff',
  '--pxc-accent': '#dc2626',
  '--pxc-bg': '#ffffff',
  '--pxc-surface': '#f8fafc',
  '--pxc-border': '#e2e8f0',
  '--pxc-text': '#1e293b',
  '--pxc-text-muted': '#94a3b8',
  '--pxc-radius': '8px',
  '--pxc-font': "'Segoe UI', system-ui, sans-serif",
}

const cssVarLabels = {
  '--pxc-primary': 'Primärfarbe',
  '--pxc-primary-text': 'Primärtext',
  '--pxc-accent': 'Akzentfarbe',
  '--pxc-bg': 'Hintergrund',
  '--pxc-surface': 'Oberfläche',
  '--pxc-border': 'Rahmenfarbe',
  '--pxc-text': 'Textfarbe',
  '--pxc-text-muted': 'Gedämpfter Text',
  '--pxc-radius': 'Eckenradius',
  '--pxc-font': 'Schriftart',
}

const previewWidth = computed(() => {
  switch (previewDevice.value) {
    case 'tablet': return '768px'
    case 'mobile': return '375px'
    default: return '100%'
  }
})

onMounted(async () => {
  const id = route.params.id
  try {
    const { data } = await catalogTemplatesApi.get(id)
    const tmpl = data.data || data
    template.value = tmpl
    htmlCode.value = tmpl.html_template || ''
    cssVars.value = { ...defaultCssVars, ...(tmpl.css_variables || {}) }
    settingsForm.value = {
      name: tmpl.name,
      description: tmpl.description || '',
      slug: tmpl.slug,
      is_active: tmpl.is_active,
      is_shared: tmpl.is_shared,
    }
  } catch (e) {
    loadError.value = true
    error.value = 'Vorlage konnte nicht geladen werden.'
  }
})

watch(htmlCode, () => { isDirty.value = true })

// Unsaved changes guard
function beforeUnload(e) {
  if (isDirty.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}
window.addEventListener('beforeunload', beforeUnload)
onBeforeUnmount(() => { window.removeEventListener('beforeunload', beforeUnload) })
onBeforeRouteLeave(() => {
  if (isDirty.value && !confirm('Es gibt ungespeicherte Änderungen. Seite wirklich verlassen?')) {
    return false
  }
})

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      html_template: htmlCode.value,
      css_variables: cssVars.value,
      name: settingsForm.value.name,
      description: settingsForm.value.description || null,
      slug: settingsForm.value.slug,
      is_active: settingsForm.value.is_active,
      is_shared: settingsForm.value.is_shared,
    }
    const { data } = await catalogTemplatesApi.update(template.value.id, payload)
    template.value = data.data || data
    isDirty.value = false
  } catch (e) {
    const msg = e.response?.data?.message || e.response?.data?.errors?.slug?.[0]
    error.value = msg || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

async function openPreview() {
  if (isDirty.value) await save()
  try {
    const { data } = await catalogTemplatesApi.preview(template.value.id)
    const url = data.data?.preview_url
    if (url) window.open(url, '_blank')
  } catch (e) {
    error.value = 'Vorschau konnte nicht geöffnet werden'
  }
}

function refreshPreview() {
  const iframe = document.getElementById('template-preview-iframe')
  if (iframe) {
    const doc = iframe.contentDocument || iframe.contentWindow?.document
    if (doc) {
      doc.open()
      doc.write(htmlCode.value)
      doc.close()
    }
  }
}

watch(htmlCode, () => {
  nextTick(refreshPreview)
})

// Keyboard shortcut: Ctrl+S
function onKeyDown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault()
    save()
  }
}
window.addEventListener('keydown', onKeyDown)
onBeforeUnmount(() => { window.removeEventListener('keydown', onKeyDown) })

// ─── Editor enhancements ────────────────────────────
const editorLine = ref(1)
const editorCol = ref(1)
const lineCount = computed(() => (htmlCode.value.match(/\n/g) || []).length + 1)
const lineNumbers = computed(() => Array.from({ length: lineCount.value }, (_, i) => i + 1))

function updateCursorPosition(e) {
  const el = e?.target || document.getElementById('html-editor-textarea')
  if (!el) return
  const pos = el.selectionStart
  const textBefore = el.value.substring(0, pos)
  editorLine.value = (textBefore.match(/\n/g) || []).length + 1
  editorCol.value = pos - textBefore.lastIndexOf('\n')
}

function syncLineScroll(e) {
  const lineEl = document.getElementById('line-number-gutter')
  if (lineEl) lineEl.scrollTop = e.target.scrollTop
}

async function copySelection() {
  const el = document.getElementById('html-editor-textarea')
  if (!el) return
  const text = el.value.substring(el.selectionStart, el.selectionEnd) || el.value
  await navigator.clipboard.writeText(text)
}

async function pasteAtCursor() {
  const el = document.getElementById('html-editor-textarea')
  if (!el) return
  const text = await navigator.clipboard.readText()
  const pos = el.selectionStart
  const end = el.selectionEnd
  htmlCode.value = el.value.substring(0, pos) + text + el.value.substring(end)
  nextTick(() => {
    el.selectionStart = el.selectionEnd = pos + text.length
    el.focus()
  })
}

function editorUndo() {
  document.getElementById('html-editor-textarea')?.focus()
  document.execCommand('undo')
}

function editorRedo() {
  document.getElementById('html-editor-textarea')?.focus()
  document.execCommand('redo')
}

// Tab and Enter handling in textarea
function onEditorKeyDown(e) {
  if (e.key === 'Tab') {
    e.preventDefault()
    const el = e.target
    const start = el.selectionStart
    const end = el.selectionEnd
    const value = el.value
    el.value = value.substring(0, start) + '  ' + value.substring(end)
    el.selectionStart = el.selectionEnd = start + 2
    htmlCode.value = el.value
  }
  nextTick(() => updateCursorPosition(e))
}

function isColorVar(key) {
  return key !== '--pxc-radius' && key !== '--pxc-font'
}

function applyCssVarsToPreview() {
  isDirty.value = true
  // Inject CSS variables into the HTML
  let html = htmlCode.value
  const rootPattern = /:root\s*\{[^}]*\}/
  const vars = Object.entries(cssVars.value)
    .map(([k, v]) => `      ${k}: ${v};`)
    .join('\n')
  const rootBlock = `:root {\n${vars}\n    }`

  if (rootPattern.test(html)) {
    html = html.replace(rootPattern, rootBlock)
  } else {
    // Insert :root block into <style>
    html = html.replace('<style>', `<style>\n    ${rootBlock}\n`)
  }
  htmlCode.value = html
}

// ─── Widget Palette ─────────────────────────────────
const showPalette = ref(true)

const widgetItems = [
  { tag: 'search', label: 'Suche', icon: Search, snippet: '<div data-catalog="search"></div>' },
  { tag: 'product-grid', label: 'Produktraster', icon: LayoutGrid, snippet: '<div data-catalog="product-grid"></div>' },
  { tag: 'product-detail', label: 'Produktdetail', icon: Package, snippet: '<div data-catalog="product-detail"></div>' },
  { tag: 'categories', label: 'Kategorien', icon: FolderTree, snippet: '<div data-catalog="categories"></div>' },
  { tag: 'facets', label: 'Facetten', icon: SlidersHorizontal, snippet: '<div data-catalog="facets"></div>' },
  { tag: 'active-filters', label: 'Akt. Filter', icon: Filter, snippet: '<div data-catalog="active-filters"></div>' },
  { tag: 'toolbar', label: 'Toolbar', icon: Wrench, snippet: '<div data-catalog="toolbar"></div>' },
  { tag: 'pagination', label: 'Seiten', icon: ChevronsLeftRight, snippet: '<div data-catalog="pagination"></div>' },
  { tag: 'wishlist', label: 'Merkliste', icon: Heart, snippet: '<div data-catalog="wishlist"></div>' },
  { tag: 'compare', label: 'Vergleich', icon: Columns, snippet: '<div data-catalog="compare"></div>' },
  { tag: 'locale', label: 'Sprache', icon: Languages, snippet: '<div data-catalog="locale"></div>' },
]

const layoutItems = [
  { tag: 'header', label: 'Header', icon: PanelTop, snippet: '<header style="background:#2563eb;color:white;padding:16px 24px;display:flex;align-items:center;justify-content:space-between">\n  <h1 style="margin:0;font-size:1.25rem">Katalog</h1>\n  <div style="display:flex;gap:12px;align-items:center">\n    <div data-catalog="search"></div>\n    <div data-catalog="wishlist"></div>\n  </div>\n</header>' },
  { tag: 'sidebar-layout', label: 'Sidebar + Main', icon: PanelLeft, snippet: '<div style="display:grid;grid-template-columns:280px 1fr;max-width:1400px;margin:0 auto">\n  <aside style="padding:16px;background:white;min-height:calc(100vh - 60px)">\n    <div data-catalog="categories"></div>\n    <div data-catalog="facets"></div>\n  </aside>\n  <main style="padding:24px">\n    <div data-catalog="toolbar"></div>\n    <div data-catalog="active-filters"></div>\n    <div data-catalog="product-grid"></div>\n    <div data-catalog="pagination"></div>\n  </main>\n</div>' },
  { tag: 'footer', label: 'Footer', icon: PanelBottom, snippet: '<footer style="background:#1e293b;color:#94a3b8;padding:24px;text-align:center;font-size:0.85rem">\n  &copy; 2026 Firma — Alle Rechte vorbehalten\n</footer>' },
  { tag: 'section', label: 'Abschnitt', icon: Rows3, snippet: '<section style="max-width:1200px;margin:0 auto;padding:32px 24px">\n  \n</section>' },
  { tag: 'heading', label: 'Überschrift', icon: Type, snippet: '<h2 style="font-size:1.5rem;font-weight:600;margin:0 0 16px">Überschrift</h2>' },
  { tag: 'hero', label: 'Hero-Banner', icon: Image, snippet: '<div style="background:linear-gradient(135deg,#2563eb,#7c3aed);color:white;padding:64px 24px;text-align:center">\n  <h1 style="font-size:2rem;margin:0 0 8px">Produktkatalog</h1>\n  <p style="font-size:1.1rem;opacity:0.9;margin:0">Entdecken Sie unser Sortiment</p>\n</div>' },
]

let dragSnippet = ''

function onWidgetDragStart(e, snippet) {
  dragSnippet = snippet
  e.dataTransfer.setData('text/plain', snippet)
  e.dataTransfer.effectAllowed = 'copy'
}

function onEditorDrop(e) {
  e.preventDefault()
  const textarea = e.target
  if (textarea.tagName !== 'TEXTAREA') return

  const snippet = e.dataTransfer.getData('text/plain') || dragSnippet
  if (!snippet) return

  // Get drop position from mouse coordinates
  const pos = getCaretPositionFromPoint(textarea, e.clientX, e.clientY)
  insertSnippetAtPosition(textarea, snippet, pos)
}

function onEditorDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'copy'
}

function insertWidgetAtCursor(snippet) {
  const textarea = document.getElementById('html-editor-textarea')
  if (!textarea) return
  const pos = textarea.selectionStart ?? textarea.value.length
  insertSnippetAtPosition(textarea, snippet, pos)
  textarea.focus()
}

function insertSnippetAtPosition(textarea, snippet, pos) {
  const before = textarea.value.substring(0, pos)
  const after = textarea.value.substring(pos)

  // Determine indentation of current line
  const lineStart = before.lastIndexOf('\n') + 1
  const currentLine = before.substring(lineStart)
  const indent = currentLine.match(/^(\s*)/)?.[1] || ''

  // Indent the snippet lines to match
  const indented = snippet.split('\n').map((line, i) => i === 0 ? line : indent + line).join('\n')

  const needsNewlineBefore = before.length > 0 && !before.endsWith('\n') ? '\n' + indent : ''
  const needsNewlineAfter = after.length > 0 && !after.startsWith('\n') ? '\n' : ''

  htmlCode.value = before + needsNewlineBefore + indented + needsNewlineAfter + after
  nextTick(() => {
    const newPos = before.length + needsNewlineBefore.length + indented.length
    textarea.selectionStart = textarea.selectionEnd = newPos
  })
}

function getCaretPositionFromPoint(textarea, x, y) {
  // Approximate caret position from coordinates
  // Since browsers don't expose this directly for textareas, use selectionStart as fallback
  textarea.focus()
  if (document.caretPositionFromPoint) {
    const range = document.caretPositionFromPoint(x, y)
    if (range) return range.offset
  }
  return textarea.selectionStart ?? textarea.value.length
}
</script>

<template>
  <div class="h-[calc(100vh-3.5rem)] flex flex-col" :class="{ 'fixed inset-0 z-50 bg-[var(--color-surface)]': editorFullscreen }">
    <!-- Toolbar -->
    <div class="flex items-center gap-3 px-4 py-2 border-b border-[var(--color-border)] bg-[var(--color-surface)] shrink-0">
      <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="router.push('/catalog-templates')" title="Zurück">
        <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <div class="flex-1 min-w-0">
        <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">
          {{ template?.name || 'Katalog-Vorlage' }}
        </span>
        <span v-if="isDirty" class="text-[10px] text-[var(--color-warning)] ml-2">• Ungespeichert</span>
      </div>

      <!-- Tab toggle -->
      <div class="flex items-center bg-[var(--color-bg)] rounded-md p-0.5 border border-[var(--color-border)]">
        <button
          :class="['text-[11px] px-3 py-1 rounded transition-colors', activeTab === 'editor' ? 'bg-[var(--color-surface)] text-[var(--color-text-primary)] font-medium shadow-sm' : 'text-[var(--color-text-tertiary)]']"
          @click="activeTab = 'editor'"
        >
          <Code class="w-3 h-3 inline mr-1" :stroke-width="2" />
          Editor
        </button>
        <button
          :class="['text-[11px] px-3 py-1 rounded transition-colors', activeTab === 'settings' ? 'bg-[var(--color-surface)] text-[var(--color-text-primary)] font-medium shadow-sm' : 'text-[var(--color-text-tertiary)]']"
          @click="activeTab = 'settings'"
        >
          <Settings class="w-3 h-3 inline mr-1" :stroke-width="2" />
          Einstellungen
        </button>
      </div>

      <!-- Device preview toggle -->
      <div v-if="activeTab === 'editor'" class="flex items-center gap-1 border-l border-[var(--color-border)] pl-3">
        <button
          v-for="device in [{key:'desktop',icon:Monitor},{key:'tablet',icon:Tablet},{key:'mobile',icon:Smartphone}]"
          :key="device.key"
          :class="['p-1 rounded transition-colors', previewDevice === device.key ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : 'text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]']"
          @click="previewDevice = device.key"
          :title="device.key"
        >
          <component :is="device.icon" class="w-3.5 h-3.5" :stroke-width="1.75" />
        </button>
      </div>

      <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="refreshPreview" title="Vorschau aktualisieren">
        <RefreshCw class="w-3 h-3" :stroke-width="2" />
      </button>

      <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="editorFullscreen = !editorFullscreen" :title="editorFullscreen ? 'Vollbild beenden' : 'Vollbild'">
        <Minimize2 v-if="editorFullscreen" class="w-3 h-3" :stroke-width="2" />
        <Maximize2 v-else class="w-3 h-3" :stroke-width="2" />
      </button>

      <button class="pim-btn pim-btn-secondary text-xs" @click="openPreview">
        <Eye class="w-3.5 h-3.5" :stroke-width="2" />
        Vorschau
      </button>

      <button
        class="pim-btn pim-btn-primary text-xs"
        @click="save"
        :disabled="saving"
      >
        <Save class="w-3.5 h-3.5" :stroke-width="2" />
        {{ saving ? 'Speichern...' : 'Speichern' }}
      </button>
    </div>

    <!-- Error banner -->
    <div v-if="error" class="px-4 py-2 bg-[var(--color-error-light)] text-[var(--color-error)] text-xs flex items-center gap-2 shrink-0">
      {{ error }}
      <button class="underline" @click="error = ''">OK</button>
    </div>

    <!-- Load error -->
    <div v-if="loadError" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <p class="text-sm text-[var(--color-text-secondary)]">Vorlage konnte nicht geladen werden.</p>
        <button class="pim-btn pim-btn-secondary text-xs mt-3" @click="router.push('/catalog-templates')">Zurück zur Übersicht</button>
      </div>
    </div>

    <!-- Editor tab: Palette + Code + Preview -->
    <div v-else-if="activeTab === 'editor'" class="flex-1 flex overflow-hidden">

      <!-- Widget Palette (left strip) -->
      <div
        class="shrink-0 border-r border-[var(--color-border)] bg-[var(--color-surface)] flex flex-col overflow-y-auto transition-all"
        :style="{ width: showPalette ? '156px' : '36px' }"
      >
        <!-- Palette toggle -->
        <button
          class="w-full px-2 py-1.5 text-[10px] font-semibold text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] transition-colors flex items-center gap-1 border-b border-[var(--color-border)] shrink-0"
          @click="showPalette = !showPalette"
          :title="showPalette ? 'Palette einklappen' : 'Palette aufklappen'"
        >
          <Puzzle class="w-3.5 h-3.5 shrink-0" :stroke-width="1.75" />
          <span v-if="showPalette">Widgets</span>
        </button>

        <template v-if="showPalette">
          <!-- Catalog Widgets -->
          <div class="px-2 pt-2 pb-1">
            <div class="text-[9px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider mb-1.5">Katalog</div>
            <div class="grid grid-cols-2 gap-1">
              <button
                v-for="w in widgetItems"
                :key="w.tag"
                draggable="true"
                @dragstart="onWidgetDragStart($event, w.snippet)"
                @dblclick="insertWidgetAtCursor(w.snippet)"
                class="flex flex-col items-center gap-0.5 p-1.5 rounded border border-transparent hover:border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-grab active:cursor-grabbing transition-colors group"
                :title="`${w.label} — Doppelklick oder Drag&amp;Drop`"
              >
                <component :is="w.icon" class="w-4 h-4 text-[var(--color-accent)] group-hover:scale-110 transition-transform" :stroke-width="1.75" />
                <span class="text-[8px] text-[var(--color-text-tertiary)] leading-tight text-center">{{ w.label }}</span>
              </button>
            </div>
          </div>

          <!-- Layout Blocks -->
          <div class="px-2 pt-2 pb-2 border-t border-[var(--color-border)]">
            <div class="text-[9px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider mb-1.5">Layout</div>
            <div class="grid grid-cols-2 gap-1">
              <button
                v-for="w in layoutItems"
                :key="w.tag"
                draggable="true"
                @dragstart="onWidgetDragStart($event, w.snippet)"
                @dblclick="insertWidgetAtCursor(w.snippet)"
                class="flex flex-col items-center gap-0.5 p-1.5 rounded border border-transparent hover:border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-grab active:cursor-grabbing transition-colors group"
                :title="`${w.label} — Doppelklick oder Drag&amp;Drop`"
              >
                <component :is="w.icon" class="w-4 h-4 text-[var(--color-text-secondary)] group-hover:scale-110 transition-transform" :stroke-width="1.75" />
                <span class="text-[8px] text-[var(--color-text-tertiary)] leading-tight text-center">{{ w.label }}</span>
              </button>
            </div>
          </div>
        </template>

        <!-- Collapsed: icon-only strip -->
        <template v-else>
          <div class="flex flex-col items-center gap-0.5 py-2">
            <button
              v-for="w in widgetItems"
              :key="w.tag"
              draggable="true"
              @dragstart="onWidgetDragStart($event, w.snippet)"
              @dblclick="insertWidgetAtCursor(w.snippet)"
              class="p-1 rounded hover:bg-[var(--color-bg)] cursor-grab active:cursor-grabbing transition-colors"
              :title="w.label"
            >
              <component :is="w.icon" class="w-3.5 h-3.5 text-[var(--color-accent)]" :stroke-width="1.75" />
            </button>
            <div class="w-4 border-t border-[var(--color-border)] my-1" />
            <button
              v-for="w in layoutItems"
              :key="w.tag"
              draggable="true"
              @dragstart="onWidgetDragStart($event, w.snippet)"
              @dblclick="insertWidgetAtCursor(w.snippet)"
              class="p-1 rounded hover:bg-[var(--color-bg)] cursor-grab active:cursor-grabbing transition-colors"
              :title="w.label"
            >
              <component :is="w.icon" class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="1.75" />
            </button>
          </div>
        </template>
      </div>

      <!-- Code editor (center) -->
      <div class="flex-1 flex flex-col border-r border-[var(--color-border)] min-w-0">
        <!-- CSS Variables bar -->
        <div class="border-b border-[var(--color-border)]">
          <button
            class="w-full text-left px-3 py-1.5 text-[11px] font-medium text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] transition-colors flex items-center gap-1"
            @click="showCssVars = !showCssVars"
          >
            <span>CSS-Variablen</span>
            <span class="text-[var(--color-text-tertiary)]">{{ showCssVars ? '▲' : '▼' }}</span>
          </button>
          <div v-if="showCssVars" class="px-3 pb-3 space-y-2">
            <div class="grid grid-cols-3 gap-2">
              <div v-for="(value, key) in cssVars" :key="key" class="flex items-center gap-1.5">
                <input
                  v-if="isColorVar(key)"
                  type="color"
                  :value="value"
                  @input="cssVars[key] = $event.target.value"
                  class="w-5 h-5 rounded cursor-pointer border border-[var(--color-border)]"
                  style="padding: 0;"
                />
                <div v-else class="w-5" />
                <div class="flex-1 min-w-0">
                  <div class="text-[9px] text-[var(--color-text-tertiary)] truncate">{{ cssVarLabels[key] || key }}</div>
                  <input
                    v-model="cssVars[key]"
                    class="pim-input text-[10px] w-full py-0 px-1"
                    style="height: 20px;"
                  />
                </div>
              </div>
            </div>
            <button class="pim-btn pim-btn-secondary text-[10px] px-2 py-0.5" @click="applyCssVarsToPreview">
              In HTML übernehmen
            </button>
          </div>
        </div>

        <!-- Editor toolbar -->
        <div class="flex items-center gap-1 px-2 py-1 border-b border-[var(--color-border)] bg-[var(--color-surface)] shrink-0">
          <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors" @click="editorUndo" title="Rückgängig (Ctrl+Z)">
            <Undo2 class="w-3.5 h-3.5" :stroke-width="1.75" />
          </button>
          <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors" @click="editorRedo" title="Wiederholen (Ctrl+Y)">
            <Redo2 class="w-3.5 h-3.5" :stroke-width="1.75" />
          </button>
          <div class="w-px h-4 bg-[var(--color-border)] mx-1" />
          <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors" @click="copySelection" title="Kopieren (Ctrl+C)">
            <ClipboardCopy class="w-3.5 h-3.5" :stroke-width="1.75" />
          </button>
          <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors" @click="pasteAtCursor" title="Einfügen (Ctrl+V)">
            <ClipboardPaste class="w-3.5 h-3.5" :stroke-width="1.75" />
          </button>
          <div class="flex-1" />
          <span class="text-[10px] font-mono text-[var(--color-text-tertiary)]">
            Zeile {{ editorLine }}, Spalte {{ editorCol }} | {{ lineCount }} Zeilen
          </span>
        </div>

        <!-- Textarea with line numbers -->
        <div class="flex-1 flex overflow-hidden">
          <div
            id="line-number-gutter"
            class="shrink-0 py-3 pr-2 text-right select-none overflow-hidden bg-[var(--color-surface)] border-r border-[var(--color-border)]"
            style="width: 44px;"
          >
            <div
              v-for="n in lineNumbers"
              :key="n"
              class="font-mono leading-relaxed px-1"
              :class="n === editorLine ? 'text-[var(--color-accent)] font-medium' : 'text-[var(--color-text-tertiary)]'"
              style="font-size: 12px;"
            >{{ n }}</div>
          </div>
          <textarea
            id="html-editor-textarea"
            v-model="htmlCode"
            class="flex-1 w-full py-3 px-3 font-mono text-[12px] leading-relaxed bg-[var(--color-bg)] text-[var(--color-text-primary)] border-none outline-none resize-none"
            spellcheck="false"
            @keydown="onEditorKeyDown"
            @keyup="updateCursorPosition"
            @click="updateCursorPosition"
            @scroll="syncLineScroll"
            @drop="onEditorDrop"
            @dragover="onEditorDragOver"
          />
        </div>
      </div>

      <!-- Live preview (right) -->
      <div class="w-[45%] shrink-0 flex flex-col bg-[#f0f0f0]">
        <div class="px-3 py-1.5 text-[11px] text-[var(--color-text-tertiary)] border-b border-[var(--color-border)] bg-[var(--color-surface)] shrink-0 flex items-center justify-between">
          <span>Vorschau</span>
          <span class="text-[10px]">{{ previewDevice }} {{ previewDevice !== 'desktop' ? previewWidth : '' }}</span>
        </div>
        <div class="flex-1 overflow-auto flex justify-center p-2">
          <iframe
            id="template-preview-iframe"
            :style="{ width: previewWidth, maxWidth: '100%', height: '100%', border: '1px solid #ddd', background: 'white' }"
            sandbox="allow-scripts allow-same-origin"
          />
        </div>
      </div>
    </div>

    <!-- Settings tab -->
    <div v-else-if="activeTab === 'settings'" class="flex-1 overflow-y-auto p-6">
      <div class="max-w-xl mx-auto space-y-4">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Vorlagen-Einstellungen</h3>

        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
          <input v-model="settingsForm.name" class="pim-input text-xs w-full" @input="isDirty = true" />
        </div>

        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beschreibung</label>
          <input v-model="settingsForm.description" class="pim-input text-xs w-full" @input="isDirty = true" />
        </div>

        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Slug (URL-Pfad)</label>
          <input v-model="settingsForm.slug" class="pim-input text-xs w-full font-mono" @input="isDirty = true" />
          <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
            Erreichbar unter: /catalog-embed/{{ settingsForm.slug }}
          </p>
        </div>

        <div class="flex items-center gap-6">
          <label class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)] cursor-pointer">
            <input type="checkbox" v-model="settingsForm.is_active" @change="isDirty = true" class="rounded" />
            <Globe class="w-3.5 h-3.5" :stroke-width="1.75" />
            Aktiv (öffentlich erreichbar)
          </label>
          <label class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)] cursor-pointer">
            <input type="checkbox" v-model="settingsForm.is_shared" @change="isDirty = true" class="rounded" />
            Für alle Benutzer sichtbar
          </label>
        </div>

        <div class="pt-4 border-t border-[var(--color-border)]">
          <h4 class="text-[12px] font-semibold text-[var(--color-text-secondary)] mb-3">Verfügbare data-catalog Widgets</h4>
          <div class="grid grid-cols-2 gap-2">
            <div v-for="w in widgetItems" :key="w.tag" class="pim-card p-2 flex items-center gap-2">
              <component :is="w.icon" class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="1.75" />
              <div>
                <code class="text-[10px] text-[var(--color-accent)] block">data-catalog="{{ w.tag }}"</code>
                <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ w.label }}</span>
              </div>
            </div>
          </div>
          <p class="text-[10px] text-[var(--color-text-tertiary)] mt-2">
            Tipp: Im Editor-Tab findest du links die Widget-Palette — per Doppelklick oder Drag&amp;Drop einfügen.
          </p>
        </div>

        <div class="pt-4 border-t border-[var(--color-border)]">
          <h4 class="text-[12px] font-semibold text-[var(--color-text-secondary)] mb-2">Init-Optionen</h4>
          <pre class="text-[11px] font-mono bg-[var(--color-bg)] p-3 rounded-md border border-[var(--color-border)] text-[var(--color-text-secondary)]">PublixxCatalog.init({
  api: '/api/v1',        // API-Endpunkt
  locale: 'de',          // Sprache (de, en, ...)
  perPage: 12,           // Produkte pro Seite
  facetsMode: 'drawer',  // drawer | sidebar
})</pre>
        </div>
      </div>
    </div>
  </div>
</template>
