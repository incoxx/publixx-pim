<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { collectionTypes } from '@/api/collections'
import attributesApi, { attributeViews } from '@/api/attributes'
import { priceTypes } from '@/api/prices'
import pdfTemplatesApi from '@/api/pdfTemplates'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  collectionType: { type: Object, default: null },
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})

const pdfTemplateOptions = ref([])
const priceTypeOptions = ref([])
const headerAttributeViewOptions = ref([])
const itemAttributeViewOptions = ref([])
const itemAttributeOptions = ref([])

const isEdit = computed(() => !!props.collectionType)

const formData = ref(
  props.collectionType
    ? {
        technical_name: props.collectionType.technical_name || '',
        name_de: props.collectionType.name_de || '',
        name_en: props.collectionType.name_en || '',
        description: props.collectionType.description || '',
        icon: props.collectionType.icon || '',
        color: props.collectionType.color || '',
        sort_order: props.collectionType.sort_order ?? 0,
        is_active: props.collectionType.is_active ?? true,
        requires_organization: props.collectionType.requires_organization ?? false,
        requires_snapshot: props.collectionType.requires_snapshot ?? false,
        default_render_template_id: props.collectionType.default_render_template_id || '',
        default_price_type: props.collectionType.default_price_type || '',
        allowed_export_formats: props.collectionType.allowed_export_formats || [],
        default_attribute_groups: props.collectionType.default_attribute_groups || [],
        default_item_attribute_groups: props.collectionType.default_item_attribute_groups || [],
        default_discount_attribute: props.collectionType.default_discount_attribute || '',
      }
    : {
        technical_name: '',
        name_de: '',
        name_en: '',
        description: '',
        icon: '',
        color: '',
        sort_order: 0,
        is_active: true,
        requires_organization: false,
        requires_snapshot: false,
        default_render_template_id: '',
        default_price_type: '',
        allowed_export_formats: [],
        default_attribute_groups: [],
        default_item_attribute_groups: [],
        default_discount_attribute: '',
      }
)

const fields = computed(() => [
  // Grunddaten
  { key: 'technical_name', label: 'Technischer Name', type: 'text', required: true, disabled: isEdit.value },
  { key: 'name_de', label: 'Name (DE)', type: 'text', required: true },
  { key: 'name_en', label: 'Name (EN)', type: 'text' },
  { key: 'description', label: 'Beschreibung', type: 'textarea' },
  { key: 'icon', label: 'Icon (Lucide Name)', type: 'text', placeholder: 'file-text' },
  { key: 'color', label: 'Farbe (Hex)', type: 'text', placeholder: '#2563eb' },
  { key: 'sort_order', label: 'Sortierung', type: 'number' },
  { key: 'is_active', label: 'Aktiv', type: 'boolean' },
  // Verhalten
  { key: 'requires_organization', label: 'Empfänger (Organisation) erforderlich', type: 'boolean' },
  {
    key: 'requires_snapshot',
    label: 'Einfrieren vor Versand erforderlich',
    type: 'boolean',
    hint: 'z.B. für rechtsverbindliche Angebote — Status "sent" ist erst nach dem Einfrieren erlaubt.',
  },
  // Rendering & Export
  {
    key: 'default_render_template_id',
    label: 'PDF-Vorlage (Kopf-/Branding-Bereich)',
    type: 'select',
    options: [{ value: '', label: 'Keine' }, ...pdfTemplateOptions.value],
  },
  {
    key: 'default_price_type',
    label: 'Standard-Preistyp',
    type: 'select',
    options: [{ value: '', label: 'Keiner' }, ...priceTypeOptions.value],
  },
  {
    key: 'allowed_export_formats',
    label: 'Erlaubte Export-Formate',
    type: 'multiselect',
    options: [
      { value: 'pdf', label: 'PDF' },
      { value: 'xlsx', label: 'Excel (XLSX)' },
      { value: 'opentrans', label: 'openTRANS' },
    ],
  },
  // Attribut-Gruppen (Standard-Vorbelegung beim Anlegen einer Collection)
  {
    key: 'default_attribute_groups',
    label: 'Attributgruppen — Kopfdaten',
    type: 'multiselect',
    options: headerAttributeViewOptions.value,
    hint: 'Jedes Attribut dieser Gruppe(n) wird automatisch in der Kopfdaten-Karte einer Collection angezeigt und im Export gerendert (Reihenfolge = Attribut-Position in der Gruppe). Nur Gruppen mit mindestens einem Collection-Attribut werden hier angeboten.',
  },
  {
    key: 'default_item_attribute_groups',
    label: 'Attributgruppen — Positionen',
    type: 'multiselect',
    options: itemAttributeViewOptions.value,
    hint: 'Jedes Attribut dieser Gruppe(n) wird automatisch im "Attribute"-Panel jeder Position angezeigt und im Export als Zeile unter der Position gerendert. Nur Gruppen mit mindestens einem Positions-Attribut werden hier angeboten.',
  },
  // Rabatt bleibt eine Einzelrollen-Zuordnung, weil er rechnerisch in die Positionssumme
  // eingeht -- alle rein darstellenden Positions-Attribute kommen aus der Gruppe oben.
  {
    key: 'default_discount_attribute',
    label: 'Rabatt-Attribut (Position)',
    type: 'select',
    options: [{ value: '', label: 'Keines' }, ...itemAttributeOptions.value],
    hint: 'Wird als eigene Spalte in der Positionstabelle gerechnet, nicht als Anzeigezeile — auch wenn es Mitglied einer Attributgruppe oben ist.',
  },
])

