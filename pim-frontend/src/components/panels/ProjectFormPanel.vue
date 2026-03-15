<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import projectsApi from '@/api/projects'
import teamsApi from '@/api/teams'
import usersApi from '@/api/users'
import PimForm from '@/components/shared/PimForm.vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
  project: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const availableTeams = ref([])
const availableUsers = ref([])
const selectedTeamIds = ref([])

const isEdit = computed(() => !!props.project)

const formData = ref({
  name: props.project?.name || '',
  description: props.project?.description || '',
  start_date: props.project?.start_date || '',
  end_date: props.project?.end_date || '',
  manager_id: props.project?.manager_id || '',
  status: props.project?.status || 'planning',
})

const fields = computed(() => [
  { key: 'name', label: 'Projektname', type: 'text', required: true },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  { key: 'start_date', label: 'Startdatum', type: 'date', required: true },
  { key: 'end_date', label: 'Enddatum', type: 'date', required: true },
  {
    key: 'manager_id', label: 'Projektmanager', type: 'select',
    options: availableUsers.value.map(u => ({ value: u.id, label: u.name })),
  },
  {
    key: 'status', label: 'Status', type: 'select',
    options: [
      { value: 'planning', label: 'Planung' },
      { value: 'active', label: 'Aktiv' },
      { value: 'completed', label: 'Abgeschlossen' },
      { value: 'on_hold', label: 'Pausiert' },
    ],
  },
])

function toggleTeam(id) {
  const idx = selectedTeamIds.value.indexOf(id)
  if (idx >= 0) {
    selectedTeamIds.value.splice(idx, 1)
  } else {
    selectedTeamIds.value.push(id)
  }
}

async function loadData() {
  try {
    const [teamsRes, usersRes] = await Promise.all([
      teamsApi.list({ per_page: 200 }),
      usersApi.list({ per_page: 200 }),
    ])
    availableTeams.value = teamsRes.data.data || teamsRes.data
    availableUsers.value = usersRes.data.data || usersRes.data

    if (isEdit.value) {
      const { data: detail } = await projectsApi.get(props.project.id)
      const p = detail.data || detail
      selectedTeamIds.value = (p.teams || []).map(t => t.id)
    }
  } catch (e) {
    console.error('Failed to load project data', e)
  }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data, team_ids: selectedTeamIds.value }
  try {
    if (isEdit.value) {
      await projectsApi.update(props.project.id, payload)
    } else {
      await projectsApi.create(payload)
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => loadData())
</script>

<template>
  <div class="p-4 space-y-6">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Projekt bearbeiten' : 'Neues Projekt' }}
    </h3>

    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />

    <!-- Team-Zuordnung -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Teams</h4>

      <div v-if="selectedTeamIds.length" class="flex flex-wrap gap-1.5 mb-3">
        <span
          v-for="tid in selectedTeamIds"
          :key="tid"
          class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] border border-[var(--color-accent)]/20"
        >
          {{ availableTeams.find(t => t.id === tid)?.name || tid }}
          <button class="hover:text-[var(--color-error)]" @click="toggleTeam(tid)">
            <X class="w-3 h-3" :stroke-width="2" />
          </button>
        </span>
      </div>

      <div class="max-h-40 overflow-y-auto border border-[var(--color-border)] rounded">
        <button
          v-for="team in availableTeams"
          :key="team.id"
          :class="[
            'w-full flex items-center gap-2 px-3 py-2 text-xs hover:bg-[var(--color-bg)] transition-colors cursor-pointer text-left',
            selectedTeamIds.includes(team.id) ? 'bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]' : ''
          ]"
          @click="toggleTeam(team.id)"
        >
          <input type="checkbox" :checked="selectedTeamIds.includes(team.id)" class="pointer-events-none" />
          <span class="text-[var(--color-text-primary)]">{{ team.name }}</span>
          <span v-if="team.users_count" class="text-[var(--color-text-tertiary)]">({{ team.users_count }} Mitglieder)</span>
        </button>
      </div>
      <p v-if="availableTeams.length === 0" class="text-xs text-[var(--color-text-tertiary)] mt-1">Keine Teams vorhanden.</p>
    </div>
  </div>
</template>
