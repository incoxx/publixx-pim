<script setup>
import { useToastStore } from '@/stores/toast'

const store = useToastStore()
</script>

<template>
  <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="msg in store.messages"
        :key="msg.id"
        :data-testid="msg.type === 'success' ? 'toast-success' : msg.type === 'error' ? 'toast-error' : 'toast-info'"
        class="pointer-events-auto px-4 py-3 rounded-lg shadow-lg text-sm font-medium max-w-sm"
        :class="{
          'bg-green-600 text-white': msg.type === 'success',
          'bg-red-600 text-white': msg.type === 'error',
          'bg-blue-600 text-white': msg.type === 'info',
        }"
      >
        {{ msg.text }}
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active { transition: all 0.3s ease-out; }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from { opacity: 0; transform: translateX(100%); }
.toast-leave-to { opacity: 0; transform: translateX(100%); }
</style>
