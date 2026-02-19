<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import PimAttributeInput from './PimAttributeInput.vue'

const props = defineProps({
  fields: { type: Array, required: true },
  modelValue: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const form = ref({ ...props.modelValue })

function updateField(key, value) {
  form.value[key] = value
  emit('update:modelValue', { ...form.value })
}

function handleSubmit() {
  emit('submit', { ...form.value })
}

// Listen for Cmd+S
function handleSave(e) {
  handleSubmit()
}

onMounted(() => {
  window.addEventListener('pim:save', handleSave)
})

onUnmounted(() => {
  window.removeEventListener('pim:save', handleSave)
})
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <div
      v-for="field in fields"
      :key="field.key"
      :class="field.fullWidth ? '' : 'max-w-lg'"
    >
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
        {{ field.label }}
        <span v-if="field.required" class="text-[var(--color-error)]">*</span>
      </label>

      <PimAttributeInput
        :type="field.type || 'text'"
        :modelValue="form[field.key]"
        :options="field.options"
        :placeholder="field.placeholder"
        :disabled="readonly || field.disabled"
        :error="errors[field.key]"
        @update:modelValue="updateField(field.key, $event)"
      />

      <p v-if="errors[field.key]" class="mt-1 text-[11px] text-[var(--color-error)]">
        {{ errors[field.key] }}
      </p>
      <p v-if="field.hint" class="mt-1 text-[11px] text-[var(--color-text-tertiary)]">
        {{ field.hint }}
      </p>
    </div>

    <div v-if="!readonly" class="flex items-center gap-2 pt-4 border-t border-[var(--color-border)]">
      <button
        type="submit"
        class="pim-btn pim-btn-primary"
        :disabled="loading"
      >
        <span v-if="loading">Speichern…</span>
        <span v-else>Speichern</span>
      </button>
      <button
        type="button"
        class="pim-btn pim-btn-secondary"
        @click="$emit('cancel')"
      >
        Abbrechen
      </button>
    </div>
  </form>
</template>
