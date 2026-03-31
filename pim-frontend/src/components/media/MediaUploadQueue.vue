<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { Upload, Check, AlertCircle, RefreshCw, X, ChevronDown, ChevronUp, ArrowUpDown, Wrench } from 'lucide-vue-next'
import mediaApi from '@/api/media'

const props = defineProps({
  metadata: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['completed', 'file-uploaded'])

const MAX_CONCURRENT = 3
const AUTO_HIDE_DELAY = 8000

const queue = ref([])
const minimized = ref(false)
let autoHideTimer = null

// Datei-Status: pending | uploading | done | replaced | error
function createQueueItem(file) {
  return {
    id: crypto.randomUUID?.() ?? (Math.random().toString(36).slice(2) + Date.now().toString(36)),
    file,
    name: file.name,
    size: file.size,
    status: 'pending',
    percent: 0,
    error: null,
    result: null,
    replaced: false,
  }
}

function addFiles(files) {
  const newItems = Array.from(files).map(createQueueItem)
  queue.value = [...queue.value, ...newItems]
  minimized.value = false
  clearAutoHide()
  processQueue()
}

const activeCount = computed(() => queue.value.filter(i => i.status === 'uploading').length)
const pendingCount = computed(() => queue.value.filter(i => i.status === 'pending').length)
const doneCount = computed(() => queue.value.filter(i => i.status === 'done' || i.status === 'replaced' || i.status === 'restored').length)
const errorCount = computed(() => queue.value.filter(i => i.status === 'error').length)
const totalCount = computed(() => queue.value.length)
const allDone = computed(() => totalCount.value > 0 && pendingCount.value === 0 && activeCount.value === 0)

watch(allDone, (done) => {
  if (done) {
    emit('completed')
    startAutoHide()
  }
})

function startAutoHide() {
  clearAutoHide()
  autoHideTimer = setTimeout(() => {
    if (allDone.value && errorCount.value === 0) {
      queue.value = []
    }
  }, AUTO_HIDE_DELAY)
}

function clearAutoHide() {
  if (autoHideTimer) {
    clearTimeout(autoHideTimer)
    autoHideTimer = null
  }
}

onUnmounted(() => clearAutoHide())

async function processQueue() {
  while (activeCount.value < MAX_CONCURRENT && pendingCount.value > 0) {
    const next = queue.value.find(i => i.status === 'pending')
    if (!next) break
    next.status = 'uploading'
    uploadFile(next)
  }
}

async function uploadFile(item) {
  try {
    const { data } = await mediaApi.upload(
      item.file,
      { ...props.metadata },
      (progress) => {
        item.percent = progress.percent
      }
    )
    item.result = data.data || data
    item.replaced = data.replaced === true
    item.restoredBroken = data.replaced_broken === true
    item.status = item.restoredBroken ? 'restored' : item.replaced ? 'replaced' : 'done'
    item.percent = 100
    emit('file-uploaded', item.result)
  } catch (err) {
    item.status = 'error'
    item.error = err.response?.data?.message || err.message || 'Upload fehlgeschlagen'
  }
  processQueue()
}

function retryItem(item) {
  item.status = 'pending'
  item.percent = 0
  item.error = null
  clearAutoHide()
  processQueue()
}

function removeItem(item) {
  queue.value = queue.value.filter(i => i.id !== item.id)
}

function clearAll() {
  queue.value = []
  clearAutoHide()
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

defineExpose({ addFiles })
</script>

<template>
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="queue.length > 0"
        class="fixed bottom-4 right-4 z-50 w-96 max-w-[calc(100vw-2rem)] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-2xl overflow-hidden"
      >
        <!-- Header -->
        <div
          class="flex items-center justify-between px-4 py-2.5 bg-[var(--color-bg)] border-b border-[var(--color-border)] cursor-pointer select-none"
          @click="minimized = !minimized"
        >
          <div class="flex items-center gap-2 text-sm font-medium text-[var(--color-text-primary)]">
            <Upload class="w-4 h-4" :stroke-width="2" />
            <span v-if="allDone">
              {{ doneCount }} hochgeladen
              <template v-if="errorCount > 0">, {{ errorCount }} Fehler</template>
            </span>
            <span v-else>
              {{ doneCount }}/{{ totalCount }} hochgeladen
            </span>
          </div>
          <div class="flex items-center gap-1">
            <button
              v-if="allDone"
              class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]"
              @click.stop="clearAll"
              title="Schließen"
            >
              <X class="w-4 h-4" />
            </button>
            <component
              :is="minimized ? ChevronUp : ChevronDown"
              class="w-4 h-4 text-[var(--color-text-tertiary)]"
            />
          </div>
        </div>

        <!-- Queue-Liste -->
        <div v-if="!minimized" class="max-h-64 overflow-y-auto divide-y divide-[var(--color-border)]">
          <div
            v-for="item in queue"
            :key="item.id"
            class="flex items-center gap-3 px-4 py-2.5"
          >
            <!-- Status Icon -->
            <div class="shrink-0">
              <Check v-if="item.status === 'done'" class="w-4 h-4 text-[var(--color-success)]" :stroke-width="2.5" />
              <Wrench v-else-if="item.status === 'restored'" class="w-4 h-4 text-[var(--color-success)]" :stroke-width="2" />
              <ArrowUpDown v-else-if="item.status === 'replaced'" class="w-4 h-4 text-[var(--color-warning,#f59e0b)]" :stroke-width="2" />
              <AlertCircle v-else-if="item.status === 'error'" class="w-4 h-4 text-[var(--color-error)]" :stroke-width="2" />
              <div v-else-if="item.status === 'uploading'" class="w-4 h-4 rounded-full border-2 border-[var(--color-primary)] border-t-transparent animate-spin" />
              <div v-else class="w-4 h-4 rounded-full border-2 border-[var(--color-border)]" />
            </div>

            <!-- Datei-Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-xs text-[var(--color-text-primary)] truncate">{{ item.name }}</span>
                <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0">{{ formatSize(item.size) }}</span>
                <span
                  v-if="item.status === 'restored'"
                  class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-success)]/10 text-[var(--color-success)] shrink-0"
                >
                  repariert
                </span>
                <span
                  v-else-if="item.status === 'replaced'"
                  class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-warning,#f59e0b)]/10 text-[var(--color-warning,#f59e0b)] shrink-0"
                >
                  überschrieben
                </span>
              </div>

              <!-- Fortschrittsbalken -->
              <div v-if="item.status === 'uploading'" class="mt-1.5 h-1 rounded-full bg-[var(--color-bg)] overflow-hidden">
                <div
                  class="h-full rounded-full bg-[var(--color-primary)] transition-all duration-300"
                  :style="{ width: item.percent + '%' }"
                />
              </div>

              <!-- Fehlertext -->
              <p v-if="item.status === 'error'" class="text-[10px] text-[var(--color-error)] mt-0.5 truncate">
                {{ item.error }}
              </p>
            </div>

            <!-- Aktionen -->
            <div class="shrink-0 flex items-center gap-1">
              <button
                v-if="item.status === 'error'"
                class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]"
                @click="retryItem(item)"
                title="Erneut versuchen"
              >
                <RefreshCw class="w-3.5 h-3.5" />
              </button>
              <button
                v-if="item.status === 'done' || item.status === 'replaced' || item.status === 'restored' || item.status === 'error'"
                class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]"
                @click="removeItem(item)"
                title="Entfernen"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(20px);
  opacity: 0;
}
</style>
