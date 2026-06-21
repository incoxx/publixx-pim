<script setup>
import { ref, computed, onMounted } from 'vue'
import { Save, Trash2, Check, LayoutDashboard } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import cockpitProfilesApi from '@/api/cockpitProfiles'
import { resolveCockpitProfile, SYSTEM_DEFAULT_PROFILE } from '@/config/cockpitProfiles'
import CockpitLayoutEditor from '@/components/cockpit/CockpitLayoutEditor.vue'

const authStore = useAuthStore()
const canEdit = computed(() => authStore.hasPermission('cockpit-layouts.edit'))

const roleList = ref([])
const selectedRoleId = ref('')
const loading = ref(false)
const saving = ref(false)
const savedFeedback = ref(false)
// Hat die aktuell gewählte Rolle ein eigenes (gespeichertes) Layout?
const hasCustomLayout = ref(false)

const layout = ref({ tiles: [], workplace: [], content: [], kpis: [] })

const selectedRole = computed(() => roleList.value.find(r => r.id === selectedRoleId.value) || null)

function flash() {
  savedFeedback.value = true
  setTimeout(() => { savedFeedback.value = false }, 2000)
}

function markCustom(roleId, value) {
  const entry = roleList.value.find(r => r.id === roleId)
  if (entry) entry.hasCustom = value
}

async function selectRole(roleId) {
  selectedRoleId.value = roleId
  if (!roleId) return
  loading.value = true
  try {
    const { data } = await cockpitProfilesApi.get(roleId)
    hasCustomLayout.value = !!data.data
    layout.value = data.data
      ? { ...data.data }
      : { ...resolveCockpitProfile(selectedRole.value?.name, null, null) }
  } catch {
    hasCustomLayout.value = false
    layout.value = { ...SYSTEM_DEFAULT_PROFILE }
  } finally {
    loading.value = false
  }
}

// Layout der Rolle anlegen bzw. aktualisieren.
async function save() {
  if (!selectedRoleId.value) return
  saving.value = true
  try {
    await cockpitProfilesApi.update(selectedRoleId.value, {
      tiles: layout.value.tiles || [],
      workplace: layout.value.workplace || [],
      content: layout.value.content || [],
      kpis: layout.value.kpis || [],
    })
    hasCustomLayout.value = true
    markCustom(selectedRoleId.value, true)
    flash()
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

// Eigenes Layout der Rolle löschen → Code-/Systemstandard greift wieder.
async function deleteLayout() {
  if (!selectedRoleId.value || !hasCustomLayout.value) return
  if (!window.confirm(`Eigenes Cockpit-Layout für "${selectedRole.value?.name}" löschen? Danach gilt wieder der Standard.`)) return
  saving.value = true
  try {
    await cockpitProfilesApi.remove(selectedRoleId.value)
    hasCustomLayout.value = false
    markCustom(selectedRoleId.value, false)
    layout.value = { ...resolveCockpitProfile(selectedRole.value?.name, null, null) }
    flash()
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await cockpitProfilesApi.roles()
    roleList.value = (data.data || data).map(r => ({
      id: r.id,
      name: r.name,
      hasCustom: !!r.has_custom_layout,
    }))
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
        <button
          v-if="canEdit"
          class="pim-btn pim-btn-secondary text-xs"
          :disabled="!selectedRoleId || saving || !hasCustomLayout"
          :title="hasCustomLayout ? 'Eigenes Layout löschen' : 'Diese Rolle nutzt den Standard'"
          @click="deleteLayout"
        >
          <Trash2 class="w-3.5 h-3.5" :stroke-width="2" /> Layout löschen
        </button>
        <button
          v-if="canEdit"
          class="pim-btn pim-btn-primary text-xs"
          :disabled="!selectedRoleId || saving"
          @click="save"
        >
          <Save class="w-3.5 h-3.5" :stroke-width="2" />
          {{ hasCustomLayout ? 'Speichern' : 'Layout anlegen' }}
        </button>
      </div>
    </div>

    <p class="text-xs text-[var(--color-text-tertiary)]">
      Lege je Rolle fest, welche Bausteine das Cockpit zeigt und in welcher Reihenfolge.
      Rollen ohne eigenes Layout nutzen den Standard. Persönliche Anpassungen der
      Nutzer:innen haben weiterhin Vorrang.
    </p>

    <!-- Rollen-Auswahl + Status -->
    <div class="flex flex-wrap items-end gap-3">
      <div class="max-w-xs flex-1 min-w-[12rem]">
        <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Rolle</label>
        <select
          :value="selectedRoleId"
          class="pim-input w-full text-sm"
          @change="selectRole($event.target.value)"
        >
          <option v-for="r in roleList" :key="r.id" :value="r.id">
            {{ r.name }}{{ r.hasCustom ? ' •' : '' }}
          </option>
        </select>
      </div>
      <span
        class="pim-badge text-[11px] px-2 py-1 rounded-full"
        :class="hasCustomLayout
          ? 'bg-[color-mix(in_srgb,var(--color-accent)_12%,transparent)] text-[var(--color-accent)]'
          : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]'"
      >
        {{ hasCustomLayout ? 'Eigenes Layout' : 'Standard (geerbt)' }}
      </span>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-10">
      <div class="w-5 h-5 border-2 border-[var(--color-accent)] border-t-transparent rounded-full animate-spin" />
    </div>

    <CockpitLayoutEditor
      v-else-if="selectedRoleId"
      v-model="layout"
      :class="{ 'pointer-events-none opacity-70': !canEdit }"
    />
  </div>
</template>
