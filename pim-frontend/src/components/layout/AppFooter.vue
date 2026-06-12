<script setup>
// Statischer App-Footer: Versionsstand (Git) + Ampel-Status der Dienste.
// Gleiche Farben wie die Toolbar. Klick oeffnet Einstellungen > System.
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import adminApi from '@/api/admin'

const router = useRouter()

const status = ref(null)
let pollTimer = null

onMounted(() => {
  load()
  // Serverseitig 60s gecacht — haeufigeres Polling bringt nichts
  pollTimer = setInterval(load, 60000)
})

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer)
})

async function load() {
  try {
    const { data } = await adminApi.getFooterStatus()
    status.value = data.data || null
  } catch {
    // Footer ist rein informativ — Fehler still ignorieren
  }
}

const versionLabel = computed(() => {
  const v = status.value?.version
  if (!v?.commit) return null
  const parts = [v.branch, v.commit].filter(Boolean).join(' @ ')
  return v.date ? `${parts} · ${v.date}` : parts
})

// Dienste ohne 'not_configured' — die sind bewusst nicht aktiv
const activeServices = computed(() =>
  (status.value?.services || []).filter(s => s.status !== 'not_configured')
)

const overallLabel = computed(() => {
  const overall = status.value?.overall
  if (overall === 'ok') return 'Alle Dienste aktiv'
  if (overall === 'warn') return 'Dienste eingeschränkt'
  if (overall === 'error') {
    const broken = activeServices.value.filter(s => s.status !== 'running').map(s => s.name)
    return broken.length === 1 ? `${broken[0]} gestört` : `${broken.length} Dienste gestört`
  }
  return ''
})

function dotColor(s) {
  if (s === 'running') return 'bg-emerald-400'
  if (s === 'paused' || s === 'unknown') return 'bg-amber-400'
  return 'bg-red-400'
}

function overallColor() {
  const overall = status.value?.overall
  if (overall === 'ok') return 'bg-emerald-400'
  if (overall === 'warn') return 'bg-amber-400'
  return 'bg-red-400'
}

function openSystemSettings() {
  router.push({ path: '/settings', query: { tab: 'system' } })
}
</script>

<template>
  <footer
    v-if="status"
    class="sticky bottom-0 z-20 flex items-center justify-between h-7 px-3 sm:px-6 text-[11px] bg-[var(--pim-toolbar-bg)]/90 backdrop-blur-md border-t border-[var(--pim-toolbar-border)] cursor-pointer select-none"
    :style="{ color: 'var(--pim-toolbar-text)' }"
    title="Einstellungen > System öffnen"
    @click="openSystemSettings"
  >
    <!-- Links: Versionsstand -->
    <div class="flex items-center gap-1.5 opacity-80 min-w-0">
      <span class="font-semibold shrink-0">anyPIM</span>
      <span v-if="versionLabel" class="truncate">{{ versionLabel }}</span>
    </div>

    <!-- Rechts: Ampel pro Dienst + Gesamtstatus -->
    <div class="flex items-center gap-3 shrink-0">
      <div class="hidden sm:flex items-center gap-2">
        <span
          v-for="svc in activeServices"
          :key="svc.name"
          class="flex items-center gap-1 opacity-80"
          :title="`${svc.name}: ${svc.status}`"
        >
          <span class="w-1.5 h-1.5 rounded-full" :class="dotColor(svc.status)"></span>
          <span class="hidden lg:inline">{{ svc.name }}</span>
        </span>
      </div>
      <span class="flex items-center gap-1.5 font-medium">
        <span class="w-2 h-2 rounded-full" :class="overallColor()"></span>
        {{ overallLabel }}
      </span>
    </div>
  </footer>
</template>
