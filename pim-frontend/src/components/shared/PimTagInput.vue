<script setup>
/**
 * Tag-Zuordnung als Chips mit Type-Ahead.
 *
 * Die Auswahl kommt aus den Tag-Stammdaten (nur aktive Tags), nicht als
 * Freitext — deshalb arbeitet die Komponente mit Tag-Objekten, nicht mit
 * Strings. Wer `tags.create` hat, kann fehlende Tags direkt hier anlegen,
 * statt den Editor zu verlassen.
 *
 * Bewusst als eigene Komponente: dieselbe Eingabe wird im Produkteditor und
 * (folgend) am Medium gebraucht.
 */
import { ref, computed, onMounted } from 'vue'
import { X, Plus, Loader2 } from 'lucide-vue-next'
import { tags as tagsApi } from '@/api/tags'
import { useAuthStore } from '@/stores/auth'
import { useLocalizedName } from '@/composables/useLocalizedName'

const props = defineProps({
  /** Zugeordnete Tags als Objekte ({ id, technical_name, name_de, ... }) */
  modelValue: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  /** Im Filter-Kontext sinnlos: dort wird ausgewählt, nicht gepflegt. */
  allowCreate: { type: Boolean, default: true },
  placeholder: { type: String, default: 'Tag hinzufügen…' },
})

const emit = defineEmits(['update:modelValue'])

const authStore = useAuthStore()
const { localizedName } = useLocalizedName()

// Vorschlaege kommen serverseitig gefiltert: die API deckelt per_page auf 100,
// eine clientseitige Liste haette ab dem 101. Tag Werte verschwiegen — und
// "anlegen" fuer einen laengst vorhandenen Tag angeboten (Dublette mit gleichem
// Namen, nur anderem technischen Namen).
const options = ref([])
const loading = ref(false)
const creating = ref(false)
const input = ref('')
const open = ref(false)
let searchDebounce = null
let requestSeq = 0

const canCreate = computed(() => props.allowCreate && authStore.hasPermission('tags.create'))

function tagLabel(tag) {
  return localizedName(tag) || tag?.technical_name || ''
}

async function loadOptions(term = '') {
  loading.value = true
  const seq = ++requestSeq
  try {
    const { data } = await tagsApi.list({
      perPage: 100,
      sort: 'name_de',
      order: 'asc',
      filters: { is_active: 1 },
      search: term.trim() || undefined,
    })
    // Verspaetete Antwort einer aelteren Eingabe darf die neuere nicht ueberschreiben
    if (seq !== requestSeq) return
    options.value = data.data || data || []
  } catch {
    if (seq === requestSeq) options.value = []
  } finally {
    if (seq === requestSeq) loading.value = false
  }
}

function onInput() {
  open.value = true
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => loadOptions(input.value), 250)
}

const assignedIds = computed(() => new Set(props.modelValue.map(t => t.id)))

const suggestions = computed(() =>
  options.value.filter(t => !assignedIds.value.has(t.id)).slice(0, 8))

/** Genauer Treffer verhindert, dass „anlegen" für einen vorhandenen Tag erscheint. */
const exactMatch = computed(() => {
  const term = input.value.trim().toLowerCase()
  if (!term) return null
  return options.value.find(t =>
    tagLabel(t).toLowerCase() === term || (t.technical_name || '').toLowerCase() === term) || null
})

const showCreateOption = computed(() =>
  canCreate.value && input.value.trim() !== '' && !exactMatch.value)

function add(tag) {
  if (!tag || assignedIds.value.has(tag.id)) return
  emit('update:modelValue', [...props.modelValue, tag])
  input.value = ''
  open.value = false
}

function remove(tagId) {
  emit('update:modelValue', props.modelValue.filter(t => t.id !== tagId))
}

/** Enter: vorhandenen Tag übernehmen, sonst (mit Recht) neu anlegen. */
function onEnter() {
  if (exactMatch.value) return add(exactMatch.value)
  if (suggestions.value.length === 1) return add(suggestions.value[0])
  if (showCreateOption.value) return createTag()
}

async function createTag() {
  const name = input.value.trim()
  if (!name || creating.value) return
  creating.value = true
  try {
    const { data } = await tagsApi.create({ name_de: name })
    const tag = data.data || data
    add(tag)
  } catch {
    // Fehler (z.B. doppelter Name) bleibt im Feld stehen, damit nichts verloren geht
  } finally {
    creating.value = false
  }
}

function onBlur() {
  // Klick auf einen Vorschlag darf nicht vom Blur geschluckt werden
  setTimeout(() => { open.value = false }, 200)
}

onMounted(loadOptions)

defineExpose({ suggestions, exactMatch, showCreateOption, add, remove, createTag, onEnter, loadOptions })
</script>

<template>
  <div>
    <div v-if="modelValue.length" class="flex flex-wrap gap-1 mb-1.5">
      <span
        v-for="tag in modelValue"
        :key="tag.id"
        class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full bg-[var(--color-accent)]/10 text-[var(--color-accent)] text-[11px]"
        :title="tag.technical_name"
      >
        {{ tagLabel(tag) }}
        <button v-if="!disabled" type="button" class="hover:opacity-70" :title="`${tagLabel(tag)} entfernen`" @click="remove(tag.id)">
          <X class="w-2.5 h-2.5" :stroke-width="2" />
        </button>
      </span>
    </div>

    <div v-if="!disabled" class="relative">
      <input
        v-model="input"
        class="pim-input text-xs w-full"
        :placeholder="placeholder"
        @focus="open = true"
        @input="onInput"
        @keydown.enter.prevent="onEnter"
        @blur="onBlur"
      />
      <Loader2 v-if="loading || creating" class="w-3.5 h-3.5 animate-spin absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" />

      <div
        v-if="open && (suggestions.length || showCreateOption)"
        class="absolute z-20 mt-1 w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg max-h-48 overflow-y-auto"
      >
        <button
          v-for="tag in suggestions"
          :key="tag.id"
          type="button"
          class="flex w-full items-center justify-between gap-2 px-2 py-1.5 text-left text-xs hover:bg-[var(--color-bg)]"
          @mousedown.prevent="add(tag)"
        >
          <span>{{ tagLabel(tag) }}</span>
          <span class="font-mono text-[10px] text-[var(--color-text-tertiary)]">{{ tag.technical_name }}</span>
        </button>
        <button
          v-if="showCreateOption"
          type="button"
          class="flex w-full items-center gap-1.5 border-t border-[var(--color-border)] px-2 py-1.5 text-left text-xs text-[var(--color-accent)] hover:bg-[var(--color-bg)]"
          @mousedown.prevent="createTag"
        >
          <Plus class="w-3 h-3" :stroke-width="2" />
          <span>„{{ input.trim() }}" als neuen Tag anlegen</span>
        </button>
      </div>
    </div>
  </div>
</template>
