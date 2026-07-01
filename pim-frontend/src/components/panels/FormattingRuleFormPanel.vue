<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { formattingRules } from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  formattingRule: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.formattingRule)

const RULE_TYPE_OPTIONS = [
  { value: 'uppercase', label: 'Großbuchstaben (UPPERCASE)' },
  { value: 'lowercase', label: 'Kleinbuchstaben (lowercase)' },
  { value: 'capitalize', label: 'Erster Buchstabe groß (Capitalize)' },
  { value: 'regex', label: 'Regex Suchen/Ersetzen' },
  { value: 'number_format', label: 'Zahlenformatierung' },
]

const existingConfig = props.formattingRule?.config || {}

const formData = ref({
  name: props.formattingRule?.name || '',
  rule_type: props.formattingRule?.rule_type || 'uppercase',
  description: props.formattingRule?.description || '',
})

const regexConfig = ref({
  pattern: existingConfig.pattern || '',
  replacement: existingConfig.replacement || '',
  case_insensitive: !!existingConfig.case_insensitive,
})

const numberConfig = ref({
  decimals: existingConfig.decimals ?? 2,
  decimal_separator: existingConfig.decimal_separator ?? ',',
  thousands_separator: existingConfig.thousands_separator ?? '.',
  prefix: existingConfig.prefix || '',
  suffix: existingConfig.suffix || '',
})

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'rule_type', label: 'Regeltyp', type: 'select', options: RULE_TYPE_OPTIONS, required: true },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
])

function buildConfig() {
  if (formData.value.rule_type === 'regex') return { ...regexConfig.value }
  if (formData.value.rule_type === 'number_format') return { ...numberConfig.value }
  return {}
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data, config: buildConfig() }
  try {
    if (isEdit.value) {
      await formattingRules.update(props.formattingRule.id, payload)
    } else {
      await formattingRules.create(payload)
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
  <div class="p-4 space-y-6">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Formatierungsregel bearbeiten' : 'Neue Formatierungsregel' }}
    </h3>
    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />

    <!-- Regex-Konfiguration -->
    <div v-if="formData.rule_type === 'regex'" class="border-t border-[var(--color-border)] pt-4 space-y-3 max-w-lg">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">Regex-Konfiguration</h4>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
          Suchmuster (Regex) <span class="text-[var(--color-error)]">*</span>
        </label>
        <input v-model="regexConfig.pattern" class="pim-input text-xs w-full font-mono" placeholder="z.B. \s+" />
        <p v-if="errors['config.pattern']" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors['config.pattern'] }}</p>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Ersetzen durch</label>
        <input v-model="regexConfig.replacement" class="pim-input text-xs w-full font-mono" />
      </div>
      <label class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)]">
        <input type="checkbox" v-model="regexConfig.case_insensitive" class="rounded" />
        Groß-/Kleinschreibung ignorieren
      </label>
    </div>

    <!-- Zahlenformat-Konfiguration -->
    <div v-if="formData.rule_type === 'number_format'" class="border-t border-[var(--color-border)] pt-4 space-y-3 max-w-lg">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">Zahlenformat-Konfiguration</h4>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Nachkommastellen</label>
          <input v-model.number="numberConfig.decimals" type="number" min="0" max="10" class="pim-input text-xs w-full" />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Dezimaltrennzeichen</label>
          <input v-model="numberConfig.decimal_separator" class="pim-input text-xs w-full" maxlength="5" />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Tausendertrennzeichen</label>
          <input v-model="numberConfig.thousands_separator" class="pim-input text-xs w-full" maxlength="5" />
        </div>
        <div></div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Präfix</label>
          <input v-model="numberConfig.prefix" class="pim-input text-xs w-full" maxlength="20" />
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Suffix</label>
          <input v-model="numberConfig.suffix" class="pim-input text-xs w-full" maxlength="20" placeholder="z.B. kg" />
        </div>
      </div>
    </div>
  </div>
</template>
