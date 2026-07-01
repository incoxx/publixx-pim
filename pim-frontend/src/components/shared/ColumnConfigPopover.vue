<script setup>
import { ref, computed, watch, defineAsyncComponent, onMounted, onUnmounted, nextTick } from 'vue'
import { Columns3, ArrowUp, ArrowDown, RotateCcw, X, GripVertical } from 'lucide-vue-next'

// vue-draggable-plus lazy laden um zirkuläre Initialisierung zu vermeiden
const VueDraggable = defineAsyncComponent(() =>
  import('vue-draggable-plus').then(m => m.VueDraggable)
)

const props = defineProps({
  allColumns: { type: Array, required: true },
  visibleKeys: { type: Array, required: true },
})

const emit = defineEmits(['toggle', 'move', 'reset', 'reorder'])

const open = ref(false)
const isMobile = ref(false)
const btnRef = ref(null)
const popoverStyle = ref({})

function checkMobile() {
  isMobile.value = window.innerWidth < 640
}

function calcPosition() {
  if (isMobile.value || !btnRef.value) return
  const rect = btnRef.value.getBoundingClientRect()
  const popW = 288 // w-72 = 18rem = 288px
  let left = rect.right - popW
  if (left < 8) left = 8
  popoverStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 4}px`,
    left: `${left}px`,
    width: `${popW}px`,
  }
}

function doOpen() {
  checkMobile()
  open.value = !open.value
  if (open.value) nextTick(calcPosition)
}

// Sichtbare Spalten in ihrer aktuellen Reihenfolge (per Drag&Drop sortierbar)
const sortableVisible = ref([])

watch(
  () => [props.visibleKeys, props.allColumns],
  () => {
    sortableVisible.value = props.visibleKeys
      .map(key => props.allColumns.find(c => c.key === key))
      .filter(Boolean)
  },
  { immediate: true, deep: true }
)

function onDragEnd() {
  emit('reorder', sortableVisible.value.map(c => c.key))
}

const baseColumns = computed(() => props.allColumns.filter(c => !c.group && !isVisible(c.key)))
const groupedColumns = computed(() => {
  const groups = {}
  for (const col of props.allColumns) {
    if (col.group && !isVisible(col.key)) {
      if (!groups[col.group]) groups[col.group] = []
      groups[col.group].push(col)
    }
  }
  return groups
})

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
      ref="btnRef"
      class="pim-btn pim-btn-secondary py-2 px-3"
      @click="doOpen"
      title="Spalten konfigurieren"
    >
      <Columns3 class="w-4 h-4" :stroke-width="1.75" />
      <span class="ml-1.5 text-xs hidden sm:inline">Spalten</span>
    </button>

    <Teleport to="body">
      <!-- Backdrop -->
      <div
        v-if="open"
        class="fixed inset-0 z-40"
        :class="isMobile ? 'bg-black/30' : ''"
        @click="open = false"
      />

      <!-- Popover / Mobile bottom sheet -->
      <transition :name="isMobile ? 'slide-up' : 'fade'">
        <div
          v-if="open"
          :class="isMobile
            ? 'fixed inset-x-0 bottom-0 z-50 bg-[var(--color-surface)] rounded-t-2xl shadow-2xl max-h-[80vh] flex flex-col'
            : 'z-50 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg overflow-hidden'"
          :style="isMobile ? {} : popoverStyle"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)] bg-[var(--color-bg)] shrink-0"
               :class="isMobile ? 'rounded-t-2xl py-3' : ''"
          >
            <span class="text-[11px] font-semibold text-[var(--color-text-secondary)] uppercase tracking-wider"
                  :class="isMobile ? 'text-xs' : ''"
            >Sichtbare Spalten</span>
            <div class="flex items-center gap-2">
              <button
                class="text-[10px] text-[var(--color-accent)] hover:underline flex items-center gap-1"
                :class="isMobile ? 'text-xs' : ''"
                @click="emit('reset')"
              >
                <RotateCcw class="w-3 h-3" :stroke-width="2" />
                Zurücksetzen
              </button>
              <button
                v-if="isMobile"
                class="p-1 rounded-full hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]"
                @click="open = false"
              >
                <X class="w-5 h-5" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- Column list -->
          <div class="overflow-y-auto p-1" :class="isMobile ? 'flex-1' : 'max-h-80'">
            <!-- Sichtbare Spalten: per Drag&Drop sortierbar -->
            <VueDraggable
              v-model="sortableVisible"
              handle=".col-drag-handle"
              ghost-class="opacity-30"
              class="space-y-0"
              @end="onDragEnd"
            >
              <div
                v-for="col in sortableVisible"
                :key="col.key"
                class="flex items-center gap-2 px-2 rounded hover:bg-[var(--color-bg)] group"
                :class="isMobile ? 'py-2.5' : 'py-1.5'"
              >
                <GripVertical
                  class="col-drag-handle w-3.5 h-3.5 text-[var(--color-text-tertiary)] opacity-40 cursor-grab active:cursor-grabbing shrink-0"
                  :stroke-width="2"
                />
                <input
                  type="checkbox"
                  :checked="true"
                  @change="toggle(col.key)"
                  class="rounded border-[var(--color-border)] text-[var(--color-accent)] shrink-0"
                  :class="isMobile ? 'w-5 h-5' : ''"
                />
                <span class="text-[var(--color-text-primary)] flex-1 truncate" :class="isMobile ? 'text-sm' : 'text-xs'">{{ col.label }}</span>
                <span v-if="col.group" class="text-[9px] text-[var(--color-text-tertiary)] shrink-0">{{ col.group }}</span>
                <div class="flex items-center gap-0.5"
                     :class="isMobile ? '' : 'opacity-0 group-hover:opacity-100 transition-opacity'"
                >
                  <button
                    class="p-0.5 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]"
                    @click.stop="move(col.key, 'up')"
                    title="Nach oben"
                  >
                    <ArrowUp class="w-3 h-3" :stroke-width="2" :class="isMobile ? 'w-4 h-4' : ''" />
                  </button>
                  <button
                    class="p-0.5 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]"
                    @click.stop="move(col.key, 'down')"
                    title="Nach unten"
                  >
                    <ArrowDown class="w-3 h-3" :stroke-width="2" :class="isMobile ? 'w-4 h-4' : ''" />
                  </button>
                </div>
              </div>
            </VueDraggable>

            <!-- Weitere Spalten (ausgeblendet) -->
            <template v-if="baseColumns.length > 0 || Object.keys(groupedColumns).length > 0">
              <div class="flex items-center gap-2 px-2 pt-2 pb-1 mt-1 border-t border-[var(--color-border)]">
                <span class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider"
                      :class="isMobile ? 'text-xs' : ''"
                >Weitere Spalten</span>
              </div>
              <div
                v-for="col in baseColumns"
                :key="col.key"
                class="flex items-center gap-2 px-2 rounded hover:bg-[var(--color-bg)] group"
                :class="isMobile ? 'py-2.5' : 'py-1.5'"
              >
                <input
                  type="checkbox"
                  :checked="false"
                  @change="toggle(col.key)"
                  class="rounded border-[var(--color-border)] text-[var(--color-accent)] shrink-0"
                  :class="isMobile ? 'w-5 h-5' : ''"
                />
                <span class="text-[var(--color-text-primary)] flex-1" :class="isMobile ? 'text-sm' : 'text-xs'">{{ col.label }}</span>
              </div>

              <!-- Grouped columns (e.g. Attribute) -->
              <template v-for="(cols, groupName) in groupedColumns" :key="groupName">
                <div class="flex items-center gap-2 px-2 pt-2 pb-1 mt-1 border-t border-[var(--color-border)]">
                  <span class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider"
                        :class="isMobile ? 'text-xs' : ''"
                  >{{ groupName }}</span>
                </div>
                <div
                  v-for="col in cols"
                  :key="col.key"
                  class="flex items-center gap-2 px-2 rounded hover:bg-[var(--color-bg)] group"
                  :class="isMobile ? 'py-2.5' : 'py-1'"
                >
                  <input
                    type="checkbox"
                    :checked="false"
                    @change="toggle(col.key)"
                    class="rounded border-[var(--color-border)] text-[var(--color-accent)] shrink-0"
                    :class="isMobile ? 'w-5 h-5' : ''"
                  />
                  <span class="text-[var(--color-text-secondary)] flex-1 truncate" :class="isMobile ? 'text-sm' : 'text-[11px]'">{{ col.label }}</span>
                </div>
              </template>
            </template>
          </div>
        </div>
      </transition>
    </Teleport>
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
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.25s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
}
</style>
