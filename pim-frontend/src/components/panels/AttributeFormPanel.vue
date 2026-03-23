<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useAttributeStore } from '@/stores/attributes'
import { useAuthStore } from '@/stores/auth'
import { units as unitsApi } from '@/api/units'
import attributesApi from '@/api/attributes'
import { translationLanguages } from '@/config/languages'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  attribute: { type: Object, default: null },
})

const store = useAttributeStore()
const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const unitOptions = ref([])

// Migration dialog state
const showMigrateDialog = ref(false)
const migrateLanguage = ref('de')
const migrating = ref(false)
const migrateResult = ref(null)
let pendingSubmitData = null

onMounted(() => {
  if (!store.allItems.length) store.fetchAllAttributes()
  if (!store.types.length) store.fetchTypes()
  if (!store.lists.length) store.fetchValueLists()
  if (!store.unitGroupsList.length) store.fetchUnitGroups()
  if (!store.compOpGroupsList.length) store.fetchComparisonOperatorGroups()
})

const isEdit = computed(() => !!props.attribute)

const formData = ref(
  props.attribute
    ? {
        ...props.attribute,
        child_attribute_ids: (props.attribute.children || []).map(c => c.id),
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        data_type: '',
        attribute_type_id: '',
        value_list_id: '',
        unit_group_id: '',
        default_unit_id: '',
        comparison_operator_group_id: '',
        child_attribute_ids: [],
        is_translatable: false,
        is_multipliable: false,
        is_searchable: false,
        is_mandatory: false,
        is_unique: false,
        is_inheritable: false,
        is_variant_attribute: false,
        is_internal: false,
        is_readonly: false,
        is_hidden: false,
        description_de: '',
        status: 'active',
      }
)

// Load units when unit group changes
let isInitialUnitLoad = !!props.attribute?.unit_group_id
watch(() => formData.value.unit_group_id, async (newGroupId) => {
  // Beim initialen Laden den gespeicherten default_unit_id nicht überschreiben
  if (!isInitialUnitLoad) {
    formData.value.default_unit_id = ''
  }
  isInitialUnitLoad = false
  if (newGroupId) {
    try {
      const { data } = await unitsApi.list(newGroupId)
      unitOptions.value = data.data || data
    } catch {
      unitOptions.value = []
    }
  } else {
    unitOptions.value = []
  }
}, { immediate: !!props.attribute?.unit_group_id })

// Clear unit + comparison operator fields when data type changes away from numeric
watch(() => formData.value.data_type, (newType) => {
  if (!['Number', 'Float'].includes(newType)) {
    formData.value.unit_group_id = ''
    formData.value.default_unit_id = ''
    formData.value.comparison_operator_group_id = ''
    unitOptions.value = []
  }
})

