<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeMetadataDefinitions } from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  definition: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.definition)

// Wert-Typen — Spiegel von AttributeMetadataDefinition::VALUE_TYPES
const valueTypeOptions = [
  { value: 'text', label: 'Text' },
  { value: 'textarea', label: 'Mehrzeiliger Text' },
  { value: 'number', label: 'Zahl' },
  { value: 'date', label: 'Datum' },
  { value: 'boolean', label: 'Ja/Nein' },
  { value: 'select', label: 'Auswahl' },
  { value: 'multiselect', label: 'Mehrfachauswahl' },
  { value: 'url', label: 'Link' },
  { value: 'email', label: 'E-Mail' },
]

const OPTION_TYPES = ['select', 'multiselect']

const formData = ref(
  props.definition
    ? {
        technical_name: props.definition.technical_name || '',
        name_de: props.definition.name_de || '',
        name_en: props.definition.name_en || '',
        description: props.definition.description || '',
        value_type: props.definition.value_type || 'text',
        is_required: !!props.definition.is_required,
        sort_order: props.definition.sort_order ?? 0,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        value_type: 'text',
        is_required: false,
        sort_order: 0,
      }
)

// Optionen außerhalb von PimForm verwaltet (eine pro Zeile), wie bei simple_options
const optionsText = ref((props.definition?.options || []).join('\n'))

const needsOptions = computed(() => OPTION_TYPES.includes(formData.value.value_type))

const valuesCount = computed(() => props.definition?.values_count ?? 0)

// Der technische Name ist der Schlüssel der Metadaten-Map am Attribut-Endpoint
// und deshalb nach dem Anlegen nicht mehr änderbar.
const fields = computed(() => [
  {
    key: 'technical_name', label: 'Technischer Name', type: 'text', required: true,
    disabled: isEdit.value,
    hint: isEdit.value ? 'Nach dem Anlegen nicht mehr änderbar.' : undefined,
  },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'value_type', label: 'Wert-Typ', type: 'select', required: true, options: valueTypeOptions },
  { key: 'is_required', label: 'Pflichtfeld', type: 'boolean', hint: 'Muss beim Speichern eines Attributs gefüllt sein.' },
  { key: 'sort_order', label: 'Sortierung', type: 'number' },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
])

const typeChangeWarning = computed(() => {
  if (!isEdit.value || !props.definition) return null
  if (formData.value.value_type === props.definition.value_type) return null
  if (valuesCount.value === 0) return null

  return `Typwechsel von "${props.definition.value_type}" zu "${formData.value.value_type}" bei `
    + `${valuesCount.value} vorhandenen Werten — bestehende Werte werden nicht konvertiert.`
})

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}

  const payload = { ...data }

  // Geleertes Zahlenfeld liefert NaN, das als null serialisiert wird und an der
  // integer-Regel scheitern würde.
  payload.sort_order = Number.isFinite(payload.sort_order) ? payload.sort_order : 0

  if (needsOptions.value) {
    payload.options = optionsText.value
      .split('\n')
      .map(s => s.trim())
      .filter(Boolean)
  } else {
    payload.options = null
  }

  try {
    if (isEdit.value) {
      // technical_name ist serverseitig nicht änderbar — gar nicht erst senden
      delete payload.technical_name
      await attributeMetadataDefinitions.update(props.definition.id, payload)
    } else {
      await attributeMetadataDefinitions.create(payload)
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-4 space-y-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Metadatum bearbeiten' : 'Neues Metadatum' }}
    </h3>

    <p class="text-[11px] text-[var(--color-text-tertiary)]">
      Metadaten beschreiben die Herkunft und Zuständigkeit von Attributen —
      z.&nbsp;B. Datenherkunft, Dateneigentümer oder Datenverbindung.
    </p>

    <!-- Auswahloptionen (außerhalb PimForm, eigene Serialisierung) -->
    <div v-if="needsOptions" class="max-w-lg">
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
        Auswahloptionen <span class="text-[var(--color-error)]">*</span>
      </label>
      <textarea
        v-model="optionsText"
        rows="5"
        placeholder="Eine Option pro Zeile, z.B.&#10;ERP&#10;Agentur&#10;Marketing"
        class="w-full px-3 py-2 rounded-lg border border-[var(--color-border)] bg-transparent text-sm text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]"
      />
      <p v-if="errors.options" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.options }}</p>
      <p class="mt-1 text-[11px] text-[var(--color-text-secondary)]">
        Eine Option pro Zeile. Optional <code>Label::Wert</code> — gespeichert wird der Wert.
      </p>
    </div>

    <!-- Warnung bei Typwechsel mit Bestandswerten -->
    <div
      v-if="typeChangeWarning"
      class="p-3 rounded-lg bg-[var(--color-warning,#f59e0b)]/10 border border-[var(--color-warning,#f59e0b)]/30 text-xs text-[var(--color-warning,#f59e0b)]"
    >
      {{ typeChangeWarning }}
    </div>

    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />

    <div v-if="isEdit" class="border-t border-[var(--color-border)] pt-4">
      <p class="text-xs text-[var(--color-text-secondary)]">
        Gepflegt an
        <span class="font-semibold text-[var(--color-text-primary)]">{{ valuesCount }}</span>
        {{ valuesCount === 1 ? 'Attribut' : 'Attributen' }}.
      </p>
    </div>
  </div>
</template>
