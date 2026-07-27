<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { attributeViews, productTypes } from '@/api/attributes'
import attributesApi from '@/api/attributes'
import { Plus, X, GripVertical, ChevronUp, ChevronDown, Maximize2, Minimize2 } from 'lucide-vue-next'
import PimForm from '@/components/shared/PimForm.vue'

// vue-draggable-plus lazy laden um zirkuläre Initialisierung zu vermeiden
const VueDraggable = defineAsyncComponent(() =>
  import('vue-draggable-plus').then(m => m.VueDraggable)
)

const props = defineProps({
  attributeView: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const assignedAttributes = ref([])
const loadingAttributes = ref(false)

// For attribute assignment
const allAttributes = ref([])
const loadingAllAttributes = ref(false)
const showAddAttribute = ref(false)
const selectedAttributeId = ref('')
const addingAttribute = ref(false)

const isEdit = computed(() => !!props.attributeView)

const formData = ref(
  props.attributeView
    ? {
        technical_name: props.attributeView.technical_name || '',
        name_de: props.attributeView.name_de || '',
        name_en: props.attributeView.name_en || '',
        description: props.attributeView.description || '',
        sort_order: props.attributeView.sort_order ?? 0,
        show_as_tab: props.attributeView.show_as_tab ?? false,
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        sort_order: 0,
        show_as_tab: false,
      }
)

// Erlaubte Produkttypen — bewusst getrennt von formData/PimForm gehalten:
// PimForm klont modelValue einmalig beim Mount, externe Mutationen an
// formData nach dem Mount würden beim Submit sonst nicht mitgeschickt.
// (Gleiches Muster wie RelationTypeFormPanel.)
const allProductTypes = ref([])
const allowedProductTypeIds = ref([...(props.attributeView?.allowed_product_type_ids || [])])

function toggleProductType(id) {
  const idx = allowedProductTypeIds.value.indexOf(id)
  if (idx === -1) allowedProductTypeIds.value = [...allowedProductTypeIds.value, id]
  else allowedProductTypeIds.value = allowedProductTypeIds.value.filter(v => v !== id)
}

const fields = computed(() => [
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  { key: 'sort_order', label: 'Sortierung', type: 'number' },
  { key: 'show_as_tab', label: 'Eigener Tab im Produkteditor', type: 'boolean' },
])

const assignedIds = computed(() => new Set(assignedAttributes.value.map(a => a.id)))

const availableAttributes = computed(() =>
  allAttributes.value.filter(a => !assignedIds.value.has(a.id))
)

onMounted(async () => {
  try {
    const { data } = await productTypes.list({ per_page: 9999 })
    allProductTypes.value = data.data || data
  } catch (e) { /* ignore */ }

  if (isEdit.value && props.attributeView?.id) {
    loadingAttributes.value = true
    try {
      // Load view with its attributes
      const { data } = await attributeViews.list({ include: 'attributes', perPage: 500 })
      const views = data.data || data
      const thisView = views.find(v => v.id === props.attributeView.id)
      assignedAttributes.value = thisView?.attributes || []
    } catch (e) { /* ignore */ }
    finally { loadingAttributes.value = false }
  }
})

async function loadAllAttributes() {
  if (allAttributes.value.length > 0) return
  loadingAllAttributes.value = true
  try {
    const { data } = await attributesApi.list({ perPage: 500 })
    allAttributes.value = data.data || data
  } catch (e) { /* ignore */ }
  finally { loadingAllAttributes.value = false }
}

async function addAttribute() {
  if (!selectedAttributeId.value) return
  addingAttribute.value = true
  try {
    await attributeViews.addAttribute(props.attributeView.id, {
      attribute_id: selectedAttributeId.value,
    })
    // Add to local list
    const attr = allAttributes.value.find(a => a.id === selectedAttributeId.value)
    if (attr) assignedAttributes.value.push({ ...attr, is_readonly_in_view: false })
    selectedAttributeId.value = ''
  } catch (e) { /* ignore */ }
  finally { addingAttribute.value = false }
}

async function removeAttribute(attrId) {
  try {
    await attributeViews.removeAttribute(props.attributeView.id, attrId)
    assignedAttributes.value = assignedAttributes.value.filter(a => a.id !== attrId)
  } catch (e) { /* ignore */ }
}

async function persistAttributeOrder() {
  try {
    await attributeViews.reorderAttributes(
      props.attributeView.id,
      assignedAttributes.value.map(a => a.id)
    )
  } catch (e) {
    // Rollback: Reihenfolge neu laden
    try {
      const { data } = await attributeViews.list({ include: 'attributes', perPage: 500 })
      const views = data.data || data
      const thisView = views.find(v => v.id === props.attributeView.id)
      assignedAttributes.value = thisView?.attributes || []
    } catch { /* ignore */ }
  }
}

async function moveAttributeUp(index) {
  if (index <= 0) return
  const list = [...assignedAttributes.value]
  ;[list[index - 1], list[index]] = [list[index], list[index - 1]]
  assignedAttributes.value = list
  await persistAttributeOrder()
}

async function moveAttributeDown(index) {
  if (index >= assignedAttributes.value.length - 1) return
  const list = [...assignedAttributes.value]
  ;[list[index], list[index + 1]] = [list[index + 1], list[index]]
  assignedAttributes.value = list
  await persistAttributeOrder()
}

async function toggleAttributeReadOnly(attr) {
  const nextValue = !attr.is_readonly_in_view
  attr.is_readonly_in_view = nextValue
  try {
    await attributeViews.updateAssignment(props.attributeView.id, attr.id, { is_readonly: nextValue })
  } catch (e) {
    attr.is_readonly_in_view = !nextValue
  }
}

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = {
    ...data,
    allowed_product_type_ids: allowedProductTypeIds.value,
  }
  try {
    if (isEdit.value) {
      await attributeViews.update(props.attributeView.id, payload)
    } else {
      await attributeViews.create(payload)
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
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
        {{ isEdit ? 'Attribut-Sicht bearbeiten' : 'Neue Attribut-Sicht' }}
      </h3>
      <button
        v-if="isEdit"
        class="pim-btn pim-btn-ghost pim-btn-xs"
        :title="authStore.panelFullscreen ? 'Vollbild beenden' : 'Vollbild (für Drag&Drop-Reihenfolge)'"
        @click="authStore.setPanelFullscreen(!authStore.panelFullscreen)"
      >
        <Minimize2 v-if="authStore.panelFullscreen" class="w-3.5 h-3.5" :stroke-width="2" />
        <Maximize2 v-else class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
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

    <!-- Erlaubte Produkttypen: leer = für alle Produkttypen gültig (Default) -->
    <div class="space-y-1">
      <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">Erlaubte Produkttypen</h4>
      <p class="text-[11px] text-[var(--color-text-tertiary)] mb-2">
        Diese Sicht (Tab &amp; Attribut-Filter) erscheint im Produkteditor nur bei Produkten
        der gewählten Typen. Keine Auswahl = für alle Produkttypen gültig.
      </p>
      <div class="flex flex-wrap gap-1.5">
        <label
          v-for="pt in allProductTypes"
          :key="pt.id"
          class="flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer transition-colors"
          :class="allowedProductTypeIds.includes(pt.id)
            ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
            : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)]'"
        >
          <input type="checkbox" class="pim-checkbox w-3 h-3" :checked="allowedProductTypeIds.includes(pt.id)" @change="toggleProductType(pt.id)" />
          {{ pt.name_de || pt.technical_name }}
        </label>
      </div>
    </div>

    <!-- Zugeordnete Attribute -->
    <div v-if="isEdit" class="border-t border-[var(--color-border)] pt-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold text-[var(--color-text-secondary)]">
          Zugeordnete Attribute
          <span v-if="!loadingAttributes" class="text-[var(--color-text-tertiary)] font-normal">({{ assignedAttributes.length }})</span>
        </h4>
        <button
          class="pim-btn pim-btn-ghost pim-btn-xs"
          @click="showAddAttribute = !showAddAttribute; if (showAddAttribute) loadAllAttributes()"
        >
          <Plus class="w-3 h-3" :stroke-width="2" />
          Zuordnen
        </button>
      </div>

      <!-- Add attribute selector -->
      <div v-if="showAddAttribute" class="flex gap-1 mb-3">
        <select
          v-model="selectedAttributeId"
          class="pim-input text-xs flex-1"
          :disabled="loadingAllAttributes"
        >
          <option value="">{{ loadingAllAttributes ? 'Laden…' : '— Attribut wählen —' }}</option>
          <option v-for="attr in availableAttributes" :key="attr.id" :value="attr.id">
            {{ attr.technical_name }} — {{ attr.name_de || '' }}
          </option>
        </select>
        <button
          class="pim-btn pim-btn-primary pim-btn-xs"
          :disabled="!selectedAttributeId || addingAttribute"
          @click="addAttribute"
        >
          OK
        </button>
      </div>

      <div v-if="loadingAttributes" class="space-y-2">
        <div v-for="i in 3" :key="i" class="pim-skeleton h-6 rounded" />
      </div>
      <div v-else-if="assignedAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] italic">
        Keine Attribute zugeordnet.
      </div>
      <template v-else>
        <VueDraggable
          v-model="assignedAttributes"
          handle=".drag-handle"
          ghost-class="opacity-30"
          class="space-y-1 max-h-[400px] overflow-y-auto"
          @end="persistAttributeOrder"
        >
          <div
            v-for="(attr, idx) in assignedAttributes"
            :key="attr.id"
            class="flex items-center justify-between p-2 rounded-lg bg-[var(--color-bg)] text-xs"
          >
            <div class="flex items-center gap-2 min-w-0">
              <GripVertical class="drag-handle w-3 h-3 text-[var(--color-text-tertiary)] opacity-40 cursor-grab active:cursor-grabbing shrink-0" />
              <span class="font-mono text-[var(--color-accent)] truncate">{{ attr.technical_name }}</span>
              <span class="text-[var(--color-text-secondary)] truncate">{{ attr.name_de }}</span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">{{ attr.data_type }}</span>
              <button
                class="p-0.5 rounded transition-colors"
                :class="attr.is_readonly_in_view
                  ? 'text-[var(--color-warning)]'
                  : 'text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
                :title="attr.is_readonly_in_view ? 'Nur-Lesen in diesem Tab (klicken zum Ändern)' : 'Editierbar in diesem Tab (klicken für Nur-Lesen)'"
                @click.stop="toggleAttributeReadOnly(attr)"
              >
                {{ attr.is_readonly_in_view ? 'Nur Lesen' : 'Editierbar' }}
              </button>
              <button
                class="p-0.5 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-all disabled:opacity-20"
                :disabled="idx === 0"
                title="Nach oben"
                @click="moveAttributeUp(idx)"
              >
                <ChevronUp class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <button
                class="p-0.5 rounded text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)] transition-all disabled:opacity-20"
                :disabled="idx === assignedAttributes.length - 1"
                title="Nach unten"
                @click="moveAttributeDown(idx)"
              >
                <ChevronDown class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <button
                class="p-0.5 rounded hover:bg-[var(--color-danger-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-danger)] transition-colors"
                title="Zuordnung entfernen"
                @click.stop="removeAttribute(attr.id)"
              >
                <X class="w-3 h-3" :stroke-width="2" />
              </button>
            </div>
          </div>
        </VueDraggable>
      </template>
    </div>

    <!-- Hinweis: Rollen-Sichtbarkeit für den Tab wird zentral in der Rollenverwaltung
         gepflegt (Produkt-Editor-Tabs-Matrix), nicht hier — damit es nur eine Quelle
         der Wahrheit für die zugrundeliegenden Berechtigungen gibt. -->
    <div v-if="isEdit && formData.show_as_tab" class="border-t border-[var(--color-border)] pt-4">
      <p class="text-[11px] text-[var(--color-text-tertiary)]">
        Sichtbarkeit und Zugriff (schreiben/lesen/versteckt) je Benutzerrolle für diesen Tab
        werden in der <strong>Rollenverwaltung</strong> unter „Produkt-Editor Tabs“ festgelegt —
        die Sicht erscheint dort automatisch als eigene Zeile, sobald „Eigener Tab“ aktiv ist.
      </p>
    </div>
  </div>
</template>
