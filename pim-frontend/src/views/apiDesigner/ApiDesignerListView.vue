<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Plus, Pencil, Trash2, Code, ExternalLink, Copy, Check,
} from 'lucide-vue-next'
import apiTemplatesApi from '@/api/apiTemplates'
import { createEmptyTemplate } from '@/stores/apiDesigner'

const router = useRouter()

const templates = ref([])
const loading = ref(false)
const error = ref('')
const showCreate = ref(false)
const newName = ref('')
const copiedSlug = ref(null)

onMounted(loadTemplates)

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await apiTemplatesApi.list()
    templates.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der API-Templates'
  } finally {
    loading.value = false
  }
}

async function createTemplate() {
  if (!newName.value.trim()) return
  try {
    const { data } = await apiTemplatesApi.create({
      name: newName.value.trim(),
      template_json: createEmptyTemplate(),
      direction: 'export',
    })
    const tmpl = data.data || data
    router.push(`/api-designer/${tmpl.id}`)
  } catch (e) {
    error.value = 'Fehler beim Erstellen'
  }
}

async function deleteTemplate(id) {
  if (!confirm('API-Template wirklich löschen?')) return
  try {
    await apiTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

function getStreamUrl(tmpl) {
  return `${window.location.origin}/api/v1/api-streams/${tmpl.slug}`
}

function copyStreamUrl(tmpl) {
  navigator.clipboard.writeText(getStreamUrl(tmpl))
  copiedSlug.value = tmpl.slug
  setTimeout(() => { copiedSlug.value = null }, 2000)
}

const directionLabels = { export: 'Export', import: 'Import', bidirectional: 'Bi-Direktional' }
const directionColors = {
  export: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  import: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  bidirectional: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">API Designer</h2>
      <button class="pim-btn pim-btn-primary text-xs" @click="showCreate = !showCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neues API-Template
      </button>
    </div>

    <!-- Create form -->
    <div v-if="showCreate" class="pim-card p-4 flex items-end gap-3">
      <div class="flex-1">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Template-Name</label>
        <input
          v-model="newName"
          class="pim-input text-xs w-full"
          placeholder="z.B. Produktkatalog API"
          @keyup.enter="createTemplate"
          autofocus
        />
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="createTemplate" :disabled="!newName.trim()">
        Erstellen & Bearbeiten
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="showCreate = false; newName = ''">
        Abbrechen
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8 text-[var(--color-text-tertiary)] text-sm">Lade Templates...</div>

    <!-- Empty state -->
    <div v-else-if="!templates.length" class="pim-card p-8 text-center">
      <Code class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-secondary)]">Noch keine API-Templates vorhanden.</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle ein neues Template, um JSON-API-Endpunkte zu designen.</p>
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
            <Code class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="1.75" />
            <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ tmpl.name }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded" :class="directionColors[tmpl.direction || 'export']">
              {{ directionLabels[tmpl.direction || 'export'] }}
            </span>
            <span
              class="text-[10px] px-1.5 py-0.5 rounded"
              :class="tmpl.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
            >
              {{ tmpl.is_active ? 'Aktiv' : 'Inaktiv' }}
            </span>
          </div>
          <div class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5 flex items-center gap-3">
            <span v-if="tmpl.slug" class="font-mono">/api-streams/{{ tmpl.slug }}</span>
            <span v-if="tmpl.description">{{ tmpl.description }}</span>
          </div>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            v-if="tmpl.slug"
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="copyStreamUrl(tmpl)"
            :title="copiedSlug === tmpl.slug ? 'Kopiert!' : 'Stream-URL kopieren'"
          >
            <Check v-if="copiedSlug === tmpl.slug" class="w-3 h-3 text-green-500" :stroke-width="2" />
            <Copy v-else class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="router.push(`/api-designer/${tmpl.id}`)"
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