const fields = computed(() => {
  const base = [
    { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
    { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
    { key: 'name_en', label: 'Name (EN)', type: 'text' },
    {
      key: 'data_type', label: 'Datentyp', type: 'select', required: true, disabled: isEdit.value,
      options: [
        { value: 'String', label: 'Text' },
        { value: 'Number', label: 'Ganzzahl' },
        { value: 'Float', label: 'Dezimalzahl' },
        { value: 'Flag', label: 'Ja / Nein' },
        { value: 'Date', label: 'Datum' },
        { value: 'Selection', label: 'Auswahl (Werteliste)' },
        { value: 'MultiSelection', label: 'Mehrfachauswahl (Werteliste)' },
        { value: 'Dictionary', label: 'Wörterbuch (Key→Value)' },
        { value: 'RichText', label: 'Formatierter Text (HTML)' },
        { value: 'Composite', label: 'Zusammengesetzt' },
      ],
    },
    {
      key: 'attribute_type_id',
      label: 'Attributgruppe',
      type: 'select',
      options: store.attributeTypeOptions,
    },
  ]

  if (['Selection', 'MultiSelection', 'Dictionary'].includes(formData.value.data_type)) {
    base.push({
      key: 'value_list_id', label: 'Werteliste', type: 'select',
      options: store.valueListOptions, required: true,
    })
  }

  if (['Number', 'Float'].includes(formData.value.data_type)) {
    base.push(
      {
        key: 'unit_group_id', label: 'Einheitengruppe', type: 'select',
        options: store.unitGroupOptions,
      },
      {
        key: 'default_unit_id', label: 'Standard-Einheit', type: 'select',
        options: unitOptions.value.map(u => ({ value: u.id, label: `${u.abbreviation} — ${u.name_de || u.abbreviation}` })),
      },
    )
  }

  if (formData.value.data_type === 'Composite') {
    const childOptions = store.allItems
      .filter(a => a.id !== props.attribute?.id && a.data_type !== 'Composite' && !a.parent_attribute_id)
      .map(a => ({ value: a.id, label: a.name_de || a.technical_name }))
    base.push({
      key: 'child_attribute_ids', label: 'Kind-Attribute', type: 'multi-select',
      options: childOptions,
    })
  }

  if (formData.value.data_type === 'Number' || formData.value.data_type === 'Float') {
    base.push({
      key: 'comparison_operator_group_id', label: 'Vergleichsoperator-Gruppe', type: 'select',
      options: store.comparisonOperatorGroupOptions || [],
    })
  }

  base.push(
    {
      key: 'status', label: 'Status', type: 'select',
      options: [{ value: 'active', label: 'Aktiv' }, { value: 'inactive', label: 'Inaktiv' }],
    },
    { key: 'is_translatable', label: 'Übersetzbar', type: 'boolean' },
    { key: 'is_multipliable', label: 'Multiplizierbar', type: 'boolean' },
    { key: 'is_searchable', label: 'Suchbar', type: 'boolean' },
    { key: 'is_mandatory', label: 'Pflichtfeld', type: 'boolean' },
    { key: 'is_unique', label: 'Eindeutig', type: 'boolean' },
    { key: 'is_inheritable', label: 'Vererbbar', type: 'boolean' },
    { key: 'is_variant_attribute', label: 'Varianten-Attribut', type: 'boolean' },
    { key: 'is_internal', label: 'Intern', type: 'boolean' },
    { key: 'is_readonly', label: 'Schreibgeschützt', type: 'boolean', hint: 'Im Produkteditor und bei Massenoperationen nicht bearbeitbar' },
    { key: 'is_hidden', label: 'Versteckt', type: 'boolean', hint: 'Nicht sichtbar im Editor, aber in Exporten enthalten' },
    { key: 'description_de', label: 'Beschreibung', type: 'textarea' },
  )

  return base
})

async function handleSubmit(data) {
  // Check if is_translatable was toggled from false to true
  if (isEdit.value && !props.attribute.is_translatable && data.is_translatable) {
    pendingSubmitData = data
    showMigrateDialog.value = true
    return
  }

  await doSave(data)
}

async function doSave(data) {
  loading.value = true
  errors.value = {}
  try {
    const { child_attribute_ids, ...attrData } = data
    // Convert empty strings to null for nullable FK fields
    if (!attrData.unit_group_id) attrData.unit_group_id = null
    if (!attrData.default_unit_id) attrData.default_unit_id = null
    let savedId

    if (isEdit.value) {
      await store.updateAttribute(props.attribute.id, attrData)
      savedId = props.attribute.id
    } else {
      const created = await store.createAttribute(attrData)
      savedId = created.id
    }

    // Update child attribute relationships for Composite attributes
    if (data.data_type === 'Composite' && savedId) {
      const newChildIds = child_attribute_ids || []
      const oldChildIds = (props.attribute?.children || []).map(c => c.id)

      // Assign new children (set parent_attribute_id)
      for (const childId of newChildIds) {
        if (!oldChildIds.includes(childId)) {
          await store.updateAttribute(childId, { parent_attribute_id: savedId })
        }
      }
      // Unassign removed children (clear parent_attribute_id)
      for (const childId of oldChildIds) {
        if (!newChildIds.includes(childId)) {
          await store.updateAttribute(childId, { parent_attribute_id: null })
        }
      }
    }

    await store.fetchAttributes()
    authStore.closePanel()
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

async function confirmMigrate() {
  if (!pendingSubmitData) return
  migrating.value = true
  migrateResult.value = null

  try {
    // First save the attribute (with is_translatable = true)
    await doSave(pendingSubmitData)

    // Then migrate existing values
    const { data } = await attributesApi.migrateLanguage(props.attribute.id, migrateLanguage.value)
    migrateResult.value = data

    showMigrateDialog.value = false
    pendingSubmitData = null
  } catch (e) {
    migrateResult.value = { error: e.response?.data?.message || 'Fehler bei der Migration' }
  } finally {
    migrating.value = false
  }
}

function skipMigrate() {
  if (!pendingSubmitData) return
  showMigrateDialog.value = false
  doSave(pendingSubmitData)
  pendingSubmitData = null
}

function cancelMigrate() {
  showMigrateDialog.value = false
  pendingSubmitData = null
}
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">
      {{ isEdit ? 'Attribut bearbeiten' : 'Neues Attribut' }}
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

    <!-- Language Migration Dialog -->
    <Teleport to="body">
      <div v-if="showMigrateDialog" class="fixed inset-0 z-[100] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" @click="cancelMigrate" />
        <div class="relative bg-[var(--color-surface)] rounded-lg shadow-xl p-6 mx-4 max-w-md w-full space-y-4">
          <h3 class="text-sm font-semibold">Bestehende Werte migrieren</h3>
          <p class="text-xs text-[var(--color-text-secondary)]">
            Dieses Attribut hat bestehende Produktwerte ohne Sprachzuordnung.
            In welche Sprache sollen die vorhandenen Werte übernommen werden?
          </p>

          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-1">Zielsprache</label>
            <select v-model="migrateLanguage" class="pim-input text-sm w-full">
              <option v-for="lang in translationLanguages" :key="lang.code" :value="lang.code">
                {{ lang.label }} ({{ lang.code }})
              </option>
            </select>
          </div>

          <div v-if="migrateResult?.error" class="p-2 rounded bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">
            {{ migrateResult.error }}
          </div>

          <div class="flex justify-end gap-2">
            <button class="pim-btn pim-btn-secondary text-xs" @click="cancelMigrate">
              Abbrechen
            </button>
            <button class="pim-btn pim-btn-secondary text-xs" @click="skipMigrate">
              Ohne Migration speichern
            </button>
            <button class="pim-btn pim-btn-primary text-xs" :disabled="migrating" @click="confirmMigrate">
              {{ migrating ? 'Migriere...' : 'Migrieren & Speichern' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
