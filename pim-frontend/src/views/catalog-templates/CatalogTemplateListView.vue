<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Plus, Pencil, Trash2, Copy, ExternalLink, Globe, Eye, EyeOff,
  Layout, Download,
} from 'lucide-vue-next'
import catalogTemplatesApi from '@/api/catalogTemplates'

const router = useRouter()

const templates = ref([])
const presets = ref([])
const loading = ref(false)
const error = ref('')
const showCreate = ref(false)
const showPresetPicker = ref(false)
const newName = ref('')
const newDescription = ref('')

onMounted(loadTemplates)

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await catalogTemplatesApi.list()
    templates.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der Katalog-Vorlagen'
  } finally {
    loading.value = false
  }
}

async function loadPresets() {
  try {
    const { data } = await catalogTemplatesApi.presets()
    presets.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der Vorlagen-Presets'
  }
}

async function createTemplate() {
  if (!newName.value.trim()) return
  try {
    const defaultHtml = `<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${newName.value.trim()} — Produktkatalog</title>
  <style>
    :root {
      --pxc-primary: #2563eb;
      --pxc-primary-text: #ffffff;
      --pxc-accent: #dc2626;
      --pxc-font: 'Segoe UI', system-ui, sans-serif;
      --pxc-radius: 8px;
    }
    body { margin: 0; font-family: var(--pxc-font); background: #f5f5f5; }
    .page-header {
      background: var(--pxc-primary); color: white;
      padding: 16px 24px; display: flex; align-items: center; justify-content: space-between;
    }
    .page-header h1 { margin: 0; font-size: 1.25rem; }
    .layout {
      display: grid; grid-template-columns: 280px 1fr;
      max-width: 1400px; margin: 0 auto;
    }
    .sidebar { padding: 16px; background: white; min-height: calc(100vh - 60px); }
    .main { padding: 24px; }
    @media (max-width: 768px) {
      .layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
    }
  </style>
</head>
<body>
  <header class="page-header">
    <h1>${newName.value.trim()}</h1>
    <div style="display:flex;gap:12px;align-items:center">
      <div data-catalog="search"></div>
      <div data-catalog="wishlist"></div>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <div data-catalog="categories"></div>
      <div data-catalog="facets"></div>
    </aside>
    <main class="main">
      <div data-catalog="toolbar"></div>
      <div data-catalog="active-filters"></div>
      <div data-catalog="product-grid"></div>
      <div data-catalog="pagination"></div>
    </main>
  </div>

  <div data-catalog="product-detail"></div>
  <div data-catalog="compare"></div>

  <script src="../dist/catalog-embed.umd.js"><\/script>
  <script>
    PublixxCatalog.init({
      api: 'http://localhost:8000/api/v1',
      locale: 'de',
      perPage: 12,
    })
  <\/script>
</body>
</html>`

    const { data } = await catalogTemplatesApi.create({
      name: newName.value.trim(),
      description: newDescription.value.trim() || null,
      html_template: defaultHtml,
    })
    const tmpl = data.data || data
    router.push(`/catalog-templates/${tmpl.id}`)
  } catch (e) {
    error.value = 'Fehler beim Erstellen'
  }
}

async function createFromPreset(preset) {
  try {
    const { data } = await catalogTemplatesApi.create({
      name: preset.name,
      description: `Erstellt aus Preset: ${preset.name}`,
      html_template: preset.html,
    })
    const tmpl = data.data || data
    showPresetPicker.value = false
    router.push(`/catalog-templates/${tmpl.id}`)
  } catch (e) {
    error.value = 'Fehler beim Erstellen aus Preset'
  }
}

