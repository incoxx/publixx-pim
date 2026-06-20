<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ChevronUp, ChevronDown, X, Plus, Save, RotateCcw, Check, LayoutDashboard } from 'lucide-vue-next'
import { roles as rolesApi } from '@/api/users'
import cockpitProfilesApi from '@/api/cockpitProfiles'
import { allTiles, widgetsForZone } from '@/config/cockpitCatalog'
import { resolveCockpitProfile, SYSTEM_DEFAULT_PROFILE } from '@/config/cockpitProfiles'

const ZONES = [
  { key: 'tiles', label: 'Schnellzugriff (Kacheln)', catalog: allTiles() },
  { key: 'workplace', label: 'Mein Arbeitsplatz', catalog: widgetsForZone('workplace') },
  { key: 'content', label: 'Medien & Content', catalog: widgetsForZone('content') },
  { key: 'kpis', label: 'Überblick (KPIs)', catalog: widgetsForZone('kpis') },
]

const roleList = ref([])
const selectedRoleId = ref('')
const loading = ref(false)
const saving = ref(false)
const savedFeedback = ref(false)

const layout = reactive({ tiles: [], workplace: [], content: [], kpis: [] })

const selectedRole = computed(() => roleList.value.find(r => r.id === selectedRoleId.value) || null)

function labelFor(zoneKey, id) {
  const zone = ZONES.find(z => z.key === zoneKey)
  return zone?.catalog.find(c => c.id === id)?.label || id
}

// Verfügbare (noch nicht gewählte) Bausteine je Zone
function availableFor(zoneKey) {
  const zone = ZONES.find(z => z.key === zoneKey)
  return zone.catalog.filter(c => !layout[zoneKey].includes(c.id))
}

function applyLayout(src) {
  for (const z of ZONES) {
    layout[z.key] = Array.isArray(src?.[z.key]) ? [...src[z.key]] : []
  }
}

async function selectRole(roleId) {
  selectedRoleId.value = roleId
  if (!roleId) return
  loading.value = true
  try {
    const { data } = await cockpitProfilesApi.get(roleId)
    const saved = data.data
    if (saved) {
      applyLayout(saved)
    } else {
      // Kein gespeichertes Layout → Code-Default der Rolle als Startpunkt
      applyLayout(resolveCockpitProfile(selectedRole.value?.name, null, null))
    }
  } catch {
    applyLayout(SYSTEM_DEFAULT_PROFILE)
  } finally {
    loading.value = false
  }
}

function addItem(zoneKey, id) {
  if (!layout[zoneKey].includes(id)) layout[zoneKey].push(id)
}
function removeItem(zoneKey, idx) {
  layout[zoneKey].splice(idx, 1)
}
function moveUp(zoneKey, idx) {
  if (idx <= 0) return
  const arr = layout[zoneKey]
  ;[arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]]
}
function moveDown(zoneKey, idx) {
  const arr = layout[zoneKey]
  if (idx >= arr.length - 1) return
  ;[arr[idx + 1], arr[idx]] = [arr[idx], arr[idx + 1]]
}

async function save() {
  if (!selectedRoleId.value) return
  saving.value = true
  try {
    await cockpitProfilesApi.update(selectedRoleId.value, {
      tiles: layout.tiles,
      workplace: layout.workplace,
      content: layout.content,
      kpis: layout.kpis,
    })
    savedFeedback.value = true
    setTimeout(() => { savedFeedback.value = false }, 2000)
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

async function resetRole() {
  if (!selectedRoleId.value) return
  saving.value = true
  try {
    await cockpitProfilesApi.remove(selectedRoleId.value)
    applyLayout(resolveCockpitProfile(selectedRole.value?.name, null, null))
    savedFeedback.value = true
    setTimeout(() => { savedFeedback.value = false }, 2000)
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await rolesApi.list()
    roleList.value = (data.data || data).map(r => ({ id: r.id, name: r.name }))
    if (roleList.value.length) selectRole(roleList.value[0].id)
  } catch { /* ignore */ }
})
</script>

<template>
  <div class="space-y-5 max-w-4xl">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
        <LayoutDashboard class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="2" />
        Cockpit-Layouts
      </h2>
      <div class="flex items-center gap-2">
        <span v-if="savedFeedback" class="text-xs text-green-600 flex items-center gap-1">
          <Check class="w-3.5 h-3.5" :stroke-width="2.5" /> Gespeichert
        </span>
        <button class="pim-btn pim-btn-secondary text-xs" :disabled="!selectedRoleId || saving" @click="resetRole">
          <RotateCcw class="w-3.5 h-3.5" :stroke-width="2" /> Zurücksetzen
        </button>
        <button class="pim-btn pim-btn-primary text-xs" :disabled="!selectedRoleId || saving" @click="save">
          <Save class="w-3.5 h-3.5" :stroke-width="2" /> Speichern
        </button>
      </div>
    </div>

    <p class="text-xs text-[var(--color-text-tertiary)]">
      Lege je Rolle fest, welche Bausteine das Cockpit zeigt und in welcher Reihenfolge.
      Persönliche Anpassungen der Nutzer:innen haben weiterhin Vorrang.
    </p>

    <!-- Rollen-Auswahl -->
    <div class="max-w-xs">
      <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Rolle</label>
      <select
        :value="selectedRoleId"
        class="pim-input w-full text-sm"
        @change="selectRole($event.target.value)"
      >
        <option v-for="r in roleList" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-10">
      <div class="w-5 h-5 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Zonen-Editor -->
    <div v-else-if="selectedRoleId" class="space-y-4">
      <div
        v-for="zone in ZONES"
        :key="zone.key"
        class="pim-card p-4"
      >
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">{{ zone.label }}</h3>

        <!-- Gewählte Bausteine (geordnet) -->
        <div v-if="layout[zone.key].length" class="space-y-1.5 mb-3">
          <div
            v-for="(id, idx) in layout[zone.key]"
            :key="id"
            class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)]"
          >
            <span class="text-[10px] text-[var(--color-text-tertiary)] w-4 text-center">{{ idx + 1 }}</span>
            <span class="text-xs text-[var(--color-text-primary)] flex-1">{{ labelFor(zone.key, id) }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-surface)] disabled:opacity-30" :disabled="idx === 0" title="Nach oben" @click="moveUp(zone.key, idx)">
              <ChevronUp class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <button class="p-0.5 rounded hover:bg-[var(--color-surface)] disabled:opacity-30" :disabled="idx === layout[zone.key].length - 1" title="Nach unten" @click="moveDown(zone.key, idx)">
              <ChevronDown class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <button class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-error)]" title="Entfernen" @click="removeItem(zone.key, idx)">
              <X class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
          </div>
        </div>
        <p v-else class="text-[11px] text-[var(--color-text-tertiary)] mb-3">Keine Bausteine in dieser Zone.</p>

        <!-- Hinzufügbare Bausteine -->
        <div v-if="availableFor(zone.key).length" class="flex flex-wrap gap-1.5">
          <button
            v-for="item in availableFor(zone.key)"
            :key="item.id"
            class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)] hover:text-[var(--color-accent)] transition"
            @click="addItem(zone.key, item.id)"
          >
            <Plus class="w-3 h-3" :stroke-width="2" /> {{ item.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
