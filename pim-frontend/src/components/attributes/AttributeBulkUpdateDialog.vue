<script setup>
import { ref, computed } from 'vue'
import { X } from 'lucide-vue-next'
import { useAttributeStore } from '@/stores/attributes'

const props = defineProps({
  open: { type: Boolean, default: false },
  selectedIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'updated'])

const store = useAttributeStore()
const saving = ref(false)
const error = ref(null)

// Each field has: enabled (checkbox), value
const fields = ref({
  is_translatable: { enabled: false, value: true },
  is_multipliable: { enabled: false, value: false },
  is_searchable: { enabled: false, value: true },
  is_mandatory: { enabled: false, value: false },
  is_unique: { enabled: false, value: false },
  is_inheritable: { enabled: false, value: true },
  is_variant_attribute: { enabled: false, value: false },
  is_internal: { enabled: false, value: false },
  attribute_type_id: { enabled: false, value: '' },
  status: { enabled: false, value: 'active' },
})

const boolFields = [
  { key: 'is_translatable', label: 'Übersetzbar' },
  { key: 'is_multipliable', label: 'Multipliziert' },
  { key: 'is_searchable', label: 'Suchbar' },
  { key: 'is_mandatory', label: 'Pflichtfeld' },
  { key: 'is_unique', label: 'Einzigartig' },
  { key: 'is_inheritable', label: 'Vererbbar' },
  { key: 'is_variant_attribute', label: 'Varianten-Attribut' },
  { key: 'is_internal', label: 'Intern' },
]

const attributeTypeOptions = computed(() =>
  store.types.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))
)

const hasEnabledFields = computed(() =>
  Object.values(fields.value).some(f => f.enabled)
)

function resetFields() {
  for (const key in fields.value) {
    fields.value[key].enabled = false
    if (key === 'attribute_type_id') fields.value[key].value = ''
    else if (key === 'status') fields.value[key].value = 'active'
    else fields.value[key].value = false
  }
  error.value = null
}

async function apply() {
  saving.value = true
  error.value = null
  try {
    const updateFields = {}
    for (const [key, field] of Object.entries(fields.value)) {
      if (field.enabled) {
        updateFields[key] = key === 'attribute_type_id'
          ? (field.value || null)
          : field.value
      }
    }
    await store.bulkUpdate(props.selectedIds, updateFields)
    resetFields()
    emit('updated')
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

function close() {
  resetFields()
  emit('close')
}
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="close" />
        <div class="relative bg-[var(--color-surface)] rounded-xl shadow-2xl border border-[var(--color-border)] w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--color-border)]">
            <div>
              <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Attribute Bulk Update</h3>
              <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">{{ selectedIds.length }} Attribute ausgewählt</p>
            </div>
            <button class="p-1 rounded hover:bg-[var(--color-bg)] transition-colors" @click="close">
              <X class="w-4 h-4 text-[var(--color-text-tertiary)]" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-5 py-4 space-y-3">
            <p class="text-[11px] text-[var(--color-text-tertiary)]">Nur angehakte Felder werden geändert.</p>

            <!-- Boolean fields -->
            <div v-for="bf in boolFields" :key="bf.key" class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="fields[bf.key].enabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">{{ bf.label }}</label>
              <select
                v-model="fields[bf.key].value"
                :disabled="!fields[bf.key].enabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!fields[bf.key].enabled ? 'opacity-40' : ''"
              >
                <option :value="true">Ja</option>
                <option :value="false">Nein</option>
              </select>
            </div>

            <!-- Divider -->
            <div class="border-t border-[var(--color-border)] my-2" />

            <!-- Attribute Group -->
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="fields.attribute_type_id.enabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">Attributgruppe</label>
              <select
                v-model="fields.attribute_type_id.value"
                :disabled="!fields.attribute_type_id.enabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!fields.attribute_type_id.enabled ? 'opacity-40' : ''"
              >
                <option value="">— Keine —</option>
                <option v-for="o in attributeTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>

            <!-- Status -->
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                v-model="fields.status.enabled"
                class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)] focus:ring-[var(--color-accent)]"
              />
              <label class="text-xs text-[var(--color-text-secondary)] w-36">Status</label>
              <select
                v-model="fields.status.value"
                :disabled="!fields.status.enabled"
                class="pim-input text-xs py-1 px-2 flex-1"
                :class="!fields.status.enabled ? 'opacity-40' : ''"
              >
                <option value="active">Aktiv</option>
                <option value="inactive">Inaktiv</option>
              </select>
            </div>

            <!-- Error -->
            <p v-if="error" class="text-xs text-[var(--color-error)]">{{ error }}</p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="!hasEnabledFields || saving"
              @click="apply"
            >
              {{ saving ? 'Wird gespeichert…' : 'Anwenden' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
