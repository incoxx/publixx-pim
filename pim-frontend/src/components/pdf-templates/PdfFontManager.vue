<script setup>
import { ref, onMounted } from 'vue'
import { X, Upload, Trash2 } from 'lucide-vue-next'
import client from '@/api/client'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'fonts-changed'])

const fonts = ref([])
const loading = ref(false)
const uploading = ref(false)
const errorMsg = ref('')

// Upload-Formular
const familyName = ref('')
const fileRegular = ref(null)
const fileBold = ref(null)
const fileItalic = ref(null)
const fileBoldItalic = ref(null)

onMounted(() => {
  loadFonts()
})

async function loadFonts() {
  loading.value = true
  try {
    const res = await client.get('/pdf-fonts')
    fonts.value = res.data.data || []
  } catch (e) {
    console.warn('Fonts laden fehlgeschlagen:', e.message)
  } finally {
    loading.value = false
  }
}

async function uploadFont() {
  if (!familyName.value.trim() || !fileRegular.value) {
    errorMsg.value = 'Font-Name und Regular-Datei sind Pflicht.'
    return
  }

  errorMsg.value = ''
  uploading.value = true

  const formData = new FormData()
  formData.append('family_name', familyName.value.trim())
  formData.append('file_regular', fileRegular.value)
  if (fileBold.value) formData.append('file_bold', fileBold.value)
  if (fileItalic.value) formData.append('file_italic', fileItalic.value)
  if (fileBoldItalic.value) formData.append('file_bold_italic', fileBoldItalic.value)

  try {
    await client.post('/pdf-fonts', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    resetForm()
    await loadFonts()
    emit('fonts-changed')
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Upload fehlgeschlagen.'
  } finally {
    uploading.value = false
  }
}

async function deleteFont(id) {
  try {
    await client.delete(`/pdf-fonts/${id}`)
    await loadFonts()
    emit('fonts-changed')
  } catch (e) {
    errorMsg.value = 'Löschen fehlgeschlagen.'
  }
}

function resetForm() {
  familyName.value = ''
  fileRegular.value = null
  fileBold.value = null
  fileItalic.value = null
  fileBoldItalic.value = null
}

function onFileChange(variant, event) {
  const file = event.target.files?.[0] || null
  if (variant === 'regular') fileRegular.value = file
  else if (variant === 'bold') fileBold.value = file
  else if (variant === 'italic') fileItalic.value = file
  else if (variant === 'bold_italic') fileBoldItalic.value = file
}

function close() {
  emit('update:modelValue', false)
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50" @click="close" />

    <!-- Dialog -->
    <div class="relative bg-[var(--color-surface)] rounded-lg shadow-xl w-[600px] max-h-[80vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--color-border)]">
        <span class="font-semibold text-sm text-[var(--color-text-primary)]">Schriften verwalten</span>
        <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="close">
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto p-5 space-y-5">
        <!-- Vorhandene Fonts -->
        <div>
          <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">Eigene Schriften</div>
          <div v-if="loading" class="text-xs text-[var(--color-text-tertiary)]">Laden...</div>
          <div v-else-if="fonts.length === 0" class="text-xs text-[var(--color-text-tertiary)]">
            Noch keine eigenen Schriften hochgeladen.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="font in fonts"
              :key="font.id"
              class="flex items-center justify-between px-3 py-2 rounded border border-[var(--color-border)] bg-[var(--color-bg)]"
            >
              <div>
                <span class="text-sm font-medium text-[var(--color-text-primary)]">{{ font.family_name }}</span>
                <span class="text-[10px] text-[var(--color-text-tertiary)] ml-2">
                  {{ font.variants.join(', ') }}
                </span>
              </div>
              <button
                class="p-1 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] hover:bg-[var(--color-error-light)]"
                @click="deleteFont(font.id)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Upload -->
        <div class="border-t border-[var(--color-border)] pt-4">
          <div class="text-xs font-semibold text-[var(--color-text-secondary)] mb-3">Neue Schrift hochladen</div>

          <div v-if="errorMsg" class="text-xs text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-1.5 rounded mb-3">
            {{ errorMsg }}
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Schriftname *</label>
              <input v-model="familyName" type="text" class="pim-input text-xs w-full" placeholder="z.B. Meine Firma Sans" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Regular (Pflicht) *</label>
                <input type="file" accept=".ttf" class="text-[10px] w-full" @change="onFileChange('regular', $event)" />
              </div>
              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Bold</label>
                <input type="file" accept=".ttf" class="text-[10px] w-full" @change="onFileChange('bold', $event)" />
              </div>
              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Italic</label>
                <input type="file" accept=".ttf" class="text-[10px] w-full" @change="onFileChange('italic', $event)" />
              </div>
              <div>
                <label class="block text-[10px] text-[var(--color-text-tertiary)] mb-0.5">Bold Italic</label>
                <input type="file" accept=".ttf" class="text-[10px] w-full" @change="onFileChange('bold_italic', $event)" />
              </div>
            </div>

            <button
              class="pim-btn pim-btn-primary text-xs px-4 py-1.5"
              :disabled="uploading || !familyName.trim() || !fileRegular"
              @click="uploadFont"
            >
              <Upload class="w-3 h-3 mr-1 inline" />
              {{ uploading ? 'Wird hochgeladen...' : 'Hochladen' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
