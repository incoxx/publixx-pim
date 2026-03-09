<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { X, Save, RefreshCw, Loader2 } from 'lucide-vue-next'
import { useTranslationsStore } from '@/stores/translations'

const props = defineProps({
  unit: { type: Object, required: true },
  languageOptions: { type: Array, required: true },
})
const emit = defineEmits(['close', 'updated'])

const store = useTranslationsStore()
const editLang = ref('')
const editText = ref('')
const saving = ref(false)
const retranslating = ref(false)
const saveError = ref(null)

function getTranslation(lang) {
  return props.unit.translations?.find(t => t.target_lang === lang)
}

function startEdit(lang) {
  editLang.value = lang
  editText.value = getTranslation(lang)?.translation || ''
  saveError.value = null
}

function cancelEdit() {
  editLang.value = ''
  editText.value = ''
  saveError.value = null
}

async function saveTranslation() {
  if (!editText.value.trim()) return
  saving.value = true
  saveError.value = null
  try {
    await store.updateTranslation(props.unit.id, editLang.value, editText.value.trim())
    editLang.value = ''
    editText.value = ''
    emit('updated')
  } catch (e) {
    saveError.value = e.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

async function retranslateUnit() {
  retranslating.value = true
  saveError.value = null
  try {
    await store.retranslate([props.unit.id])
    await store.fetchUnit(props.unit.id)
  } catch (e) {
    saveError.value = e.response?.data?.message || 'Fehler bei der Neuübersetzung'
  } finally {
    retranslating.value = false
  }
}

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

const statusLabels = { pending: 'Ausstehend', auto: 'Automatisch', reviewed: 'Geprüft' }
const statusColors = {
  pending: 'bg-gray-500/10 text-gray-600',
  auto: 'bg-amber-500/10 text-amber-600',
  reviewed: 'bg-green-500/10 text-green-600',
}
const providerLabels = { deepl: 'DeepL', google: 'Google', claude: 'Claude', human: 'Manuell', import: 'Import' }
</script>

<template>
  <div class="fixed inset-0 z-50 flex justify-end">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/20" @click="emit('close')" />

    <!-- Panel -->
    <div class="relative w-[500px] max-w-full bg-[var(--color-surface)] border-l border-[var(--color-border)] shadow-xl overflow-y-auto">
      <!-- Header -->
      <div class="sticky top-0 bg-[var(--color-surface)] border-b border-[var(--color-border)] px-5 py-4 flex items-center justify-between z-10">
        <h2 class="text-base font-medium text-[var(--color-text-primary)]">Übersetzungseinheit</h2>
        <button @click="emit('close')" class="text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]">
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="p-5 space-y-5">
        <!-- Error -->
        <div
          v-if="saveError"
          class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-md text-xs text-red-700"
        >
          <span>{{ saveError }}</span>
          <button @click="saveError = null" class="ml-auto text-red-400 hover:text-red-600">
            <X class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Source -->
        <div>
          <div class="text-xs text-[var(--color-text-tertiary)] mb-1">Quelltext ({{ unit.source_lang?.toUpperCase() }})</div>
          <div class="text-sm font-medium text-[var(--color-text-primary)] bg-[var(--color-bg)] p-3 rounded-md border border-[var(--color-border)]">
            {{ unit.source_text }}
          </div>
        </div>

        <!-- Meta -->
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div>
            <span class="text-[var(--color-text-tertiary)]">Bereich:</span>
            <span class="ml-1 text-[var(--color-text-secondary)]">{{ unit.domain || '—' }}</span>
          </div>
          <div>
            <span class="text-[var(--color-text-tertiary)]">Zeichen:</span>
            <span class="ml-1 text-[var(--color-text-secondary)]">{{ unit.char_count }}</span>
          </div>
        </div>

        <!-- Retranslate button -->
        <button
          class="flex items-center gap-2 px-3 py-2 text-xs font-medium border border-[var(--color-border)] rounded-md hover:bg-[var(--color-bg)] w-full justify-center"
          :disabled="retranslating"
          @click="retranslateUnit"
        >
          <RefreshCw v-if="!retranslating" class="w-3.5 h-3.5" />
          <Loader2 v-else class="w-3.5 h-3.5 animate-spin" />
          Neu übersetzen (MT)
        </button>

        <!-- Translations -->
        <div>
          <div class="text-xs font-medium text-[var(--color-text-secondary)] mb-2">Übersetzungen</div>
          <div class="space-y-3">
            <div
              v-for="opt in languageOptions"
              :key="opt.value"
              class="bg-[var(--color-bg)] rounded-md border border-[var(--color-border)] p-3"
            >
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-mono uppercase bg-[var(--color-surface)] px-1.5 py-0.5 rounded">{{ opt.value }}</span>
                  <span class="text-xs text-[var(--color-text-secondary)]">{{ opt.label }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span
                    v-if="getTranslation(opt.value)"
                    :class="['text-[10px] px-1.5 py-0.5 rounded-full', statusColors[getTranslation(opt.value).status] || 'bg-gray-500/10 text-gray-600']"
                  >
                    {{ statusLabels[getTranslation(opt.value).status] || getTranslation(opt.value).status }}
                  </span>
                  <span
                    v-if="getTranslation(opt.value)?.provider"
                    class="text-[10px] text-[var(--color-text-tertiary)]"
                  >
                    {{ providerLabels[getTranslation(opt.value).provider] || getTranslation(opt.value).provider }}
                  </span>
                </div>
              </div>

              <!-- Editing mode -->
              <div v-if="editLang === opt.value" class="mt-2">
                <textarea
                  v-model="editText"
                  rows="2"
                  class="w-full text-sm px-3 py-2 border border-[var(--color-border)] rounded-md bg-[var(--color-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 resize-none"
                />
                <div class="flex gap-2 mt-2">
                  <button
                    class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-[var(--color-accent)] text-white rounded-md hover:opacity-90 disabled:opacity-50"
                    :disabled="saving || !editText.trim()"
                    @click="saveTranslation"
                  >
                    <Save v-if="!saving" class="w-3 h-3" />
                    <Loader2 v-else class="w-3 h-3 animate-spin" />
                    Speichern
                  </button>
                  <button
                    class="px-3 py-1.5 text-xs border border-[var(--color-border)] rounded-md hover:bg-[var(--color-surface)]"
                    @click="cancelEdit"
                  >
                    Abbrechen
                  </button>
                </div>
              </div>

              <!-- Display mode -->
              <div v-else>
                <div
                  v-if="getTranslation(opt.value)"
                  class="text-sm text-[var(--color-text-primary)] cursor-pointer hover:bg-[var(--color-surface)] p-1.5 rounded -mx-1.5 transition-colors"
                  @click="startEdit(opt.value)"
                  :title="'Klicken zum Bearbeiten'"
                >
                  {{ getTranslation(opt.value).translation }}
                </div>
                <button
                  v-else
                  class="text-xs text-[var(--color-accent)] hover:underline"
                  @click="startEdit(opt.value)"
                >
                  + Übersetzung hinzufügen
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Usages -->
        <div v-if="unit.usages && unit.usages.length > 0">
          <div class="text-xs font-medium text-[var(--color-text-secondary)] mb-2">Verwendung</div>
          <div class="space-y-1.5">
            <div
              v-for="usage in unit.usages"
              :key="usage.id"
              class="text-xs text-[var(--color-text-secondary)] bg-[var(--color-bg)] px-3 py-2 rounded-md border border-[var(--color-border)]"
            >
              <span class="font-medium text-[var(--color-text-primary)]">{{ usage.entity_label || usage.entity_id }}</span>
              <span class="text-[var(--color-text-tertiary)]"> · {{ usage.entity_type }} · {{ usage.field_name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
