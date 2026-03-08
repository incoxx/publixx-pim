<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { relationTypes } from '@/api/prices'
import attributesApi from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'
import { X, Plus } from 'lucide-vue-next'

const props = defineProps({
  relationType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

// Default attributes
const allAttributes = ref([])
const defaultAttributes = ref([])
const loadingAttributes = ref(false)
const savingAttributes = ref(false)
const newAttrId = ref('')

const isEdit = computed(() => !!props.relationType)

const formData = ref(
  props.relationType
    ? {
        technical_name: props.relationType.technical_name || '',
        name_de: props.relationType.name_de || '',
        name_en: props.relationType.name_en || '',
        is_bidirectional: props.relationType.is_bidirectional ?? false,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        is_bidirectional: false,
      }
)

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'is_bidirectional', label: 'Bidirektional', type: 'boolean' },
])

const availableAttributes = computed(() =>
  allAttributes.value.filter(a => !defaultAttributes.value.some(d => d.id === a.id))
)

onMounted(async () => {
  if (isEdit.value && props.relationType?.id) {
    loadingAttributes.value = true
    try {
      const [attrResp] = await Promise.all([
        attributesApi.list({ per_page: 9999 }),
      ])
      allAttributes.value = attrResp.data.data || attrResp.data
      defaultAttributes.value = (props.relationType.default_attributes || []).map(a => ({ ...a }))
    } catch (e) { /* ignore */ }
    finally { loadingAttributes.value = false }
  }
})

function addDefaultAttribute() {
  if (!newAttrId.value) return
  const attr = allAttributes.value.find(a => a.id === newAttrId.value)
  if (!attr || defaultAttributes.value.some(d => d.id === attr.id)) return
  defaultAttributes.value.push({ ...attr })
  newAttrId.value = ''
}

function removeDefaultAttribute(index) {
  defaultAttributes.value.splice(index, 1)
}

async function saveDefaultAttributes() {
  if (!props.relationType?.id) return
  savingAttributes.value = true
  try {
    const payload = defaultAttributes.value.map((a, idx) => ({
      attribute_id: a.id,
      sort_order: idx,
    }))
    const { data } = await relationTypes.updateDefaultAttributes(props.relationType.id, payload)
    const updated = data.data || data
    defaultAttributes.value = (updated.default_attributes || []).map(a => ({ ...a }))
  } catch (e) {
    console.error('Failed to save default attributes:', e.message)
  } finally {
    savingAttributes.value = false
  }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  try {
    if (isEdit.value) {
      await relationTypes.update(props.relationType.id, data)
    } else {
      await relationTypes.create(data)
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
      {{ isEdit ? 'Beziehungstyp bearbeiten' : 'Neuer Beziehungstyp' }}
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

    <!-- Default-Attribute -->
    <div v-if="isEdit" class="border-t border-[var(--color-border)] pt-4">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-2">
        Default-Attribute
        <span v-if="!loadingAttributes" class="text-[var(--color-text-tertiary)] font-normal">({{ defaultAttributes.length }})</span>
      </h4>

      <div v-if="loadingAttributes" class="space-y-2">
        <div v-for="i in 3" :key="i" class="pim-skeleton h-6 rounded" />
      </div>

      <template v-else>
        <!-- Existing default attributes -->
        <div v-if="defaultAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] italic mb-3">
          Keine Default-Attribute zugeordnet.
        </div>
        <div v-else class="space-y-1 max-h-[300px] overflow-y-auto mb-3">
          <div
            v-for="(attr, idx) in defaultAttributes"
            :key="attr.id"
            class="flex items-center justify-between p-2 rounded-lg bg-[var(--color-bg)] text-xs"
          >
            <div class="flex items-center gap-2 min-w-0">
              <span class="font-mono text-[var(--color-accent)] truncate">{{ attr.technical_name }}</span>
              <span class="text-[var(--color-text-secondary)] truncate">{{ attr.name_de }}</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">{{ attr.data_type }}</span>
              <button
                class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] p-0.5"
                title="Entfernen"
                @click="removeDefaultAttribute(idx)"
              >
                <X class="w-3.5 h-3.5" :stroke-width="1.75" />
              </button>
            </div>
          </div>
        </div>

        <!-- Add attribute -->
        <div class="flex items-end gap-2">
          <div class="flex-1">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attribut hinzufügen</label>
            <select
              v-model="newAttrId"
              class="pim-input text-xs w-full"
            >
              <option value="" disabled>Attribut wählen…</option>
              <option v-for="attr in availableAttributes" :key="attr.id" :value="attr.id">
                {{ attr.technical_name }} — {{ attr.name_de || '' }}
              </option>
            </select>
          </div>
          <button
            class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 mb-0.5"
            :disabled="!newAttrId"
            @click="addDefaultAttribute"
          >
            <Plus class="w-3 h-3" :stroke-width="2" /> Hinzufügen
          </button>
        </div>

        <!-- Save button -->
        <div class="flex justify-end pt-2">
          <button
            class="pim-btn pim-btn-primary text-xs"
            :disabled="savingAttributes"
            @click="saveDefaultAttributes"
          >
            {{ savingAttributes ? 'Speichern…' : 'Default-Attribute speichern' }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
