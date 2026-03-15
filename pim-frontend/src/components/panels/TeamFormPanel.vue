<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import teamsApi from '@/api/teams'
import usersApi from '@/api/users'
import PimForm from '@/components/shared/PimForm.vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
  team: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const availableUsers = ref([])
const selectedUserIds = ref([])

const isEdit = computed(() => !!props.team)

const formData = ref({
  name: props.team?.name || '',
  description: props.team?.description || '',
})

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
])

async function loadData() {
  try {
    const { data } = await usersApi.list({ per_page: 200 })
    availableUsers.value = data.data || data

    if (isEdit.value) {
      const { data: detail } = await teamsApi.get(props.team.id)
      const t = detail.data || detail
      selectedUserIds.value = (t.users || []).map(u => u.id)
    }
  } catch (e) {
    console.error('Failed to load team data', e)
  }
}

function toggleUser(id) {
  const idx = selectedUserIds.value.indexOf(id)
  if (idx >= 0) {
    selectedUserIds.value.splice(idx, 1)
  } else {
    selectedUserIds.value.push(id)
  }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data, user_ids: selectedUserIds.value }
  try {
    if (isEdit.value) {
      await teamsApi.update(props.team.id, payload)
    } else {
      await teamsApi.create(payload)
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
      {{ isEdit ? 'Team bearbeiten' : 'Neues Team' }}
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

    <!-- Mitglieder -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Mitglieder</h4>

      <!-- Ausgewählte Mitglieder -->
      <div v-if="selectedUserIds.length" class="flex flex-wrap gap-1.5 mb-3">
        <span
          v-for="uid in selectedUserIds"
          :key="uid"
          class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] border border-[var(--color-accent)]/20"
        >
          {{ availableUsers.find(u => u.id === uid)?.name || uid }}
          <button class="hover:text-[var(--color-error)]" @click="toggleUser(uid)">
            <X class="w-3 h-3" :stroke-width="2" />
          </button>
        </span>
      </div>

      <!-- Verfügbare Benutzer -->
      <div class="max-h-48 overflow-y-auto border border-[var(--color-border)] rounded">
        <button
          v-for="user in availableUsers"
          :key="user.id"
          :class="[
            'w-full flex items-center gap-2 px-3 py-2 text-xs hover:bg-[var(--color-bg)] transition-colors cursor-pointer text-left',
            selectedUserIds.includes(user.id) ? 'bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]' : ''
          ]"
          @click="toggleUser(user.id)"
        >
          <input type="checkbox" :checked="selectedUserIds.includes(user.id)" class="pointer-events-none" />
          <span class="text-[var(--color-text-primary)]">{{ user.name }}</span>
          <span class="text-[var(--color-text-tertiary)]">{{ user.email }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
