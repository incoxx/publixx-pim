<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { relationTypes } from '@/api/prices'
import attributesApi, { productTypes } from '@/api/attributes'
import PimForm from '@/components/shared/PimForm.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import { X, Plus } from 'lucide-vue-next'

// Lädt ALLE Attribute über alle Seiten. Nötig, weil die API per_page serverseitig
// auf 100 cappt (getPerPage) — ein einzelner Aufruf lieferte sonst nur einen
// Ausschnitt. Ergebnis alphabetisch nach Anzeigename sortiert.
async function fetchAllAttributes() {
  const all = []
  let page = 1
  // Obergrenze als Sicherheitsnetz gegen Endlosschleifen bei unerwarteten Meta-Daten.
  for (let guard = 0; guard < 100; guard++) {
    const { data } = await attributesApi.list({ perPage: 100, page })
    const items = data.data || data || []
    all.push(...items)
    const lastPage = data.meta?.last_page ?? 1
    if (items.length === 0 || page >= lastPage) break
    page++
  }
  return all.sort((a, b) =>
    (a.name_de || a.technical_name || '').localeCompare(b.name_de || b.technical_name || '', 'de')
  )
}

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
const saveAttrError = ref('')
const saveAttrSuccess = ref(false)
const newAttrId = ref('')

const isEdit = computed(() => !!props.relationType)

const formData = ref(
  props.relationType
    ? {
        technical_name: props.relationType.technical_name || '',
        name_de: props.relationType.name_de || '',
        name_en: props.relationType.name_en || '',
        is_bidirectional: props.relationType.is_bidirectional ?? false,
        allows_free_attributes: props.relationType.allows_free_attributes ?? true,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        is_bidirectional: false,
        allows_free_attributes: true,
      }
)

// Erlaubte Produkttypen — bewusst getrennt von formData/PimForm gehalten:
// PimForm klont modelValue einmalig beim Mount, externe Mutationen an
// formData nach dem Mount würden beim Submit sonst nicht mitgeschickt.
const allProductTypes = ref([])
const allowedSourceProductTypeIds = ref([...(props.relationType?.allowed_source_product_type_ids || [])])
const allowedTargetProductTypeIds = ref([...(props.relationType?.allowed_target_product_type_ids || [])])

// Zwei dedizierte Funktionen statt einer generischen mit Ref-Parameter: Vue
// entpackt Top-Level-Refs, die direkt aus dem Template übergeben werden
// (z.B. "toggleProductTypeId(allowedSourceProductTypeIds, id)") — die
// Funktion hätte dann nur den aktuellen Array-Wert erhalten, nicht die Ref,
// und Mutationen wären ins Leere gelaufen.
function toggleSourceProductType(id) {
  const idx = allowedSourceProductTypeIds.value.indexOf(id)
  if (idx === -1) allowedSourceProductTypeIds.value = [...allowedSourceProductTypeIds.value, id]
  else allowedSourceProductTypeIds.value = allowedSourceProductTypeIds.value.filter(v => v !== id)
}

function toggleTargetProductType(id) {
  const idx = allowedTargetProductTypeIds.value.indexOf(id)
  if (idx === -1) allowedTargetProductTypeIds.value = [...allowedTargetProductTypeIds.value, id]
  else allowedTargetProductTypeIds.value = allowedTargetProductTypeIds.value.filter(v => v !== id)
}

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'is_bidirectional', label: 'Bidirektional', type: 'boolean' },
  { key: 'allows_free_attributes', label: 'Freie Attribute zulassen', type: 'boolean' },
])

const availableAttributes = computed(() =>
  allAttributes.value.filter(a => !defaultAttributes.value.some(d => d.id === a.id))
)

