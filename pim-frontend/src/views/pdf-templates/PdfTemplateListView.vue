<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Plus, Pencil, Trash2, Download, FileText, Copy, Users,
} from 'lucide-vue-next'
import pdfTemplatesApi from '@/api/pdfTemplates'
import { createEmptyTemplate } from '@/stores/pdfTemplateDesigner'
import { fontFamilies, defaultFontFamily } from '@/components/pdf-templates/fontList'

const router = useRouter()

const templates = ref([])
const loading = ref(false)
const error = ref('')
const showCreate = ref(false)
const newName = ref('')
const newFont = ref(defaultFontFamily)

onMounted(loadTemplates)

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await pdfTemplatesApi.list()
    templates.value = data.data || data
  } catch (e) {
    error.value = 'Fehler beim Laden der PDF-Vorlagen'
  } finally {
    loading.value = false
  }
}

async function createTemplate() {
  if (!newName.value.trim()) return
  try {
    const { data } = await pdfTemplatesApi.create({
      name: newName.value.trim(),
      template_json: createEmptyTemplate(newFont.value),
    })
    const tmpl = data.data || data
    router.push(`/pdf-templates/${tmpl.id}`)
  } catch (e) {
    error.value = 'Fehler beim Erstellen'
  }
}

async function deleteTemplate(id) {
  if (!confirm('PDF-Vorlage wirklich löschen?')) return
  try {
    await pdfTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
  } catch (e) {
    error.value = 'Fehler beim Löschen'
  }
}

async function duplicateTemplate(tmpl) {
  try {
    const { data: origData } = await pdfTemplatesApi.get(tmpl.id)
    const orig = origData.data || origData
    const { data } = await pdfTemplatesApi.create({
      name: orig.name + ' (Kopie)',
      description: orig.description,
      template_json: orig.template_json,
      page_orientation: orig.page_orientation,
      page_size: orig.page_size,
    })
    const newTmpl = data.data || data
    templates.value.push(newTmpl)
  } catch (e) {
    error.value = 'Fehler beim Duplizieren'
  }
}
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">PDF-Vorlagen Designer</h2>
      <button class="pim-btn pim-btn-primary text-xs" @click="showCreate = !showCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neue Vorlage
      </button>
    </div>

    <!-- Create form -->
    <div v-if="showCreate" class="pim-card p-4 flex items-end gap-3">
      <div class="flex-1">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Vorlagen-Name</label>
        <input
          v-model="newName"
          class="pim-input text-xs w-full"
          placeholder="z.B. Produktdatenblatt"
          @keyup.enter="createTemplate"
          autofocus
        />
      </div>
      <div class="w-44">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Standard-Schriftart</label>
        <select v-model="newFont" class="pim-input text-xs w-full">
          <option v-for="f in fontFamilies" :key="f.value" :value="f.value">{{ f.label }}</option>
        </select>
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
    <div v-if="loading" class="text-center py-8 text-[var(--color-text-tertiary)] text-sm">Lade Vorlagen...</div>

    <!-- Empty state -->
    <div v-else-if="!templates.length" class="pim-card p-8 text-center">
      <FileText class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-secondary)]">Noch keine PDF-Vorlagen vorhanden.</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle eine Vorlage, um Produktdatenblätter visuell zu gestalten.</p>
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
            <FileText class="w-4 h-4 text-[var(--color-accent)] shrink-0" :stroke-width="1.75" />
            <span class="text-sm font-medium text-[var(--color-text-primary)] truncate">{{ tmpl.name }}</span>
            <span v-if="tmpl.is_shared" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 flex items-center gap-0.5">
              <Users class="w-3 h-3" :stroke-width="2" />
              Geteilt
            </span>
          </div>
          <div class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5 flex items-center gap-3">
            <span v-if="tmpl.description">{{ tmpl.description }}</span>
            <span>{{ tmpl.page_orientation === 'landscape' ? 'Querformat' : 'Hochformat' }}</span>
            <span>{{ tmpl.page_size || 'A4' }}</span>
          </div>
        </div>

        <div class="flex items-center gap-1.5 shrink-0">
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="duplicateTemplate(tmpl)"
            title="Duplizieren"
          >
            <Copy class="w-3 h-3" :stroke-width="2" />
          </button>
          <button
            class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
            @click="router.push(`/pdf-templates/${tmpl.id}`)"
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