async function deleteTemplate(id) {
  if (!confirm('Katalog-Vorlage wirklich löschen?')) return
  try {
    await catalogTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

async function duplicateTemplate(tmpl) {
  try {
    const { data } = await catalogTemplatesApi.duplicate(tmpl.id)
    const newTmpl = data.data || data
    templates.value.push(newTmpl)
  } catch (e) {
    error.value = 'Fehler beim Duplizieren'
  }
}

async function openPreview(tmpl) {
  try {
    const { data } = await catalogTemplatesApi.preview(tmpl.id)
    const url = data.data?.preview_url
    if (url) window.open(url, '_blank')
  } catch (e) {
    error.value = 'Fehler beim Erstellen der Vorschau'
  }
}

async function toggleActive(tmpl) {
  try {
    await catalogTemplatesApi.update(tmpl.id, { is_active: !tmpl.is_active })
    tmpl.is_active = !tmpl.is_active
  } catch (e) {
    error.value = 'Fehler beim Aktualisieren'
  }
}

function openPresetPicker() {
  showPresetPicker.value = true
  if (!presets.value.length) loadPresets()
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Katalog-Vorlagen</h2>
      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-secondary text-xs" @click="openPresetPicker">
          <Download class="w-3.5 h-3.5" :stroke-width="2" />
          Aus Preset
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="showCreate = !showCreate">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          Neue Vorlage
        </button>
      </div>
    </div>

    <!-- Create form -->
    <div v-if="showCreate" class="pim-card p-4 space-y-3">
      <div class="flex items-end gap-3">
        <div class="flex-1">
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Vorlagen-Name</label>
          <input
            v-model="newName"
            class="pim-input text-xs w-full"
            placeholder="z.B. Webshop Herbst 2026"
            @keyup.enter="createTemplate"
            autofocus
          />
        </div>
        <div class="flex-1">
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beschreibung (optional)</label>
          <input
            v-model="newDescription"
            class="pim-input text-xs w-full"
            placeholder="Kurze Beschreibung..."
          />
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-primary text-xs" @click="createTemplate" :disabled="!newName.trim()">
          Erstellen & Bearbeiten
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="showCreate = false; newName = ''; newDescription = ''">
          Abbrechen
        </button>
      </div>
    </div>

    <!-- Preset picker modal -->
    <div v-if="showPresetPicker" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showPresetPicker = false">
      <div class="pim-card p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">Preset auswählen</h3>
        <div v-if="!presets.length" class="text-center text-sm text-[var(--color-text-tertiary)] py-8">Lade Presets...</div>
        <div v-else class="grid grid-cols-2 gap-3">
          <button
            v-for="preset in presets"
            :key="preset.slug"
            class="pim-card p-4 text-left hover:bg-[var(--color-bg)] transition-colors cursor-pointer border border-[var(--color-border)]"
            @click="createFromPreset(preset)"
          >
            <div class="text-sm font-medium text-[var(--color-text-primary)]">{{ preset.name }}</div>
            <div class="text-[11px] text-[var(--color-text-tertiary)] mt-1">{{ preset.slug }}</div>
          </button>
        </div>
        <div class="mt-4 flex justify-end">
          <button class="pim-btn pim-btn-secondary text-xs" @click="showPresetPicker = false">Schließen</button>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">
      {{ error }}
      <button class="ml-2 underline" @click="error = ''">OK</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8 text-[var(--color-text-tertiary)] text-sm">Lade Vorlagen...</div>

    <!-- Empty state -->
    <div v-else-if="!templates.length" class="pim-card p-8 text-center">
      <Layout class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-secondary)]">Noch keine Katalog-Vorlagen vorhanden.</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle eine Vorlage, um einen anpassbaren Online-Katalog zu gestalten.</p>
    </div>

    <!-- Template list -->
    <div v-else class="space-y-2">
      <div
        v-for="tmpl in templates"
        :key="tmpl.id"
        class="pim-card p-4 flex items-center gap-4 hover:bg-[var(--color-bg)] transition-colors"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <Layout class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="1.75" />
            <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ tmpl.name }}</span>
            <span
              v-if="tmpl.is_active"
              class="text-[10px] px-1.5 py-0.5 rounded bg-green-50 text-green-600 flex items-center gap-0.5"
            >
              <Globe class="w-3 h-3" :stroke-width="2" />
              Aktiv
            </span>
            <span
              v-else
              class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500"
            >
              Inaktiv
            </span>
            <span v-if="tmpl.is_shared" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">
              Geteilt
            </span>
          </div>
          <div class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5 flex items-center gap-3">
            <span v-if="tmpl.description">{{ tmpl.description }}</span>
            <span class="font-mono">{{ tmpl.slug }}</span>
          </div>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="toggleActive(tmpl)"
            :title="tmpl.is_active ? 'Deaktivieren' : 'Aktivieren'"
          >
            <Eye v-if="tmpl.is_active" class="w-3 h-3" :stroke-width="2" />
            <EyeOff v-else class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="openPreview(tmpl)"
            title="Vorschau"
          >
            <ExternalLink class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="duplicateTemplate(tmpl)"
            title="Duplizieren"
          >
            <Copy class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="router.push(`/catalog-templates/${tmpl.id}`)"
            title="Bearbeiten"
          >
            <Pencil class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1 text-[var(--color-error)]"
            @click="deleteTemplate(tmpl.id)"
            title="Löschen"
          >
            <Trash2 class="w-3 h-3" :stroke-width="2" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
