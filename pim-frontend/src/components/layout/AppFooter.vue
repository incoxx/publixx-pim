<script setup>
// App-Footer: Versionsstand, Dienste-Ampel und 5 benutzerindividuelle Route-Presets.
// Presets werden per User-Preferences-API gespeichert (geraeteübergreifend).
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import adminApi from '@/api/admin'
import userPreferencesApi from '@/api/userPreferences'
import { X, Plus } from 'lucide-vue-next'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// ─── Dienste-Status ───────────────────────────────────────────────────────────
const status = ref(null)
let pollTimer = null

onMounted(() => {
  loadStatus()
  loadPresets()
  pollTimer = setInterval(loadStatus, 60000)
})

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer)
  document.removeEventListener('click', onClickOutside)
})

async function loadStatus() {
  try {
    const { data } = await adminApi.getFooterStatus()
    status.value = data.data || null
  } catch { /* rein informativ */ }
}

const versionLabel = computed(() => {
  const v = status.value?.version
  if (!v?.commit) return null
  const parts = [v.branch, v.commit].filter(Boolean).join(' @ ')
  return v.date ? `${parts} · ${v.date}` : parts
})

const activeServices = computed(() =>
  (status.value?.services || []).filter(s => s.status !== 'not_configured')
)

const overallLabel = computed(() => {
  const overall = status.value?.overall
  if (overall === 'ok') return t('Alle Dienste aktiv')
  if (overall === 'warn') return t('Dienste eingeschränkt')
  if (overall === 'error') {
    const broken = activeServices.value.filter(s => s.status !== 'running').map(s => s.name)
    return broken.length === 1
      ? t('{name} gestört', { name: broken[0] })
      : t('{count} Dienste gestört', { count: broken.length })
  }
  return ''
})

function dotColor(s) {
  if (s === 'running') return 'bg-emerald-400'
  if (s === 'paused' || s === 'unknown') return 'bg-amber-400'
  return 'bg-red-400'
}

function overallColor() {
  const o = status.value?.overall
  if (o === 'ok') return 'bg-emerald-400'
  if (o === 'warn') return 'bg-amber-400'
  return 'bg-red-400'
}

function openSystemSettings(e) {
  e.stopPropagation()
  router.push({ path: '/settings', query: { tab: 'system' } })
}

// ─── Presets ─────────────────────────────────────────────────────────────────
// Jeder Slot: { path, label } oder null (leer)
const SLOT_COUNT = 5
const presets = ref(Array(SLOT_COUNT).fill(null))
let saveTimer = null

async function loadPresets() {
  try {
    const { data } = await userPreferencesApi.get('footer_presets')
    const saved = data?.data?.slots || data?.slots || []
    presets.value = Array.from({ length: SLOT_COUNT }, (_, i) => saved[i] ?? null)
  } catch { /* neu → leer */ }
}

function savePresets() {
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(async () => {
    try {
      await userPreferencesApi.update('footer_presets', { slots: presets.value })
    } catch { /* ignorieren */ }
  }, 500)
}

// ─── Hover-Edit-Overlay ──────────────────────────────────────────────────────
// hoveredSlot: welcher Slot zeigt das Edit-Icon?
const hoveredSlot = ref(null)

// Aktives Kontextmenü
const menuSlot = ref(null)      // Index oder null
const menuRef = ref(null)

function openMenu(idx, e) {
  e.stopPropagation()
  menuSlot.value = idx
  nextTick(() => {
    document.addEventListener('click', onClickOutside, { once: true })
  })
}

function onClickOutside() {
  menuSlot.value = null
}

function assignSlot(idx) {
  presets.value[idx] = {
    path: route.fullPath,
    label: route.meta?.title || document.title || route.path,
  }
  menuSlot.value = null
  savePresets()
}

function clearSlot(idx) {
  presets.value[idx] = null
  menuSlot.value = null
  savePresets()
}

function navigateTo(preset) {
  if (preset?.path) router.push(preset.path)
}

// Slot-Label kürzen: maximal 12 Zeichen
function slotLabel(label) {
  if (!label) return ''
  return label.length > 12 ? label.slice(0, 11) + '…' : label
}
</script>