onMounted(async () => {
  // Promise.allSettled statt Promise.all: PDF-Vorlagen sind ein eigenes, separat lizenzierbares
  // Enterprise-Modul (pdf_templates) -- ohne diese Lizenz liefert der Endpoint 403. Das darf nicht
  // dazu fuehren, dass auch die uebrigen (unabhaengigen) Auswahllisten leer bleiben.
  const [pdfRes, priceRes, viewsRes, itemAttrRes] = await Promise.allSettled([
    pdfTemplatesApi.list(),
    priceTypes.list(),
    // include=attributes: Attributgruppen (AttributeView) sind generisch fuer alle Entitaeten
    // (Produkte, Collections, ...) -- ob eine Gruppe hier ueberhaupt sinnvoll ist, ergibt sich
    // erst daraus, ob sie mindestens ein Attribut mit passendem applies_to enthaelt (s.u.).
    attributeViews.list({ include: 'attributes' }),
    // Nur fuer die Rabatt-Attribut-Einzelauswahl -- alle anderen Positions-/Kopfdaten-Attribute
    // kommen ueber die Attributgruppen-Mehrfachauswahl oben.
    attributesApi.list({ filters: { applies_to: 'collection_item' }, perPage: 200 }),
  ])
  if (pdfRes.status === 'fulfilled') {
    pdfTemplateOptions.value = (pdfRes.value.data.data || pdfRes.value.data).map(t => ({ value: t.id, label: t.name }))
  }
  if (priceRes.status === 'fulfilled') {
    priceTypeOptions.value = (priceRes.value.data.data || priceRes.value.data).map(t => ({ value: t.technical_name, label: t.name_de }))
  }
  if (viewsRes.status === 'fulfilled') {
    const views = viewsRes.value.data.data || viewsRes.value.data
    const viewsWithScope = (scope) => views
      .filter(v => (v.attributes || []).some(a => (a.applies_to || []).includes(scope)))
      .map(v => ({ value: v.technical_name, label: v.name_de }))
    headerAttributeViewOptions.value = viewsWithScope('collection')
    itemAttributeViewOptions.value = viewsWithScope('collection_item')
  }
  if (itemAttrRes.status === 'fulfilled') {
    itemAttributeOptions.value = (itemAttrRes.value.data.data || itemAttrRes.value.data).map(a => ({ value: a.technical_name, label: a.name_de }))
  }
})

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = {
    ...data,
    default_render_template_id: data.default_render_template_id || null,
    default_price_type: data.default_price_type || null,
    default_discount_attribute: data.default_discount_attribute || null,
  }
  try {
    if (isEdit.value) {
      await collectionTypes.update(props.collectionType.id, payload)
    } else {
      await collectionTypes.create(payload)
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
      {{ isEdit ? 'Collection-Typ bearbeiten' : 'Neuer Collection-Typ' }}
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
