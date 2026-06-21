<script setup>
import { ref, computed, onMounted } from 'vue'
import { Save, RotateCcw, Check, LayoutDashboard } from 'lucide-vue-next'
import { roles as rolesApi } from '@/api/users'
import cockpitProfilesApi from '@/api/cockpitProfiles'
import { resolveCockpitProfile, SYSTEM_DEFAULT_PROFILE } from '@/config/cockpitProfiles'
import CockpitLayoutEditor from '@/components/cockpit/CockpitLayoutEditor.vue'

const roleList = ref([])
const selectedRoleId = ref('')
const loading = ref(false)
const saving = ref(false)
const savedFeedback = ref(false)

const layout = ref({ tiles: [], workplace: [], content: [], kpis: [] })

const selectedRole = computed(() => roleList.value.find(r => r.id === selectedRoleId.value) || null)

function flash() {
  savedFeedback.value = true
  setTimeout(() => { savedFeedback.value = false }, 2000)
}

async function selectRole(roleId) {
  selectedRoleId.value = roleId
  if (!roleId) return
  loading.value = true
  try {
    const { data } = await cockpitProfilesApi.get(roleId)
    layout.value = data.data
      ? { ...data.data }
      : { ...resolveCockpitProfile(selectedRole.value?.name, null, null) }
  } catch {
    layout.value = { ...SYSTEM_DEFAULT_PROFILE }
  } finally {
    loading.value = false
  }
}

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
    flash()
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

async function resetRole() {
  if (!selectedRoleId.value) return
  saving.value = true
  try {
    await cockpitProfilesApi.remove(selectedRoleId.value)
    layout.value = { ...resolveCockpitProfile(selectedRole.value?.name, null, null) }
    flash()
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

    <CockpitLayoutEditor v-else-if="selectedRoleId" v-model="layout" />
  </div>
</template>