onMounted(async () => {
  try {
    const { data } = await productTypes.list({ perPage: 100 })
    allProductTypes.value = data.data || data
  } catch (e) { /* ignore */ }

  if (isEdit.value && props.relationType?.id) {
    loadingAttributes.value = true
    try {
      allAttributes.value = await fetchAllAttributes()
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
  saveAttrError.value = ''
  saveAttrSuccess.value = false
  try {
    const payload = defaultAttributes.value.map((a, idx) => ({
      attribute_id: a.id,
      sort_order: idx,
    }))
    const { data } = await relationTypes.updateDefaultAttributes(props.relationType.id, payload)
    const updated = data.data || data
    defaultAttributes.value = (updated.default_attributes || []).map(a => ({ ...a }))
    saveAttrSuccess.value = true
    setTimeout(() => { saveAttrSuccess.value = false }, 3000)
    if (props.onSaved) props.onSaved()
  } catch (e) {
    console.error('Failed to save default attributes:', e)
    saveAttrError.value = e.response?.data?.message || e.message || 'Fehler beim Speichern'
  } finally {
    savingAttributes.value = false
  }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = {
    ...data,
    allowed_source_product_type_ids: allowedSourceProductTypeIds.value,
    allowed_target_product_type_ids: allowedTargetProductTypeIds.value,
  }
  try {
    if (isEdit.value) {
      await relationTypes.update(props.relationType.id, payload)
    } else {
      await relationTypes.create(payload)
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
    <!-- Erlaubte Produkttypen: leer = für alle Produkttypen zulässig (Default) -->
    <div class="space-y-3">
      <div>
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-1">
          {{ formData.is_bidirectional ? 'Erlaubte Produkttypen' : 'Erlaubte Quell-Produkttypen' }}
        </h4>
        <p class="text-[11px] text-[var(--color-text-tertiary)] mb-2">
          <template v-if="formData.is_bidirectional">
            Bidirektional: gilt gleichermaßen als Quelle und Ziel. Keine Auswahl = alle Produkttypen erlaubt.
          </template>
          <template v-else>
            Nur Produkte dieser Typen können diesen Beziehungstyp anlegen. Keine Auswahl = alle Produkttypen erlaubt.
          </template>
        </p>
        <div class="flex flex-wrap gap-1.5">
          <label
            v-for="pt in allProductTypes"
            :key="pt.id"
            class="flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer transition-colors"
            :class="allowedSourceProductTypeIds.includes(pt.id)
              ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
              : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)]'"
          >
            <input type="checkbox" class="pim-checkbox w-3 h-3" :checked="allowedSourceProductTypeIds.includes(pt.id)" @change="toggleSourceProductType(pt.id)" />
            {{ pt.name_de || pt.technical_name }}
          </label>
        </div>
      </div>
      <div v-if="!formData.is_bidirectional">
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)] mb-1">Erlaubte Ziel-Produkttypen</h4>
        <p class="text-[11px] text-[var(--color-text-tertiary)] mb-2">
          Nur Produkte dieser Typen können als Zielprodukt gewählt werden. Keine Auswahl = alle Produkttypen erlaubt.
        </p>
        <div class="flex flex-wrap gap-1.5">
          <label
            v-for="pt in allProductTypes"
            :key="pt.id"
            class="flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer transition-colors"
            :class="allowedTargetProductTypeIds.includes(pt.id)
              ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
              : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)]'"
          >
            <input type="checkbox" class="pim-checkbox w-3 h-3" :checked="allowedTargetProductTypeIds.includes(pt.id)" @change="toggleTargetProductType(pt.id)" />
            {{ pt.name_de || pt.technical_name }}
          </label>
        </div>
      </div>
    </div>

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

        <!-- Add attribute (durchsuchbare Lookup-Auswahl) -->
        <div class="flex items-end gap-2">
          <div class="flex-1">
            <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Attribut hinzufügen</label>
            <PimAttributeInput
              type="dictionary"
              v-model="newAttrId"
              :options="availableAttributes.map(a => ({ value: a.id, label: (a.name_de || a.technical_name) + ' — ' + a.technical_name }))"
              placeholder="Attribut suchen…"
            />
          </div>
          <button
            class="pim-btn pim-btn-secondary text-xs px-3 py-1.5 mb-0.5"
            :disabled="!newAttrId"
            @click="addDefaultAttribute"
          >
            <Plus class="w-3 h-3" :stroke-width="2" /> Hinzufügen
          </button>
        </div>

        <!-- Save feedback + button -->
        <div v-if="saveAttrError" class="text-xs text-[var(--color-error)] bg-red-50 rounded p-2 mt-2">
          {{ saveAttrError }}
        </div>
        <div v-if="saveAttrSuccess" class="text-xs text-[var(--color-success,#16a34a)] bg-green-50 rounded p-2 mt-2">
          Default-Attribute gespeichert.
        </div>
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
