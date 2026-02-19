<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  Search, Package, GitBranch, Sliders, Database,
  Upload, Download, Image, DollarSign, Users, Settings, Plus,
} from 'lucide-vue-next'

const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const inputRef = ref(null)
const query = ref('')
const selectedIndex = ref(0)

const allItems = computed(() => [
  { id: 'n1', icon: Package, label: t('nav.products'), action: () => router.push('/products'), section: 'navigation' },
  { id: 'n2', icon: GitBranch, label: t('nav.hierarchies'), action: () => router.push('/hierarchies'), section: 'navigation' },
  { id: 'n3', icon: Sliders, label: t('nav.attributes'), action: () => router.push('/attributes'), section: 'navigation' },
  { id: 'n4', icon: Database, label: t('nav.valueLists'), action: () => router.push('/value-lists'), section: 'navigation' },
  { id: 'n5', icon: Upload, label: t('nav.imports'), action: () => router.push('/imports'), section: 'navigation' },
  { id: 'n6', icon: Download, label: t('nav.exports'), action: () => router.push('/exports'), section: 'navigation' },
  { id: 'n7', icon: Image, label: t('nav.media'), action: () => router.push('/media'), section: 'navigation' },
  { id: 'n8', icon: DollarSign, label: t('nav.prices'), action: () => router.push('/prices'), section: 'navigation' },
  { id: 'n9', icon: Users, label: t('nav.users'), action: () => router.push('/users'), section: 'navigation' },
  { id: 'n10', icon: Settings, label: t('nav.settings'), action: () => router.push('/settings'), section: 'navigation' },
  { id: 'a1', icon: Plus, label: t('product.newProduct'), action: () => router.push('/products?new=1'), section: 'actions' },
  { id: 'a2', icon: Plus, label: t('attribute.newAttribute'), action: () => router.push('/attributes?new=1'), section: 'actions' },
  { id: 'a3', icon: Upload, label: t('import.uploadFile'), action: () => router.push('/imports'), section: 'actions' },
])

const filtered = computed(() => {
  if (!query.value.trim()) return allItems.value
  const q = query.value.toLowerCase()
  return allItems.value.filter(i => i.label.toLowerCase().includes(q))
})

const flatList = computed(() => filtered.value)

const groupedResults = computed(() => {
  const groups = {}
  for (const item of filtered.value) {
    if (!groups[item.section]) groups[item.section] = []
    groups[item.section].push(item)
  }
  return groups
})

watch(() => authStore.commandPaletteOpen, async (open) => {
  if (open) {
    query.value = ''
    selectedIndex.value = 0
    await nextTick()
    inputRef.value?.focus()
  }
})

function close() {
  authStore.commandPaletteOpen = false
}

function execute(item) {
  item.action()
  close()
}

function globalIndex(item) {
  return flatList.value.indexOf(item)
}

function handleKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, flatList.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const item = flatList.value[selectedIndex.value]
    if (item) execute(item)
  } else if (e.key === 'Escape') {
    close()
  }
}

const sectionLabels = { navigation: 'Navigation', actions: 'Aktionen' }
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="authStore.commandPaletteOpen"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[20vh]"
      >
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />
        <div
          class="relative w-full max-w-[540px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden"
          @keydown="handleKeydown"
        >
          <div class="flex items-center gap-3 px-4 py-3 border-b border-[var(--color-border)]">
            <Search class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
            <input
              ref="inputRef"
              v-model="query"
              :placeholder="t('cmd.placeholder')"
              class="flex-1 bg-transparent text-sm text-[var(--color-text-primary)] placeholder-[var(--color-text-tertiary)] outline-none"
            />
            <span class="pim-kbd text-[10px]">ESC</span>
          </div>
          <div class="max-h-[360px] overflow-y-auto py-2">
            <template v-if="flatList.length === 0">
              <p class="px-4 py-8 text-center text-sm text-[var(--color-text-tertiary)]">
                {{ t('cmd.noResults') }}
              </p>
            </template>
            <template v-else>
              <div v-for="(items, section) in groupedResults" :key="section">
                <p class="px-4 py-1 text-[10px] font-medium uppercase tracking-wider text-[var(--color-text-tertiary)]">
                  {{ sectionLabels[section] || section }}
                </p>
                <button
                  v-for="item in items"
                  :key="item.id"
                  :class="[
                    'w-full flex items-center gap-3 px-4 py-2 text-[13px] transition-colors cursor-pointer',
                    globalIndex(item) === selectedIndex
                      ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)]'
                      : 'text-[var(--color-text-primary)] hover:bg-[var(--color-bg)]',
                  ]"
                  @click="execute(item)"
                  @mouseenter="selectedIndex = globalIndex(item)"
                >
                  <component :is="item.icon" class="w-4 h-4 shrink-0 opacity-60" :stroke-width="1.75" />
                  <span class="flex-1 text-left">{{ item.label }}</span>
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
