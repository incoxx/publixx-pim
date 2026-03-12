<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import accessLinksApi from '@/api/accessLinks'
import { roles } from '@/api/users'
import PimForm from '@/components/shared/PimForm.vue'
import { Copy, Check } from 'lucide-vue-next'

const props = defineProps({
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const rolesList = ref([])
const createdLink = ref(null)
const copied = ref(false)

const formData = ref({
  name: '',
  role_id: '',
  expires_in_days: 7,
})

const fields = computed(() => [
  { key: 'name', label: 'Name', type: 'text', required: true, hint: 'Name der Person, die Zugang erhalten soll' },
  {
    key: 'role_id', label: 'Rolle', type: 'select', required: true,
    options: rolesList.value
      .filter(r => r.name !== 'Admin')
      .map(r => ({ value: r.id, label: r.name })),
  },
  {
    key: 'expires_in_days', label: 'Gültigkeit', type: 'select',
    options: [
      { value: 1, label: '1 Tag' },
      { value: 3, label: '3 Tage' },
      { value: 7, label: '7 Tage' },
      { value: 14, label: '14 Tage' },
      { value: 30, label: '30 Tage' },
    ],
  },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}

  try {
    const { data: result } = await accessLinksApi.create(data)
    createdLink.value = result.data
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

async function copyLink() {
  if (!createdLink.value?.url) return
  try {
    await navigator.clipboard.writeText(createdLink.value.url)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch { /* clipboard API not available */ }
}

function handleClose() {
  if (props.onSaved) props.onSaved()
  authStore.closePanel()
}

onMounted(async () => {
  try {
    const { data } = await roles.list()
    rolesList.value = data.data || data
  } catch { /* ignore */ }
})
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      Neuer Zugangslink
    </h3>

    <!-- Success: Show created link -->
    <div v-if="createdLink" class="space-y-4">
      <div class="p-3 rounded-md bg-[var(--color-success-light)] text-[var(--color-success)] text-sm">
        Zugangslink für <strong>{{ createdLink.name }}</strong> wurde erstellt.
      </div>

      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Link zum Verteilen</label>
        <div class="flex items-center gap-2">
          <input
            type="text"
            readonly
            :value="createdLink.url"
            class="pim-input flex-1 text-xs font-mono"
            @click="$event.target.select()"
          />
          <button class="pim-btn pim-btn-primary shrink-0" @click="copyLink" :title="copied ? 'Kopiert!' : 'Link kopieren'">
            <Check v-if="copied" class="w-4 h-4" :stroke-width="2" />
            <Copy v-else class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>
      </div>

      <div class="text-xs text-[var(--color-text-tertiary)] space-y-1">
        <p>Rolle: <strong>{{ createdLink.role?.name }}</strong></p>
        <p>Gültig bis: <strong>{{ createdLink.expires_at ? new Date(createdLink.expires_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '' }}</strong></p>
      </div>

      <div class="flex justify-end pt-2">
        <button class="pim-btn" @click="handleClose">Schließen</button>
      </div>
    </div>

    <!-- Form -->
    <PimForm
      v-else
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
