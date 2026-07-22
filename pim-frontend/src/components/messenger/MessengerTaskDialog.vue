<script setup>
import { ref, watch } from 'vue'
import { X } from 'lucide-vue-next'
import messengerApi from '@/api/messenger'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  conversationId: { type: String, required: true },
  assignedTo: { type: String, required: true },
  assignedToName: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'created'])

const title = ref('')
const description = ref('')
const dueAt = ref('')
const saving = ref(false)
const error = ref('')

watch(() => props.modelValue, (open) => {
  if (open) {
    title.value = ''
    description.value = ''
    dueAt.value = ''
    error.value = ''
  }
})

async function save() {
  if (!title.value.trim()) return
  saving.value = true
  error.value = ''
  try {
    const { data } = await messengerApi.tasks.create({
      title: title.value.trim(),
      description: description.value.trim() || null,
      due_at: dueAt.value || null,
      assigned_to: props.assignedTo,
      conversation_id: props.conversationId,
    })
    emit('created', data.data)
    close()
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.detail || 'Aufgabe konnte nicht angelegt werden'
  } finally {
    saving.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/40" @click="close" />
      <div class="relative bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-md flex flex-col m-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <h3 class="text-sm font-semibold">Aufgabe anlegen</h3>
          <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="close">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>

        <div class="p-4 space-y-3">
          <p class="text-xs text-[var(--color-text-tertiary)]">Für {{ assignedToName }}</p>

          <input v-model="title" class="pim-input text-sm w-full" placeholder="Titel *" />
          <textarea v-model="description" rows="3" class="pim-input text-sm w-full resize-none" placeholder="Beschreibung (optional)" />
          <label class="block text-xs text-[var(--color-text-tertiary)]">
            Fällig am
            <input v-model="dueAt" type="date" class="pim-input text-sm w-full mt-1" />
          </label>

          <p v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</p>
        </div>

        <div class="flex justify-end gap-2 px-4 py-3 border-t border-[var(--color-border)]">
          <button class="pim-btn pim-btn-ghost text-xs" @click="close">Abbrechen</button>
          <button class="pim-btn pim-btn-primary text-xs" :disabled="!title.trim() || saving" @click="save">Anlegen</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
