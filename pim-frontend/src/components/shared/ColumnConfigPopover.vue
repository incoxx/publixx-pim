<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Columns3, ArrowUp, ArrowDown, RotateCcw } from 'lucide-vue-next'

const props = defineProps({
  allColumns: { type: Array, required: true },
  visibleKeys: { type: Array, required: true },
})

const emit = defineEmits(['toggle', 'move', 'reset'])

const open = ref(false)

function isVisible(key) {
  return props.visibleKeys.includes(key)
}

function toggle(key) {
  emit('toggle', key)
}

function move(key, direction) {
  emit('move', key, direction)
}

function onEscape(e) {
  if (e.key === 'Escape' && open.value) {
    open.value = false
  }
}

onMounted(() => window.addEventListener('keydown', onEscape))
onUnmounted(() => window.removeEventListener('keydown', onEscape))
</script>

<template>
  <div class="relative column-config-popover">
    <button
      class="pim-btn pim-btn-secondary py-2 px-3"
      @click="open = !open"
      title="Spalten konfigurieren"
    >
      <Columns3 class="w-4 h-4" :stroke-width="1.75" />
      <span class="ml-1.5 text-xs hidden sm:inline">Spalten</span>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        class="fixed inset-0 z-40"
        @click="open = false"
      />
    </Teleport>

    <transition name="fade">
      <div
        v-if="open"
        class="absolute right-0 top-full mt-1 z-50 w-64 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg overflow-hidden"
      >
        <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)] bg-[var(--color-bg)]">
          <span class="text-[11px] font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider">Sichtbare Spalten</span>
          <button
            class="text-[10px] text-[var(--color-accent)] hover:underline flex items-center gap-1"
            @click="emit('reset')"
          >
            <RotateCcw class="w-3 h-3" :stroke-width="2" />
            Zurücksetzen
          </button>
        </div>
        <div class="max-h-64 overflow-y-auto p-1">
          <div
            v-for="col in allColumns"
            :key="col.key"
            class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--color-bg)] group"
          >
            <input
              type="checkbox"
              :checked="isVisible(col.key)"
              @change="toggle(col.key)"
              class="rounded border-[var(--color-border)] text-[var(--color-accent)] shrink-0"
            />
            <span class="text-xs text-[var(--color-text-primary)] flex-1">{{ col.label }}</span>
            <div v-if="isVisible(col.key)" class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                class="p-0.5 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]"
                @click.stop="move(col.key, 'up')"
                title="Nach oben"
              >
                <ArrowUp class="w-3 h-3" :stroke-width="2" />
              </button>
              <button
                class="p-0.5 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]"
                @click.stop="move(col.key, 'down')"
                title="Nach unten"
              >
                <ArrowDown class="w-3 h-3" :stroke-width="2" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
