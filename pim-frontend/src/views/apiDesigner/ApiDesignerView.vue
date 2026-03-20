<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { useApiDesignerStore } from '@/stores/apiDesigner'
import {
  ArrowLeft, Save, Eye, Settings, Filter, Loader2,
} from 'lucide-vue-next'
import searchProfilesApi from '@/api/searchProfiles'
import ApiTreeEditor from '@/components/apiDesigner/ApiTreeEditor.vue'
import ApiFieldPicker from '@/components/apiDesigner/ApiFieldPicker.vue'
import ApiJsonPreview from '@/components/apiDesigner/ApiJsonPreview.vue'
import ApiStreamSettingsModal from '@/components/apiDesigner/ApiStreamSettingsModal.vue'

const route = useRoute()
const router = useRouter()
const store = useApiDesignerStore()

const searchProfiles = ref([])
const saving = ref(false)
const error = ref('')
const loadError = ref(false)
const showStreamSettings = ref(false)
const showFieldPicker = ref(false)

onMounted(async () => {
  const id = route.params.id
  try {
    await Promise.all([
      store.loadTemplate(id),
      store.loadFields(),
      loadSearchProfiles(),
    ])
  } catch (e) {
    loadError.value = true
    error.value = 'Template konnte nicht geladen werden.'
  }
})

// Unsaved changes guard
function beforeUnload(e) {
  if (store.isDirty) {
    e.preventDefault()
    e.returnValue = ''
  }
}
window.addEventListener('beforeunload', beforeUnload)
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

onBeforeRouteLeave(() => {
  if (store.isDirty && !confirm('Es gibt ungespeicherte Änderungen. Seite wirklich verlassen?')) {
    return false
  }
})

async function loadSearchProfiles() {
  try {
    const { data } = await searchProfilesApi.list()
    searchProfiles.value = data.data || data
  } catch (e) { /* ignore */ }
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    await store.saveTemplate()
  } catch (e) {
    error.value = 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

async function loadPreview() {
  error.value = ''
  try {
    await store.loadPreview()
  } catch (e) {
    error.value = 'Vorschau konnte nicht geladen werden'
  }
}

function onSearchProfileChange(id) {
  if (store.currentTemplate) {
    store.currentTemplate.search_profile_id = id || null
    store.isDirty = true
  }
}
</script>

<template>
  <div class="h-full flex flex-col" v-if="store.currentTemplate">
    <!-- Toolbar -->
    <div class="shrink-0 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2.5 flex items-center gap-3">
      <button
        class="pim-btn pim-btn-secondary text-xs px-2 py-1"
        @click="router.push('/api-designer')"
        title="Zurück"
      >
        <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <input
        v-model="store.currentTemplate.name"
        class="pim-input text-sm font-medium w-56"
        placeholder="Template-Name"
        @input="store.isDirty = true"
      />

      <!-- Direction -->
      <select
        v-model="store.currentTemplate.direction"
        class="pim-input text-xs w-32"
        @change="store.isDirty = true"
      >
        <option value="export">Export</option>
        <option value="import">Import</option>
        <option value="bidirectional">Bi-Direktional</option>
      </select>

      <!-- Language -->
      <select
        v-model="store.currentTemplate.language"
        class="pim-input text-xs w-16"
        @change="store.isDirty = true"
      >
        <option value="de">DE</option>
        <option value="en">EN</option>
      </select>

      <!-- Active Toggle -->
      <label class="flex items-center gap-1.5 text-[11px] cursor-pointer text-[var(--color-text-secondary)]">
        <input
          type="checkbox"
          :checked="store.currentTemplate.is_active"
          class="rounded"
          @change="store.currentTemplate.is_active = $event.target.checked; store.isDirty = true"
        />
        Aktiv
      </label>

      <div class="flex-1"></div>

      <!-- Search Profile -->
      <div class="flex items-center gap-1.5">
        <Filter class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" :stroke-width="2" />
        <span class="text-xs text-[var(--color-text-secondary)] whitespace-nowrap">Suchprofil:</span>
        <select
          class="pim-input text-xs w-48"
          :value="store.currentTemplate.search_profile_id || ''"
          @change="onSearchProfileChange($event.target.value)"
        >
          <option value="">Alle Produkte</option>
          <option v-for="sp in searchProfiles" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
        </select>
      </div>

      <!-- Stream Settings -->
      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="showStreamSettings = true"
        title="Stream-Einstellungen"
      >
        <Settings class="w-3.5 h-3.5" :stroke-width="2" />
        Stream
      </button>

      <!-- Actions -->
      <button
        class="pim-btn pim-btn-primary text-xs"
        @click="save"
        :disabled="saving || !store.isDirty"
      >
        <Save class="w-3.5 h-3.5" :stroke-width="2" />
        {{ saving ? 'Speichern...' : 'Speichern' }}
      </button>

      <button
        class="pim-btn pim-btn-secondary text-xs"
        @click="loadPreview"
        :disabled="store.previewLoading"
      >
        <Loader2 v-if="store.previewLoading" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
        <Eye v-else class="w-3.5 h-3.5" :stroke-width="2" />
        Vorschau
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="px-4 py-2 bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Split-View Layout -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left: Tree Editor -->
      <div class="flex-1 overflow-y-auto p-4 bg-[var(--color-bg)]">
        <ApiTreeEditor @show-field-picker="showFieldPicker = true" />
      </div>

      <!-- Right: JSON Preview -->
      <div class="w-[45%] shrink-0 border-l border-[var(--color-border)] overflow-y-auto bg-[var(--color-surface)]">
        <ApiJsonPreview />
      </div>
    </div>

    <!-- Field Picker Popover -->
    <ApiFieldPicker
      v-if="showFieldPicker"
      @close="showFieldPicker = false"
    />

    <!-- Stream Settings Modal -->
    <ApiStreamSettingsModal
      v-if="showStreamSettings"
      @close="showStreamSettings = false"
    />
  </div>

  <!-- Loading / Error state -->
  <div v-else class="flex flex-col items-center justify-center h-64 text-[var(--color-text-tertiary)]">
    <template v-if="loadError">
      <p class="text-[var(--color-error)] text-sm mb-2">{{ error }}</p>
      <button class="pim-btn pim-btn-secondary text-xs" @click="router.push('/api-designer')">Zurück zur Übersicht</button>
    </template>
    <template v-else>Lade Template...</template>
  </div>
</template>
