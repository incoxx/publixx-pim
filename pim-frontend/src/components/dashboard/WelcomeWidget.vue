<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Upload, FileBarChart, Globe } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  welcome: { type: Object, default: null },
})

const router = useRouter()
const authStore = useAuthStore()

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Guten Morgen'
  if (hour < 18) return 'Guten Tag'
  return 'Guten Abend'
})

const userName = computed(() => {
  return props.welcome?.user_name || authStore.userName || ''
})

const firstName = computed(() => {
  return userName.value.split(' ')[0]
})

const contextLine = computed(() => {
  if (!props.welcome) return ''
  const parts = []
  if (props.welcome.open_tasks_count > 0) {
    parts.push(`${props.welcome.open_tasks_count} offene Aufgaben`)
  }
  if (props.welcome.weekly_edits > 0) {
    parts.push(`${props.welcome.weekly_edits} Produkte bearbeitet diese Woche`)
  }
  return parts.join(' \u00b7 ')
})

const quickActions = [
  { label: 'Produkt anlegen', icon: Plus, route: { name: 'products', query: { action: 'create' } }, color: 'var(--color-accent)' },
  { label: 'Import starten', icon: Upload, route: { name: 'imports' }, color: 'var(--color-success)' },
  { label: 'Reports', icon: FileBarChart, route: { name: 'reports' }, color: 'var(--color-warning)' },
  { label: 'Katalog-Vorschau', icon: Globe, route: { name: 'catalog' }, color: 'var(--color-info, #3b82f6)' },
]
</script>

<template>
  <div class="pim-card p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-semibold text-[var(--color-text-primary)]">
          {{ greeting }}, {{ firstName }}
        </h1>
        <p v-if="contextLine" class="text-sm text-[var(--color-text-tertiary)] mt-1">
          {{ contextLine }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="action in quickActions"
          :key="action.label"
          class="pim-btn pim-btn-ghost text-xs flex items-center gap-1.5 px-3 py-2 rounded-lg border border-[var(--color-border)] hover:border-[var(--color-accent)] transition-colors"
          @click="router.push(action.route)"
        >
          <component :is="action.icon" class="w-3.5 h-3.5" :style="{ color: action.color }" :stroke-width="2" />
          <span>{{ action.label }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
