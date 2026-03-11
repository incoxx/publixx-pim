<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import manufacturersApi from '@/api/manufacturers'
import mediaApi from '@/api/media'
import PimForm from '@/components/shared/PimForm.vue'
import { Upload, X } from 'lucide-vue-next'

const props = defineProps({
  manufacturer: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const logoPreview = ref(props.manufacturer?.logo_url || null)

const isEdit = computed(() => !!props.manufacturer)

const formData = ref(
  props.manufacturer
    ? {
        name: props.manufacturer.name || '',
        street: props.manufacturer.street || '',
        zip: props.manufacturer.zip || '',
        city: props.manufacturer.city || '',
        country: props.manufacturer.country || '',
        email: props.manufacturer.email || '',
        website: props.manufacturer.website || '',
        logo_media_id: props.manufacturer.logo_media_id || null,
      }
    : {
        name: '',
        street: '',
        zip: '',
        city: '',
        country: '',
        email: '',
        website: '',
        logo_media_id: null,
      }
)

const fields = computed(() => [
  { key: 'name', label: 'Firma', type: 'text', required: true },
  { key: 'street', label: 'Straße', type: 'text' },
  { key: 'zip', label: 'PLZ', type: 'text' },
  { key: 'city', label: 'Stadt', type: 'text' },
  { key: 'country', label: 'Land (ISO)', type: 'text', maxlength: 2, placeholder: 'z.B. DE, AT, CH' },
  { key: 'email', label: 'E-Mail', type: 'email' },
  { key: 'website', label: 'Webseite', type: 'url' },
])

async function uploadLogo(event) {
  const file = event.target.files?.[0]
  if (!file) return
  try {
    const { data } = await mediaApi.upload(file, { usage_purpose: 'manufacturer_logo' })
    const media = data.data || data
    formData.value.logo_media_id = media.id
    logoPreview.value = media.thumb_url || media.url || media.file_url
  } catch (e) {
    errors.value.logo_media_id = 'Logo-Upload fehlgeschlagen: ' + (e.response?.data?.message || e.message)
  }
  event.target.value = ''
}

function removeLogo() {
  formData.value.logo_media_id = null
  logoPreview.value = null
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data, logo_media_id: formData.value.logo_media_id }
  try {
    if (isEdit.value) {
      await manufacturersApi.update(props.manufacturer.id, payload)
    } else {
      await manufacturersApi.create(payload)
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
  <div class="p-4 space-y-6">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Hersteller bearbeiten' : 'Neuer Hersteller' }}
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

    <!-- Logo Upload -->
    <div class="border-t border-[var(--color-border)] pt-4">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Logo</h4>
      <div v-if="logoPreview" class="flex items-center gap-3 mb-2">
        <img :src="logoPreview" alt="Logo" class="w-16 h-16 object-contain rounded border border-[var(--color-border)] bg-white" />
        <button class="pim-btn pim-btn-sm text-[var(--color-error)]" @click="removeLogo">
          <X class="w-3.5 h-3.5" :stroke-width="2" />
          Entfernen
        </button>
      </div>
      <label class="pim-btn pim-btn-sm cursor-pointer inline-flex items-center gap-1.5">
        <Upload class="w-3.5 h-3.5" :stroke-width="2" />
        {{ logoPreview ? 'Logo ersetzen' : 'Logo hochladen' }}
        <input type="file" accept="image/*" class="hidden" @change="uploadLogo" />
      </label>
      <p v-if="errors.logo_media_id" class="text-xs text-[var(--color-error)] mt-1">{{ errors.logo_media_id }}</p>
    </div>
  </div>
</template>
