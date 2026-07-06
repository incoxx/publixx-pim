<script setup>
import { ref, onMounted } from 'vue'
import { X, Plus, Save, Trash2, AlertCircle } from 'lucide-vue-next'
import mediaRenditionPresets from '@/api/mediaRenditionPresets'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const items = ref([])
const loading = ref(false)
const editId = ref(null)
const showForm = ref(false)
const saving = ref(false)
const errors = ref({})
const deleteTarget = ref(null)
const deleting = ref(false)

function emptyForm() {
  return {
    technical_name: '', name_de: '', name_en: '',
    channel: 'web', format: 'jpeg', colorspace: 'rgb', fit: 'contain',
    max_width: null, max_height: null, dpi: null, quality: 85,
    background_color: '', sort_order: 0, is_active: true,
  }
}

const form = ref(emptyForm())

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await mediaRenditionPresets.list()
    items.value = data.data || []
  } finally {
    loading.value = false
  }
}

function openForm(item = null) {
  errors.value = {}
  if (item) {
    editId.value = item.id
    form.value = {
      technical_name: item.technical_name,
      name_de: item.name_de,
      name_en: item.name_en || '',
      channel: item.channel,
      format: item.format,
      colorspace: item.colorspace,
      fit: item.fit,
      max_width: item.max_width,
      max_height: item.max_height,
      dpi: item.dpi,
      quality: item.quality,
      background_color: item.background_color || '',
      sort_order: item.sort_order,
      is_active: item.is_active,
    }
  } else {
    editId.value = null
    form.value = emptyForm()
  }
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  errors.value = {}
}

async function saveForm() {
  saving.value = true
  errors.value = {}
  try {
    const payload = { ...form.value }
    if (payload.background_color === '') payload.background_color = null
    if (editId.value) {
      await mediaRenditionPresets.update(editId.value, payload)
    } else {
      await mediaRenditionPresets.create(payload)
    }
    showForm.value = false
    await fetchItems()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    saving.value = false
  }
}

async function confirmDelete({ force }) {
  deleting.value = true
  try {
    await mediaRenditionPresets.delete(deleteTarget.value.id, { force })
    deleteTarget.value = null
    await fetchItems()
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  if (props.open) fetchItems()
})

function onOpenChange(isOpen) {
  if (isOpen) fetchItems()
}

