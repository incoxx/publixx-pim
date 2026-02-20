<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import usersApi, { roles } from '@/api/users'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  user: { type: Object, default: null },
  rolesList: { type: Array, default: () => [] },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const isEdit = computed(() => !!props.user)

const formData = ref(
  props.user
    ? {
        name: props.user.name,
        email: props.user.email,
        password: '',
        role_ids: props.user.roles?.map(r => r.id) || [],
        language: props.user.language || 'de',
        is_active: props.user.is_active ?? true,
      }
    : {
        name: '',
        email: '',
        password: '',
        role_ids: [],
        language: 'de',
        is_active: true,
      }
)

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true },
  { key: 'email', label: 'E-Mail', type: 'email', required: true },
  {
    key: 'password', label: 'Passwort', type: 'text', required: !isEdit.value,
    hint: isEdit.value ? 'Leer lassen um Passwort beizubehalten' : 'Mindestens 8 Zeichen',
  },
  {
    key: 'role_ids', label: 'Rolle', type: 'select',
    options: props.rolesList.map(r => ({ value: r.id, label: r.name })),
  },
  {
    key: 'language', label: 'Sprache', type: 'select',
    options: [
      { value: 'de', label: 'Deutsch' },
      { value: 'en', label: 'English' },
      { value: 'fr', label: 'Français' },
    ],
  },
  { key: 'is_active', label: 'Aktiv', type: 'boolean' },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}

  const payload = { ...data }
  if (isEdit.value && !payload.password) {
    delete payload.password
  }
  // Convert single role_ids value to array if needed
  if (payload.role_ids && !Array.isArray(payload.role_ids)) {
    payload.role_ids = [payload.role_ids]
  }

  try {
    if (isEdit.value) {
      await usersApi.update(props.user.id, payload)
    } else {
      await usersApi.create(payload)
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
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Benutzer bearbeiten' : 'Neuer Benutzer' }}
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
  </div>
</template>
