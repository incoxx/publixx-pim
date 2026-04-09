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
import ApiGraphqlPreview from '@/components/apiDesigner/ApiGraphqlPreview.vue'
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
const mobilePanel = ref('editor') // 'editor' | 'preview' — Mobile-Tab-Toggle

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
    <div class="shrink-0 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2 sm:px-4 sm:py-2.5">
      <!-- Row 1: Back + Name + Actions -->
      <div class="flex items-center gap-2 sm:gap-3">
        <button
          class="pim-btn pim-btn-secondary text-xs px-2 py-1 shrink-0"
          @click="router.push('/api-designer')"
          title="Zurück"
        >
          <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
        </button>

        <input
          v-model="store.currentTemplate.name"
          class="pim-input text-sm font-medium min-w-0 flex-1 shrink"
          style="max-width: 14rem"
          placeholder="Template-Name"
          @input="store.isDirty = true"
        />

        <div class="flex-1"></div>

        <!-- Actions (immer sichtbar) -->
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0 ml-auto">
          <button
            class="pim-btn pim-btn-secondary text-xs px-2 py-1 sm:px-3"
            @click="showStreamSettings = true"
            title="Stream-Einstellungen"
          >
            <Settings class="w-3.5 h-3.5" :stroke-width="2" />
            <span class="hidden sm:inline ml-1">Stream</span>
          </button>

          <button
            class="pim-btn pim-btn-primary text-xs px-2 py-1 sm:px-3"
            @click="save"
            :disabled="saving"
          >
            <Save class="w-3.5 h-3.5" :stroke-width="2" />
            <span class="hidden sm:inline ml-1">{{ saving ? 'Speichern...' : 'Speichern' }}</span>
          </button>

          <button
            class="pim-btn pim-btn-secondary text-xs px-2 py-1 sm:px-3"
            @click="loadPreview"
            :disabled="store.previewLoading"
          >
            <Loader2 v-if="store.previewLoading" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
            <Eye v-else class="w-3.5 h-3.5" :stroke-width="2" />
            <span class="hidden sm:inline ml-1">Vorschau</span>
          </button>
        </div>
      </div>

      <!-- Row 2: Settings (wraps auf Mobile) -->
      <div class="flex flex-wrap items-center gap-2 mt-2">
        <!-- Direction -->
        <select
          v-model="store.currentTemplate.direction"
          class="pim-input text-xs w-auto min-w-[100px]"
          @change="store.isDirty = true"
        >
          <option value="export">Export</option>
          <option value="import">Import</option>
          <option value="bidirectional">Bi-Direktional</option>
        </select>

        <!-- Output Format -->
        <select
          v-model="store.currentTemplate.output_format"
          class="pim-input text-xs w-auto min-w-[90px]"
          @change="store.isDirty = true"
        >
          <option value="json">JSON</option>
          <option value="graphql">GraphQL</option>
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

        <!-- Search Profile -->
        <div class="flex items-center gap-1.5">
          <Filter class="w-3.5 h-3.5 text-[var(--color-text-secondary)] shrink-0" :stroke-width="2" />
          <select
            class="pim-input text-xs w-auto min-w-[120px] max-w-[200px]"
            :value="store.currentTemplate.search_profile_id || ''"
            @change="onSearchProfileChange($event.target.value)"
          >
            <option value="">Alle Produkte</option>
            <option v-for="sp in searchProfiles" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="px-4 py-2 bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Mobile Tab Toggle (nur auf kleinen Screens) -->
    <div class="flex sm:hidden border-b border-[var(--color-border)] bg-[var(--color-surface)]">
      <button
        class="flex-1 px-4 py-2 text-xs font-medium transition-colors"
        :class="mobilePanel === 'editor' ? 'text-[var(--color-accent)] border-b-2 border-[var(--color-accent)]' : 'text-[var(--color-text-secondary)]'"
        @click="mobilePanel = 'editor'"
      >
        Editor
      </button>
      <button
        class="flex-1 px-4 py-2 text-xs font-medium transition-colors"
        :class="mobilePanel === 'preview' ? 'text-[var(--color-accent)] border-b-2 border-[var(--color-accent)]' : 'text-[var(--color-text-secondary)]'"
        @click="mobilePanel = 'preview'"
      >
        Vorschau
      </button>
    </div>

    <!-- Split-View Layout (Desktop: side-by-side, Mobile: tabbed) -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left: Tree Editor -->
      <div
        class="flex-1 overflow-y-auto p-3 sm:p-4 bg-[var(--color-bg)]"
        :class="{ 'hidden sm:block': mobilePanel !== 'editor' }"
      >
        <ApiTreeEditor @show-field-picker="showFieldPicker = true" />
      </div>

      <!-- Right: Preview (JSON or GraphQL) -->
      <div
        class="w-full sm:w-[45%] sm:shrink-0 sm:border-l border-[var(--color-border)] overflow-y-auto bg-[var(--color-surface)]"
        :class="{ 'hidden sm:block': mobilePanel !== 'preview' }"
      >
        <ApiGraphqlPreview v-if="store.currentTemplate?.output_format === 'graphql'" />
        <ApiJsonPreview v-else />
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