defineExpose({ onOpenChange })
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="$emit('close')" />
      <div class="relative z-10 w-full max-w-[860px] max-h-[85vh] flex flex-col bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Rendition-Presets verwalten</h3>
          <button class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)]" @click="$emit('close')">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>

        <div class="overflow-auto p-4 space-y-4 flex-1">
          <div class="flex justify-end">
            <button class="pim-btn pim-btn-primary text-xs" @click="openForm()">
              <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neues Preset
            </button>
          </div>

          <!-- Formular -->
          <div v-if="showForm" class="pim-card p-4 space-y-3">
            <p v-if="errors._general" class="text-xs text-[var(--color-error)] flex items-center gap-1.5">
              <AlertCircle class="w-3.5 h-3.5" :stroke-width="2" /> {{ errors._general }}
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Technischer Name</label>
                <input class="pim-input text-xs" v-model="form.technical_name" :disabled="!!editId" />
                <p v-if="errors.technical_name" class="text-[10px] text-[var(--color-error)] mt-0.5">{{ errors.technical_name }}</p>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Name (DE)</label>
                <input class="pim-input text-xs" v-model="form.name_de" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Name (EN)</label>
                <input class="pim-input text-xs" v-model="form.name_en" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Kanal</label>
                <select class="pim-select text-xs" v-model="form.channel">
                  <option value="web">Web</option>
                  <option value="print">Print</option>
                  <option value="mobile">Mobile</option>
                  <option value="social">Social</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Format</label>
                <select class="pim-select text-xs" v-model="form.format">
                  <option value="jpeg">JPEG</option>
                  <option value="png">PNG</option>
                  <option value="webp">WebP</option>
                  <option value="gif">GIF</option>
                  <option value="tiff">TIFF</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Farbraum</label>
                <select class="pim-select text-xs" v-model="form.colorspace">
                  <option value="rgb">RGB</option>
                  <option value="cmyk">CMYK</option>
                  <option value="gray">Graustufen</option>
                </select>
                <p v-if="form.colorspace === 'cmyk' || form.format === 'tiff'" class="text-[10px] text-[var(--color-warning)] mt-0.5">
                  Erfordert ext-imagick auf dem Server
                </p>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Zuschnitt</label>
                <select class="pim-select text-xs" v-model="form.fit">
                  <option value="contain">Contain (einpassen)</option>
                  <option value="cover">Cover (Fokuspunkt-Crop)</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Max. Breite (px)</label>
                <input class="pim-input text-xs" type="number" v-model.number="form.max_width" placeholder="Original" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Max. Höhe (px)</label>
                <input class="pim-input text-xs" type="number" v-model.number="form.max_height" placeholder="Original" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">DPI</label>
                <input class="pim-input text-xs" type="number" v-model.number="form.dpi" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Qualität (1-100)</label>
                <input class="pim-input text-xs" type="number" min="1" max="100" v-model.number="form.quality" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Hintergrundfarbe</label>
                <input class="pim-input text-xs" v-model="form.background_color" placeholder="#FFFFFF" />
              </div>
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Sortierung</label>
                <input class="pim-input text-xs" type="number" v-model.number="form.sort_order" />
              </div>
              <div class="flex items-end pb-1.5">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="form.is_active" class="w-3.5 h-3.5 accent-[var(--color-primary)]" />
                  <span class="text-xs text-[var(--color-text-primary)]">Aktiv</span>
                </label>
              </div>
            </div>
            <div class="flex gap-2">
              <button class="pim-btn pim-btn-primary text-xs" :disabled="saving" @click="saveForm">
                <Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ saving ? 'Speichern…' : 'Speichern' }}
              </button>
              <button class="pim-btn pim-btn-secondary text-xs" @click="cancelForm">Abbrechen</button>
            </div>
          </div>

          <!-- Liste -->
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-[var(--color-border)] text-[10px] uppercase tracking-wide text-[var(--color-text-tertiary)]">
                <th class="text-left py-2">Preset</th>
                <th class="text-left py-2">Kanal</th>
                <th class="text-left py-2">Format</th>
                <th class="text-left py-2">Farbraum</th>
                <th class="text-left py-2">Max. Größe</th>
                <th class="text-center py-2">Aktiv</th>
                <th class="w-16"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading"><td colspan="7" class="py-6 text-center text-[var(--color-text-tertiary)]">Lädt…</td></tr>
              <tr v-else-if="items.length === 0"><td colspan="7" class="py-6 text-center text-[var(--color-text-tertiary)]">Keine Presets vorhanden</td></tr>
              <tr
                v-else
                v-for="item in items"
                :key="item.id"
                class="border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] cursor-pointer"
                @click="openForm(item)"
              >
                <td class="py-2">
                  <p class="text-[var(--color-text-primary)] font-medium">{{ item.name_de }}</p>
                  <p class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ item.technical_name }}</p>
                </td>
                <td class="py-2 text-[var(--color-text-secondary)]">{{ item.channel }}</td>
                <td class="py-2 text-[var(--color-text-secondary)]">{{ item.format.toUpperCase() }}</td>
                <td class="py-2 text-[var(--color-text-secondary)]">{{ item.colorspace.toUpperCase() }}</td>
                <td class="py-2 text-[var(--color-text-secondary)]">
                  {{ item.max_width ? `${item.max_width}×${item.max_height}px` : 'Original' }}
                </td>
                <td class="py-2 text-center">
                  <span class="pim-badge" :class="item.is_active ? 'bg-[var(--color-success)]/10 text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]'">
                    {{ item.is_active ? 'Ja' : 'Nein' }}
                  </span>
                </td>
                <td class="py-2" @click.stop>
                  <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="deleteTarget = item">
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <PimDeleteConfirmDialog
        :open="!!deleteTarget"
        title="Preset löschen?"
        :message="`Das Preset '${deleteTarget?.name_de || ''}' wird gelöscht. Bereits generierte Renditions dieses Presets bleiben als Dateien erhalten, verlieren aber die Preset-Zuordnung.`"
        entityType="media-rendition-presets"
        :entityId="deleteTarget?.id"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="deleteTarget = null"
      />
    </div>
  </Teleport>
</template>
