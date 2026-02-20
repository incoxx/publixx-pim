<script setup>
const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Bestätigung' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Löschen' },
  cancelLabel: { type: String, default: 'Abbrechen' },
  danger: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="$emit('cancel')" />
        <div class="relative w-full max-w-[400px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl p-6 space-y-4 shadow-xl mx-4">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ title }}</h3>
          <p class="text-xs text-[var(--color-text-tertiary)] leading-relaxed">{{ message }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button
              class="pim-btn pim-btn-secondary text-xs"
              :disabled="loading"
              @click="$emit('cancel')"
            >
              {{ cancelLabel }}
            </button>
            <button
              :class="['pim-btn text-xs', danger ? 'bg-[var(--color-error)] text-white hover:bg-[var(--color-error)]/90' : 'pim-btn-primary']"
              :disabled="loading"
              @click="$emit('confirm')"
            >
              <span v-if="loading">Bitte warten…</span>
              <span v-else>{{ confirmLabel }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