<template>
  <footer
    v-if="status"
    class="sticky bottom-0 z-20 flex items-center h-7 px-3 sm:px-6 text-[11px] bg-[var(--pim-toolbar-bg)]/90 backdrop-blur-md border-t border-[var(--pim-toolbar-border)] select-none gap-3"
    :style="{ color: 'var(--pim-toolbar-text)' }"
  >

    <!-- Versionsstand (klickbar → System) -->
    <button
      class="flex items-center gap-1.5 opacity-70 hover:opacity-100 transition-opacity min-w-0 shrink-0"
      :title="t('Einstellungen > System') + '\n' + (versionLabel || '')"
      @click="openSystemSettings"
    >
      <span class="font-semibold">anyPIM</span>
      <span v-if="versionLabel" class="hidden lg:inline truncate max-w-[14rem]">{{ versionLabel }}</span>
    </button>

    <!-- Trennlinie -->
    <span class="opacity-20 shrink-0">|</span>

    <!-- ─── Preset-Slots ──────────────────────────────────────────────── -->
    <div class="flex items-center gap-1 flex-1 min-w-0">
      <div
        v-for="(preset, idx) in presets"
        :key="idx"
        class="relative"
        @mouseenter="hoveredSlot = idx"
        @mouseleave="hoveredSlot = null"
      >
        <!-- Belegter Slot -->
        <button
          v-if="preset"
          class="flex items-center gap-1 h-5 px-2 rounded text-[11px] font-medium transition-colors max-w-[7rem]"
          :class="route.fullPath === preset.path
            ? 'bg-white/20 opacity-100'
            : 'bg-white/5 hover:bg-white/15 opacity-80 hover:opacity-100'"
          :title="t(preset.label) + '\n' + t('(Klick: öffnen · Edit-Icon: ändern)')"
          @click="navigateTo(preset)"
        >
          <span class="text-[9px] opacity-50 shrink-0 font-mono">P{{ idx + 1 }}</span>
          <span class="truncate">{{ slotLabel(t(preset.label)) }}</span>
        </button>

        <!-- Leerer Slot -->
        <button
          v-else
          class="flex items-center justify-center h-5 w-8 rounded text-[11px] transition-colors opacity-30 hover:opacity-70 border border-dashed border-current"
          :title="`P${idx + 1} — ${t('Leer')}\n(${t('Edit-Icon: aktuelle Seite zuweisen')})`"
          @click="assignSlot(idx)"
        >
          <span class="text-[9px] font-mono">P{{ idx + 1 }}</span>
        </button>

        <!-- Edit-Icon (erscheint beim Hover) -->
        <button
          v-if="hoveredSlot === idx"
          class="absolute -top-1 -right-1 w-3.5 h-3.5 rounded-full bg-[var(--pim-toolbar-bg)] border border-[var(--pim-toolbar-border)] flex items-center justify-center z-10 opacity-90 hover:opacity-100 transition-opacity"
          :title="preset ? t('Slot bearbeiten') : t('Aktuelle Seite zuweisen')"
          @click.stop="openMenu(idx, $event)"
        >
          <svg class="w-2 h-2" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M8 2l2 2-6 6H2V8l6-6z"/>
          </svg>
        </button>

        <!-- Kontextmenü -->
        <div
          v-if="menuSlot === idx"
          ref="menuRef"
          class="absolute bottom-7 left-0 w-44 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-xl py-1 z-50 text-[var(--color-text-primary)]"
          @click.stop
        >
          <div class="px-3 py-1.5 text-[10px] text-[var(--color-text-tertiary)] border-b border-[var(--color-border)] font-medium">
            {{ t('Slot P') }}{{ idx + 1 }}
          </div>
          <button
            class="w-full px-3 py-1.5 text-left text-xs hover:bg-[var(--color-bg)] transition-colors flex items-center gap-2"
            @click="assignSlot(idx)"
          >
            <Plus class="w-3 h-3 shrink-0" :stroke-width="2" />
            {{ t('Aktuelle Seite zuweisen') }}
            <span class="ml-auto text-[10px] text-[var(--color-text-tertiary)] truncate max-w-[6rem]">{{ slotLabel(route.meta?.title ? t(route.meta.title) : route.path) }}</span>
          </button>
          <button
            v-if="preset"
            class="w-full px-3 py-1.5 text-left text-xs text-[var(--color-error)] hover:bg-[var(--color-error)]/10 transition-colors flex items-center gap-2"
            @click="clearSlot(idx)"
          >
            <X class="w-3 h-3 shrink-0" :stroke-width="2" />
            {{ t('Slot leeren') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Trennlinie -->
    <span class="opacity-20 shrink-0 hidden sm:inline">|</span>

    <!-- Dienste-Ampel (rechts) -->
    <div class="hidden sm:flex items-center gap-3 shrink-0">
      <div class="hidden lg:flex items-center gap-2">
        <span
          v-for="svc in activeServices"
          :key="svc.name"
          class="flex items-center gap-1 opacity-70"
          :title="`${svc.name}: ${svc.status}`"
        >
          <span class="w-1.5 h-1.5 rounded-full" :class="dotColor(svc.status)"></span>
          <span class="hidden xl:inline">{{ svc.name }}</span>
        </span>
      </div>
      <button
        class="flex items-center gap-1.5 font-medium opacity-80 hover:opacity-100 transition-opacity"
        :title="t('Einstellungen > System öffnen')"
        @click="openSystemSettings"
      >
        <span class="w-2 h-2 rounded-full" :class="overallColor()"></span>
        {{ overallLabel }}
      </button>
    </div>

  </footer>
</template>
