<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Save, Plus, Trash2, Image, Star, X, Search, Download } from 'lucide-vue-next'
import productsApi from '@/api/products'
import mediaApi from '@/api/media'
import { priceTypes, relationTypes } from '@/api/prices'
import attributesApiDefault, { productTypes } from '@/api/attributes'
import hierarchiesApi from '@/api/hierarchies'
import PimCollectionGroup from '@/components/shared/PimCollectionGroup.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import PimTable from '@/components/shared/PimTable.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'
import PimCompositeModal from '@/components/shared/PimCompositeModal.vue'
import ProductVersionsTab from '@/components/products/ProductVersionsTab.vue'

const route = useRoute()
const router = useRouter()
const store = useProductStore()
const { t } = useI18n()

const activeTab = ref('attributes')
const saving = ref(false)

// Hierarchy assignment
const hierarchies = ref([])
const hierarchyNodes = ref([])
const selectedHierarchyId = ref(null)

const tabs = [
  { key: 'attributes', label: t('product.attributes') },
  { key: 'variant-attributes', label: 'Varianten-Attribute' },
  { key: 'variants', label: t('product.variants') },
  { key: 'media', label: t('product.media') },
  { key: 'prices', label: t('product.prices') },
  { key: 'relations', label: t('product.relations') },
  { key: 'preview', label: t('product.preview') },
  { key: 'versions', label: t('product.versions') },
]

const product = computed(() => store.current)

// ─── Attribute Values ─────────────────────────────────
const schema = ref(null)
const attributeValues = ref({})
const attrLoaded = ref(false)

// ─── Composite Modal State ────────────────────────────
const compositeModalOpen = ref(false)
const activeComposite = ref(null)

function openCompositeModal(compositeAttr) {
  activeComposite.value = compositeAttr
  compositeModalOpen.value = true
}

function onCompositeValuesUpdate(newValues) {
  for (const [childId, value] of Object.entries(newValues)) {
    attributeValues.value[childId] = value
  }
}

function getCompositeSummary(compositeAttr) {
  const children = compositeAttr._children || []
  const parts = children
    .map(c => attributeValues.value[c.id])
    .filter(v => v !== undefined && v !== null && v !== '')
  if (parts.length === 0) return null
  return parts.join(' × ')
}

function mapDataTypeToInput(backendType) {
  const map = {
    'String': 'text', 'Number': 'number', 'Float': 'decimal',
    'Date': 'date', 'Flag': 'boolean', 'Selection': 'select',
    'Dictionary': 'json', 'Collection': 'json', 'Composite': 'composite',
  }
  return map[backendType] || 'text'
}

async function loadAttributeData() {
  if (attrLoaded.value || !product.value) return
  try {
    // Try resolved attributes from hierarchy first (includes inheritance info)
    let resolvedAttrs = null
    if (product.value.master_hierarchy_node_id) {
      try {
        const { data: resolvedData } = await productsApi.getResolvedAttributes(product.value.id)
        resolvedAttrs = resolvedData.data || resolvedData
      } catch { /* fallback to product type schema */ }
    }

    if (resolvedAttrs && resolvedAttrs.length > 0) {
      // Use hierarchy-resolved attributes as schema
      schema.value = resolvedAttrs.map(ra => ({
        id: ra.attribute_id,
        technical_name: ra.attribute_technical_name,
        name_de: ra.attribute_name_de || ra.attribute_technical_name,
        name_en: ra.attribute_name_en,
        data_type: ra.data_type,
        is_mandatory: ra.is_mandatory,
        is_translatable: ra.is_translatable,
        is_variant_attribute: ra.is_variant_attribute || false,
        parent_attribute_id: ra.parent_attribute_id || null,
        group: ra.collection_name || 'Vererbte Attribute',
        _source: ra.source,
        _is_inherited: ra.is_inherited,
        _access: ra.access_product,
      }))
      // Populate values from resolved data
      for (const ra of resolvedAttrs) {
        if (ra.value !== null && ra.value !== undefined) {
          attributeValues.value[ra.attribute_id] = ra.value
        }
      }
    } else {
      // Fallback: load attribute values + product type schema
      const { data: valData } = await productsApi.getAttributeValues(product.value.id)
      const vals = valData.data || valData
      if (Array.isArray(vals)) {
        for (const val of vals) {
          const attrId = val.attribute_id || val.attribute?.id
          if (!attrId) continue
          attributeValues.value[attrId] = val.value_string ?? val.value_number ?? val.value_date ?? val.value_flag ?? val.value_selection_id ?? ''
        }
      }
      if (product.value.product_type_id) {
        try {
          const { data: schemaData } = await productTypes.getSchema(product.value.product_type_id)
          schema.value = schemaData.data || schemaData
        } catch { /* schema might not exist */ }
      }
    }
    attrLoaded.value = true
  } catch { /* silently fail */ }
}

const schemaAttributes = computed(() => {
  if (!schema.value) return []
  // Schema may have attributes directly or grouped
  if (Array.isArray(schema.value.attributes)) return schema.value.attributes
  if (Array.isArray(schema.value)) return schema.value
  return []
})

const attributeGroups = computed(() => {
  const allAttrs = schemaAttributes.value.filter(a => !a.is_variant_attribute)
  if (allAttrs.length === 0) return []

  // Collect IDs of all composite attributes in the schema
  const compositeIds = new Set(allAttrs.filter(a => a.data_type === 'Composite').map(a => a.id))

  // Filter out child attributes whose parent composite is also in the schema
  // (they will only appear inside the composite modal)
  const attrs = allAttrs.filter(a => {
    if (a.parent_attribute_id && compositeIds.has(a.parent_attribute_id)) return false
    return true
  })

  // Enrich composite attributes with their children for the modal
  for (const attr of attrs) {
    if (attr.data_type === 'Composite') {
      attr._children = allAttrs.filter(c => c.parent_attribute_id === attr.id)
    }
  }

  const groups = {}
  for (const attr of attrs) {
    const groupName = attr.attribute_type?.name_de || attr.group || 'Weitere Attribute'
    if (!groups[groupName]) groups[groupName] = []
    groups[groupName].push(attr)
  }
  return Object.entries(groups).map(([name, attributes]) => ({ name, attributes }))
})

const variantAttributeGroups = computed(() => {
  const attrs = schemaAttributes.value.filter(a => a.is_variant_attribute)
  if (attrs.length === 0) return []
  const groups = {}
  for (const attr of attrs) {
    const groupName = attr.attribute_type?.name_de || attr.group || 'Varianten-Attribute'
    if (!groups[groupName]) groups[groupName] = []
    groups[groupName].push(attr)
  }
  return Object.entries(groups).map(([name, attributes]) => ({ name, attributes }))
})

// ─── Variants ─────────────────────────────────────────
const variants = ref([])
const variantsLoaded = ref(false)
const variantsLoading = ref(false)
const showVariantForm = ref(false)
const variantForm = ref({ sku: '', name: '', ean: '', status: 'draft' })
const variantErrors = ref({})
const variantSaving = ref(false)

const variantAttributeDefs = ref([])
const variantAttrValuesMap = ref({})

const variantColumns = computed(() => {
  const base = [
    { key: 'sku', label: 'SKU', mono: true },
    { key: 'name', label: 'Name' },
    { key: 'status', label: 'Status' },
  ]
  for (const attr of variantAttributeDefs.value) {
    base.push({ key: `_va_${attr.id}`, label: attr.name_de || attr.technical_name })
  }
  return base
})

const variantRows = computed(() => {
  return variants.value.map(v => {
    const row = { ...v }
    for (const attr of variantAttributeDefs.value) {
      const vals = variantAttrValuesMap.value[v.id] || []
      const attrVal = vals.find(av => (av.attribute_id || av.attribute?.id) === attr.id)
      row[`_va_${attr.id}`] = attrVal
        ? (attrVal.value_string ?? attrVal.value_number ?? attrVal.value_date ?? (attrVal.value_flag !== null ? (attrVal.value_flag ? 'Ja' : 'Nein') : '') ?? '')
        : ''
    }
    return row
  })
})

async function loadVariants() {
  if (variantsLoaded.value || !product.value) return
  variantsLoading.value = true
  try {
    const { data } = await productsApi.getVariants(product.value.id)
    variants.value = data.data || data
    variantsLoaded.value = true

    // Load variant attribute definitions
    try {
      const { data: attrData } = await attributesApiDefault.listVariantAttributes()
      variantAttributeDefs.value = attrData.data || attrData
    } catch { /* no variant attributes */ }

    // Load attribute values for each variant
    if (variantAttributeDefs.value.length > 0 && variants.value.length > 0) {
      const promises = variants.value.map(async (v) => {
        try {
          const { data: valData } = await productsApi.getAttributeValues(v.id)
          return { id: v.id, values: valData.data || valData }
        } catch { return { id: v.id, values: [] } }
      })
      const results = await Promise.all(promises)
      const map = {}
      for (const r of results) {
        map[r.id] = Array.isArray(r.values) ? r.values : []
      }
      variantAttrValuesMap.value = map
    }
  } catch { /* silently fail */ }
  finally { variantsLoading.value = false }
}

async function createVariant() {
  variantSaving.value = true
  variantErrors.value = {}
  try {
    await productsApi.createVariant(product.value.id, variantForm.value)
    showVariantForm.value = false
    variantForm.value = { sku: '', name: '', ean: '', status: 'draft' }
    variantsLoaded.value = false
    await loadVariants()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        variantErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { variantSaving.value = false }
}

// ─── Media ────────────────────────────────────────────
const mediaItems = ref([])
const mediaLoaded = ref(false)
const mediaLoading = ref(false)
const showMediaPicker = ref(false)
const availableMedia = ref([])
const mediaPickerLoading = ref(false)

async function loadMedia() {
  if (mediaLoaded.value || !product.value) return
  mediaLoading.value = true
  try {
    const { data } = await productsApi.getMedia(product.value.id)
    mediaItems.value = data.data || data
    mediaLoaded.value = true
  } catch { /* silently fail */ }
  finally { mediaLoading.value = false }
}

async function openMediaPicker() {
  showMediaPicker.value = true
  mediaPickerLoading.value = true
  try {
    const { data } = await mediaApi.list({ perPage: 100 })
    availableMedia.value = data.data || data
  } catch { /* silently fail */ }
  finally { mediaPickerLoading.value = false }
}

async function attachMedia(mediaItem) {
  try {
    await productsApi.attachMedia(product.value.id, {
      media_id: mediaItem.id,
      usage_type: 'default',
      sort_order: mediaItems.value.length,
    })
    showMediaPicker.value = false
    mediaLoaded.value = false
    await loadMedia()
  } catch { /* silently fail */ }
}

async function detachMedia(item) {
  const pivotId = item.pivot?.id || item.id
  try {
    await productsApi.detachMedia(pivotId)
    mediaLoaded.value = false
    await loadMedia()
  } catch { /* silently fail */ }
}

function getMediaUrl(item) {
  const fname = item.file_name || item.media?.file_name
  if (fname) return mediaApi.fileUrl(fname)
  return item.url || ''
}

// ─── Prices ───────────────────────────────────────────
const prices = ref([])
const pricesLoaded = ref(false)
const pricesLoading = ref(false)
const priceTypesList = ref([])
const showPriceForm = ref(false)
const priceForm = ref({ price_type_id: '', amount: '', currency: 'EUR', valid_from: '', valid_to: '', country: '', scale_from: '', scale_to: '' })
const priceErrors = ref({})
const priceSaving = ref(false)
const priceEditId = ref(null)
const priceDeleteTarget = ref(null)
const priceDeleting = ref(false)

const priceColumns = [
  { key: 'price_type.name_de', label: 'Preistyp' },
  { key: 'amount', label: 'Betrag', align: 'right' },
  { key: 'currency', label: 'Währung' },
  { key: 'valid_from', label: 'Gültig ab' },
  { key: 'valid_to', label: 'Gültig bis' },
  { key: 'country', label: 'Land' },
]

async function loadPrices() {
  if (pricesLoaded.value || !product.value) return
  pricesLoading.value = true
  try {
    const [pricesResp, typesResp] = await Promise.all([
      productsApi.getPrices(product.value.id),
      priceTypesList.value.length ? Promise.resolve(null) : priceTypes.list(),
    ])
    prices.value = pricesResp.data.data || pricesResp.data
    if (typesResp) priceTypesList.value = typesResp.data.data || typesResp.data
    pricesLoaded.value = true
  } catch { /* silently fail */ }
  finally { pricesLoading.value = false }
}

function openPriceForm(price = null) {
  if (price) {
    priceEditId.value = price.id
    priceForm.value = {
      price_type_id: price.price_type_id || price.price_type?.id || '',
      amount: price.amount || '',
      currency: price.currency || 'EUR',
      valid_from: price.valid_from ? price.valid_from.substring(0, 10) : '',
      valid_to: price.valid_to ? price.valid_to.substring(0, 10) : '',
      country: price.country || '',
      scale_from: price.scale_from || '',
      scale_to: price.scale_to || '',
    }
  } else {
    priceEditId.value = null
    priceForm.value = { price_type_id: '', amount: '', currency: 'EUR', valid_from: '', valid_to: '', country: '', scale_from: '', scale_to: '' }
  }
  priceErrors.value = {}
  showPriceForm.value = true
}

async function savePrice() {
  priceSaving.value = true
  priceErrors.value = {}
  const payload = { ...priceForm.value }
  if (!payload.valid_to) delete payload.valid_to
  if (!payload.country) delete payload.country
  if (!payload.scale_from) delete payload.scale_from
  if (!payload.scale_to) delete payload.scale_to
  try {
    if (priceEditId.value) {
      await productsApi.updatePrice(priceEditId.value, payload)
    } else {
      await productsApi.createPrice(product.value.id, payload)
    }
    showPriceForm.value = false
    pricesLoaded.value = false
    await loadPrices()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        priceErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { priceSaving.value = false }
}

async function confirmDeletePrice() {
  priceDeleting.value = true
  try {
    await productsApi.deletePrice(priceDeleteTarget.value.id)
    priceDeleteTarget.value = null
    pricesLoaded.value = false
    await loadPrices()
  } finally { priceDeleting.value = false }
}

// ─── Relations ────────────────────────────────────────
const relations = ref([])
const relationsLoaded = ref(false)
const relationsLoading = ref(false)
const relationTypesList = ref([])
const showRelationForm = ref(false)
const relationForm = ref({ relation_type_id: '', target_product_id: '', sort_order: 0 })
const relationErrors = ref({})
const relationSaving = ref(false)
const relationDeleteTarget = ref(null)
const relationDeleting = ref(false)
const productSearch = ref('')
const productSearchResults = ref([])
const productSearching = ref(false)

const relationColumns = [
  { key: 'relation_type.name_de', label: 'Beziehungstyp' },
  { key: 'target_product.sku', label: 'Ziel-SKU', mono: true },
  { key: 'target_product.name', label: 'Zielprodukt' },
  { key: 'sort_order', label: 'Reihenfolge' },
]

async function loadRelations() {
  if (relationsLoaded.value || !product.value) return
  relationsLoading.value = true
  try {
    const [relResp, typesResp] = await Promise.all([
      productsApi.getRelations(product.value.id),
      relationTypesList.value.length ? Promise.resolve(null) : relationTypes.list(),
    ])
    relations.value = relResp.data.data || relResp.data
    if (typesResp) relationTypesList.value = typesResp.data.data || typesResp.data
    relationsLoaded.value = true
  } catch { /* silently fail */ }
  finally { relationsLoading.value = false }
}

let searchTimeout = null
function searchProducts() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (!productSearch.value.trim()) { productSearchResults.value = []; return }
    productSearching.value = true
    try {
      const { data } = await productsApi.list({ search: productSearch.value, perPage: 10 })
      productSearchResults.value = (data.data || data).filter(p => p.id !== product.value.id)
    } catch { productSearchResults.value = [] }
    finally { productSearching.value = false }
  }, 300)
}

function selectTargetProduct(p) {
  relationForm.value.target_product_id = p.id
  productSearch.value = `${p.sku} — ${p.name || ''}`
  productSearchResults.value = []
}

async function createRelation() {
  relationSaving.value = true
  relationErrors.value = {}
  try {
    await productsApi.createRelation(product.value.id, relationForm.value)
    showRelationForm.value = false
    relationForm.value = { relation_type_id: '', target_product_id: '', sort_order: 0 }
    productSearch.value = ''
    relationsLoaded.value = false
    await loadRelations()
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors || {}
      for (const [key, val] of Object.entries(errs)) {
        relationErrors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally { relationSaving.value = false }
}

async function confirmDeleteRelation() {
  relationDeleting.value = true
  try {
    await productsApi.deleteRelation(relationDeleteTarget.value.id)
    relationDeleteTarget.value = null
    relationsLoaded.value = false
    await loadRelations()
  } finally { relationDeleting.value = false }
}

// ─── Preview (Generic) ───────────────────────────────
const previewData = ref(null)
const previewLoading = ref(false)
const completenessData = ref(null)

async function loadPreview() {
  if (!product.value) return
  previewLoading.value = true
  try {
    const [prevResp, compResp] = await Promise.all([
      productsApi.getPreview(product.value.id),
      productsApi.getCompleteness(product.value.id),
    ])
    previewData.value = prevResp.data.data || prevResp.data
    completenessData.value = compResp.data.data || compResp.data
  } catch { /* silently fail */ }
  finally { previewLoading.value = false }
}

const previewVariantColumns = computed(() => {
  const base = [
    { key: 'sku', label: 'SKU', mono: true },
    { key: 'name', label: 'Name' },
    { key: 'ean', label: 'EAN', mono: true },
    { key: 'status', label: 'Status' },
  ]
  if (previewData.value?.variants?.[0]?.variant_attributes?.length) {
    for (const va of previewData.value.variants[0].variant_attributes) {
      base.push({ key: `_pva_${va.label}`, label: va.label })
    }
  }
  return base
})

const previewVariantRows = computed(() => {
  if (!previewData.value?.variants) return []
  return previewData.value.variants.map(v => {
    const row = { ...v }
    if (v.variant_attributes) {
      for (const va of v.variant_attributes) {
        row[`_pva_${va.label}`] = va.value ? `${va.value}${va.unit ? ' ' + va.unit : ''}` : '—'
      }
    }
    return row
  })
})

const excelLoading = ref(false)
const pdfLoading = ref(false)
const downloadError = ref(null)

function triggerBlobDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.style.display = 'none'
  document.body.appendChild(a)
  a.click()
  setTimeout(() => {
    URL.revokeObjectURL(url)
    document.body.removeChild(a)
  }, 100)
}

async function downloadExcel() {
  excelLoading.value = true
  downloadError.value = null
  try {
    const resp = await productsApi.downloadPreviewExcel(product.value.id)
    triggerBlobDownload(resp.data, `product-preview-${product.value.sku || product.value.id}.xlsx`)
  } catch (err) {
    console.error('Excel download failed:', err)
    downloadError.value = 'Excel-Download fehlgeschlagen'
  } finally {
    excelLoading.value = false
  }
}

async function downloadPdf() {
  pdfLoading.value = true
  downloadError.value = null
  try {
    const resp = await productsApi.downloadPreviewPdf(product.value.id)
    triggerBlobDownload(resp.data, `product-preview-${product.value.sku || product.value.id}.pdf`)
  } catch (err) {
    console.error('PDF download failed:', err)
    downloadError.value = 'PDF-Download fehlgeschlagen'
  } finally {
    pdfLoading.value = false
  }
}

// ─── Save ─────────────────────────────────────────────
async function save() {
  if (!product.value) return
  saving.value = true
  try {
    // Save base product fields
    await store.update(product.value.id, {
      name: product.value.name,
      status: product.value.status,
      ean: product.value.ean,
      master_hierarchy_node_id: product.value.master_hierarchy_node_id || null,
    })
    // Save attribute values if any changed
    if (Object.keys(attributeValues.value).length > 0) {
      const values = Object.entries(attributeValues.value).map(([attribute_id, value]) => ({
        attribute_id,
        value,
      }))
      await store.saveAttributeValues(product.value.id, values)
    }
  } finally {
    saving.value = false
  }
}

// ─── Hierarchy node loading ──────────────────────────
async function loadHierarchies() {
  try {
    const { data } = await hierarchiesApi.list()
    hierarchies.value = data.data || data
    // Load tree for the hierarchy that the product belongs to
    if (product.value?.master_hierarchy_node_id) {
      for (const h of hierarchies.value) {
        await loadHierarchyTree(h.id)
      }
    } else if (hierarchies.value.length > 0) {
      await loadHierarchyTree(hierarchies.value[0].id)
    }
  } catch { /* silently fail */ }
}

async function loadHierarchyTree(hierarchyId) {
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    const tree = data.data || data
    const flatNodes = flattenTree(tree, hierarchyId)
    hierarchyNodes.value = [...hierarchyNodes.value.filter(n => n._hierarchyId !== hierarchyId), ...flatNodes]
    if (!selectedHierarchyId.value) selectedHierarchyId.value = hierarchyId
  } catch { /* silently fail */ }
}

function flattenTree(nodes, hierarchyId, prefix = '') {
  const result = []
  for (const node of (Array.isArray(nodes) ? nodes : [])) {
    const label = prefix + (node.name_de || node.name_en || node.id)
    result.push({ id: node.id, label, _hierarchyId: hierarchyId })
    if (node.children?.length) {
      result.push(...flattenTree(node.children, hierarchyId, label + ' › '))
    }
  }
  return result
}

async function onHierarchyChange(hierarchyId) {
  selectedHierarchyId.value = hierarchyId
  await loadHierarchyTree(hierarchyId)
}

// ─── Tab lazy loading ─────────────────────────────────
watch(activeTab, (tab) => {
  if (tab === 'attributes') loadAttributeData()
  if (tab === 'variant-attributes') loadAttributeData()
  if (tab === 'variants') loadVariants()
  if (tab === 'media') loadMedia()
  if (tab === 'prices') loadPrices()
  if (tab === 'relations') loadRelations()
  if (tab === 'preview') loadPreview()
})

onMounted(async () => {
  await store.fetchOne(route.params.id)
  loadAttributeData()
  loadHierarchies()
})

// Re-load when navigating between products/variants (same component, different ID)
watch(() => route.params.id, async (newId, oldId) => {
  if (newId === oldId) return

  // Reset all loaded flags
  attrLoaded.value = false
  variantsLoaded.value = false
  mediaLoaded.value = false
  pricesLoaded.value = false
  relationsLoaded.value = false

  // Clear stale data
  schema.value = null
  attributeValues.value = {}
  variants.value = []
  variantAttributeDefs.value = []
  variantAttrValuesMap.value = {}
  mediaItems.value = []
  prices.value = []
  relations.value = []
  previewData.value = null
  completenessData.value = null

  // Close open forms
  showVariantForm.value = false
  showPriceForm.value = false
  showRelationForm.value = false
  showMediaPicker.value = false

  // Reset tab to attributes
  activeTab.value = 'attributes'

  // Reload product/variant data
  await store.fetchOne(newId)
  loadAttributeData()
  loadHierarchies()
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="pim-btn pim-btn-ghost p-1.5" @click="router.push('/products')">
        <ArrowLeft class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <div class="flex-1">
        <div v-if="store.loading" class="space-y-2">
          <div class="pim-skeleton h-5 w-48 rounded" />
          <div class="pim-skeleton h-3 w-32 rounded" />
        </div>
        <template v-else-if="product">
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">
              {{ product.name || product.sku }}
            </h2>
            <span v-if="product.product_type_ref === 'variant'" class="pim-badge bg-purple-100 text-purple-700 text-[10px] px-1.5 py-0.5 rounded">
              Variante
            </span>
          </div>
          <p class="text-xs text-[var(--color-text-tertiary)] font-mono">
            {{ product.sku }}
            <span v-if="product.parent_product_id" class="ml-2 text-[var(--color-text-tertiary)]">
              (Parent: {{ product.parent_product_id.substring(0, 8) }}…)
            </span>
          </p>
        </template>
      </div>
      <button class="pim-btn pim-btn-primary" :disabled="saving" @click="save">
        <Save class="w-4 h-4" :stroke-width="1.75" />
        {{ saving ? 'Speichern…' : t('common.save') }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[var(--color-border)]">
      <nav class="flex gap-0 -mb-px">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="[
            'px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
              : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:border-[var(--color-border)]',
          ]"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Tab content -->
    <div v-if="store.loading" class="space-y-4">
      <div class="pim-card p-6">
        <div class="space-y-4">
          <div class="pim-skeleton h-4 w-1/3 rounded" />
          <div class="pim-skeleton h-8 w-full rounded" />
          <div class="pim-skeleton h-4 w-1/4 rounded" />
          <div class="pim-skeleton h-8 w-full rounded" />
        </div>
      </div>
    </div>

    <!-- ═══ Attributes Tab ═══ -->
    <div v-else-if="activeTab === 'attributes' && product" class="space-y-3">
      <!-- Base fields (always shown) -->
      <PimCollectionGroup title="Stammdaten" :filledCount="3" :totalCount="5">
        <div class="space-y-3 pt-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SKU</label>
            <input class="pim-input font-mono" :value="product.sku" readonly />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
            <input class="pim-input" v-model="product.name" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">EAN</label>
            <input class="pim-input font-mono" v-model="product.ean" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Status</label>
            <PimAttributeInput
              type="select"
              v-model="product.status"
              :options="[{ value: 'active', label: 'Aktiv' }, { value: 'draft', label: 'Entwurf' }, { value: 'inactive', label: 'Inaktiv' }, { value: 'discontinued', label: 'Auslaufend' }]"
            />
          </div>
          <div v-if="product.product_type_ref !== 'variant'">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Master-Hierarchie-Knoten</label>
            <div class="flex gap-2">
              <select v-if="hierarchies.length > 1" class="pim-input text-xs w-36 shrink-0" :value="selectedHierarchyId" @change="onHierarchyChange($event.target.value)">
                <option v-for="h in hierarchies" :key="h.id" :value="h.id">{{ h.name_de || h.technical_name }}</option>
              </select>
              <select class="pim-input text-xs flex-1" v-model="product.master_hierarchy_node_id">
                <option value="">— Kein Knoten —</option>
                <option v-for="node in hierarchyNodes" :key="node.id" :value="node.id">{{ node.label }}</option>
              </select>
            </div>
          </div>
        </div>
      </PimCollectionGroup>

      <PimCollectionGroup title="Beschreibung" :filledCount="1" :totalCount="3" :defaultOpen="false">
        <div class="space-y-3 pt-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Kurzbeschreibung</label>
            <PimAttributeInput type="textarea" v-model="product.description_short" />
          </div>
        </div>
      </PimCollectionGroup>

      <!-- Dynamic attributes from product type schema -->
      <PimCollectionGroup
        v-for="group in attributeGroups"
        :key="group.name"
        :title="group.name"
        :filledCount="group.attributes.filter(a => attributeValues[a.id]).length"
        :totalCount="group.attributes.length"
        :defaultOpen="false"
      >
        <div class="space-y-3 pt-3">
          <div v-for="attr in group.attributes" :key="attr.id">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
              {{ attr.name_de || attr.technical_name }}
              <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
              <span v-if="attr._is_inherited" class="ml-1 text-[10px] text-blue-500 font-normal">(vererbt)</span>
            </label>
            <!-- Composite: Button with summary -->
            <button
              v-if="attr.data_type === 'Composite'"
              class="w-full flex items-center justify-between pim-input text-left cursor-pointer hover:border-[var(--color-accent)] transition-colors"
              :disabled="attr._access === 'read_only'"
              @click="openCompositeModal(attr)"
            >
              <span class="text-[13px]" :class="getCompositeSummary(attr) ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)]'">
                {{ getCompositeSummary(attr) || 'Bearbeiten…' }}
              </span>
              <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0 ml-2">{{ (attr._children || []).length }} Felder</span>
            </button>
            <!-- Normal attribute -->
            <PimAttributeInput
              v-else
              :type="mapDataTypeToInput(attr.data_type)"
              :modelValue="attributeValues[attr.id]"
              :options="attr.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || []"
              :disabled="attr._access === 'read_only'"
              @update:modelValue="attributeValues[attr.id] = $event"
            />
          </div>
        </div>
      </PimCollectionGroup>

      <!-- Composite Modal -->
      <PimCompositeModal
        :open="compositeModalOpen"
        :compositeAttribute="activeComposite ? { ...activeComposite, children: activeComposite._children || [] } : null"
        :modelValue="attributeValues"
        :mapType="mapDataTypeToInput"
        @update:open="compositeModalOpen = $event"
        @update:modelValue="onCompositeValuesUpdate"
      />
    </div>

    <!-- ═══ Variant Attributes Tab ═══ -->
    <div v-else-if="activeTab === 'variant-attributes' && product" class="space-y-3">
      <template v-if="variantAttributeGroups.length > 0">
        <PimCollectionGroup
          v-for="group in variantAttributeGroups"
          :key="group.name"
          :title="group.name"
          :filledCount="group.attributes.filter(a => attributeValues[a.id]).length"
          :totalCount="group.attributes.length"
          :defaultOpen="true"
        >
          <div class="space-y-3 pt-3">
            <div v-for="attr in group.attributes" :key="attr.id">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                {{ attr.name_de || attr.technical_name }}
                <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
                <span v-if="attr._is_inherited" class="ml-1 text-[10px] text-blue-500 font-normal">(vererbt)</span>
              </label>
              <PimAttributeInput
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="attributeValues[attr.id]"
                :options="attr.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || []"
                :disabled="attr._access === 'read_only'"
                @update:modelValue="attributeValues[attr.id] = $event"
              />
            </div>
          </div>
        </PimCollectionGroup>
      </template>
      <div v-else class="pim-card p-12 text-center">
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Varianten-Attribute zugewiesen</p>
      </div>
    </div>

    <!-- ═══ Variants Tab ═══ -->
    <div v-else-if="activeTab === 'variants' && product" class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Varianten</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="showVariantForm = !showVariantForm">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neue Variante
        </button>
      </div>

      <!-- Variant creation form -->
      <div v-if="showVariantForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SKU <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" v-model="variantForm.sku" />
            <p v-if="variantErrors.sku" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ variantErrors.sku }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" v-model="variantForm.name" />
            <p v-if="variantErrors.name" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ variantErrors.name }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">EAN</label>
            <input class="pim-input" v-model="variantForm.ean" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Status</label>
            <PimAttributeInput type="select" v-model="variantForm.status" :options="[{ value: 'draft', label: 'Entwurf' }, { value: 'active', label: 'Aktiv' }]" />
          </div>
        </div>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="variantSaving" @click="createVariant">
            {{ variantSaving ? 'Speichern…' : 'Erstellen' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showVariantForm = false">Abbrechen</button>
        </div>
      </div>

      <PimTable
        :columns="variantColumns"
        :rows="variantRows"
        :loading="variantsLoading"
        emptyText="Keine Varianten vorhanden"
        @row-click="(row) => router.push(`/products/${row.id}`)"
      >
        <template #cell-status="{ value }">
          <span :class="['pim-badge', value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
            {{ value === 'active' ? 'Aktiv' : 'Entwurf' }}
          </span>
        </template>
      </PimTable>
    </div>

    <!-- ═══ Media Tab ═══ -->
    <div v-else-if="activeTab === 'media' && product" class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Medien</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="openMediaPicker">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Medium zuordnen
        </button>
      </div>

      <div v-if="mediaLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="i in 4" :key="i" class="pim-skeleton aspect-square rounded-lg" />
      </div>
      <div v-else-if="mediaItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="m in mediaItems" :key="m.id" class="pim-card overflow-hidden group relative">
          <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
            <img :src="getMediaUrl(m)" class="w-full h-full object-cover" loading="lazy" alt="" />
          </div>
          <div class="p-2 flex items-center justify-between">
            <span class="text-[11px] text-[var(--color-text-primary)] truncate flex-1">{{ m.file_name || m.media?.file_name || '—' }}</span>
            <button class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors" @click="detachMedia(m)">
              <X class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
          </div>
        </div>
      </div>
      <div v-else class="pim-card p-12 text-center">
        <Image class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <p class="text-sm text-[var(--color-text-tertiary)]">Keine Medien zugeordnet</p>
      </div>

      <!-- Media picker modal -->
      <Teleport to="body">
        <transition name="fade">
          <div v-if="showMediaPicker" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showMediaPicker = false" />
            <div class="relative w-full max-w-[600px] max-h-[80vh] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
              <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
                <span class="text-sm font-semibold">Medium auswählen</span>
                <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="showMediaPicker = false">
                  <X class="w-4 h-4" :stroke-width="2" />
                </button>
              </div>
              <div class="flex-1 overflow-y-auto p-4">
                <div v-if="mediaPickerLoading" class="grid grid-cols-3 gap-2">
                  <div v-for="i in 6" :key="i" class="pim-skeleton aspect-square rounded" />
                </div>
                <div v-else class="grid grid-cols-3 gap-2">
                  <div
                    v-for="m in availableMedia"
                    :key="m.id"
                    class="aspect-square bg-[var(--color-bg)] rounded overflow-hidden cursor-pointer hover:ring-2 hover:ring-[var(--color-accent)] transition-all"
                    @click="attachMedia(m)"
                  >
                    <img :src="mediaApi.fileUrl(m.file_name)" class="w-full h-full object-cover" loading="lazy" alt="" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </transition>
      </Teleport>
    </div>

    <!-- ═══ Prices Tab ═══ -->
    <div v-else-if="activeTab === 'prices' && product" class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Preise</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="openPriceForm()">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neuer Preis
        </button>
      </div>

      <!-- Price creation/edit form -->
      <div v-if="showPriceForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Preistyp <span class="text-[var(--color-error)]">*</span></label>
            <PimAttributeInput type="select" v-model="priceForm.price_type_id" :options="priceTypesList.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))" />
            <p v-if="priceErrors.price_type_id" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ priceErrors.price_type_id }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Betrag <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" type="number" step="0.01" v-model="priceForm.amount" />
            <p v-if="priceErrors.amount" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ priceErrors.amount }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Währung <span class="text-[var(--color-error)]">*</span></label>
            <PimAttributeInput type="select" v-model="priceForm.currency" :options="[{ value: 'EUR', label: 'EUR' }, { value: 'USD', label: 'USD' }, { value: 'CHF', label: 'CHF' }, { value: 'GBP', label: 'GBP' }]" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Land</label>
            <input class="pim-input" v-model="priceForm.country" placeholder="z.B. DE" maxlength="2" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig ab <span class="text-[var(--color-error)]">*</span></label>
            <input class="pim-input" type="date" v-model="priceForm.valid_from" />
            <p v-if="priceErrors.valid_from" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ priceErrors.valid_from }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Gültig bis</label>
            <input class="pim-input" type="date" v-model="priceForm.valid_to" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Staffel von</label>
            <input class="pim-input" type="number" v-model="priceForm.scale_from" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Staffel bis</label>
            <input class="pim-input" type="number" v-model="priceForm.scale_to" />
          </div>
        </div>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="priceSaving" @click="savePrice">
            {{ priceSaving ? 'Speichern…' : 'Speichern' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showPriceForm = false">Abbrechen</button>
        </div>
      </div>

      <PimTable
        :columns="priceColumns"
        :rows="prices"
        :loading="pricesLoading"
        emptyText="Keine Preise vorhanden"
        @row-click="openPriceForm"
        @row-action="(row) => priceDeleteTarget = row"
      >
        <template #cell-amount="{ value }">
          <span class="font-mono">{{ value ? Number(value).toFixed(2) : '—' }}</span>
        </template>
        <template #cell-valid_from="{ value }">
          <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
        </template>
        <template #cell-valid_to="{ value }">
          <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
        </template>
      </PimTable>

      <PimConfirmDialog
        :open="!!priceDeleteTarget"
        title="Preis löschen?"
        message="Dieser Preis wird unwiderruflich gelöscht."
        :loading="priceDeleting"
        @confirm="confirmDeletePrice"
        @cancel="priceDeleteTarget = null"
      />
    </div>

    <!-- ═══ Relations Tab ═══ -->
    <div v-else-if="activeTab === 'relations' && product" class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Produktbeziehungen</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="showRelationForm = !showRelationForm">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neue Beziehung
        </button>
      </div>

      <!-- Relation creation form -->
      <div v-if="showRelationForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beziehungstyp <span class="text-[var(--color-error)]">*</span></label>
            <PimAttributeInput type="select" v-model="relationForm.relation_type_id" :options="relationTypesList.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))" />
            <p v-if="relationErrors.relation_type_id" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ relationErrors.relation_type_id }}</p>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Reihenfolge</label>
            <input class="pim-input" type="number" v-model.number="relationForm.sort_order" />
          </div>
          <div class="col-span-2 relative">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Zielprodukt <span class="text-[var(--color-error)]">*</span></label>
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
              <input class="pim-input pl-9" v-model="productSearch" placeholder="SKU oder Name suchen…" @input="searchProducts" />
            </div>
            <p v-if="relationErrors.target_product_id" class="text-[11px] text-[var(--color-error)] mt-0.5">{{ relationErrors.target_product_id }}</p>
            <!-- Search results dropdown -->
            <div v-if="productSearchResults.length > 0" class="absolute z-10 w-full mt-1 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg max-h-48 overflow-y-auto">
              <div
                v-for="p in productSearchResults"
                :key="p.id"
                class="px-3 py-2 hover:bg-[var(--color-bg)] cursor-pointer flex items-center gap-2"
                @click="selectTargetProduct(p)"
              >
                <span class="text-xs font-mono text-[var(--color-text-secondary)]">{{ p.sku }}</span>
                <span class="text-xs">{{ p.name }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="relationSaving" @click="createRelation">
            {{ relationSaving ? 'Speichern…' : 'Erstellen' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showRelationForm = false">Abbrechen</button>
        </div>
      </div>

      <PimTable
        :columns="relationColumns"
        :rows="relations"
        :loading="relationsLoading"
        emptyText="Keine Beziehungen vorhanden"
        @row-action="(row) => relationDeleteTarget = row"
      />

      <PimConfirmDialog
        :open="!!relationDeleteTarget"
        title="Beziehung löschen?"
        message="Diese Produktbeziehung wird entfernt."
        :loading="relationDeleting"
        @confirm="confirmDeleteRelation"
        @cancel="relationDeleteTarget = null"
      />
    </div>

    <!-- ═══ Preview Tab (Generic) ═══ -->
    <div v-else-if="activeTab === 'preview' && product" class="space-y-3">
      <!-- Header with export buttons -->
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Produktvorschau</h3>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-secondary text-xs" :disabled="excelLoading" @click="downloadExcel">
            <Download v-if="!excelLoading" class="w-3.5 h-3.5" :stroke-width="1.75" />
            <span v-else class="w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin inline-block" />
            Excel
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" :disabled="pdfLoading" @click="downloadPdf">
            <Download v-if="!pdfLoading" class="w-3.5 h-3.5" :stroke-width="1.75" />
            <span v-else class="w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin inline-block" />
            PDF
          </button>
        </div>
      </div>
      <div v-if="downloadError" class="text-xs text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded">
        {{ downloadError }}
      </div>

      <!-- Loading state -->
      <div v-if="previewLoading" class="space-y-3">
        <div class="pim-card p-6"><div class="pim-skeleton h-20 w-full rounded" /></div>
        <div class="pim-card p-6"><div class="pim-skeleton h-32 w-full rounded" /></div>
        <div class="pim-card p-6"><div class="pim-skeleton h-24 w-full rounded" /></div>
      </div>

      <template v-else-if="previewData">
        <!-- Completeness Gauge -->
        <div v-if="completenessData" class="pim-card p-4">
          <div class="flex items-center gap-5">
            <div v-html="completenessData.chart_svg" class="shrink-0 w-[80px] h-[80px] [&>svg]:w-full [&>svg]:h-full" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-[var(--color-text-primary)]">
                Vollständigkeit: {{ completenessData.overall_percentage }}%
              </p>
              <p class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">
                {{ completenessData.filled_fields }} von {{ completenessData.total_fields }} Feldern befüllt
              </p>
              <div class="flex flex-wrap gap-1.5 mt-2">
                <span
                  v-for="s in completenessData.sections"
                  :key="s.name"
                  :class="[
                    'inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-medium',
                    s.percentage >= 100 ? 'bg-green-100 text-green-700' :
                    s.percentage >= 50 ? 'bg-amber-100 text-amber-700' :
                    'bg-red-100 text-red-700'
                  ]"
                >
                  {{ s.name }}: {{ s.percentage }}%
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stammdaten -->
        <PimCollectionGroup
          title="Stammdaten"
          :filledCount="Object.values(previewData.stammdaten).filter(v => v !== null && v !== '').length"
          :totalCount="Object.keys(previewData.stammdaten).length"
        >
          <div class="grid grid-cols-2 gap-x-6 gap-y-2 pt-3">
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">SKU</span>
              <p class="text-[13px] font-mono text-[var(--color-text-primary)]">{{ previewData.stammdaten.sku || '—' }}</p>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">EAN</span>
              <p class="text-[13px] font-mono text-[var(--color-text-primary)]">{{ previewData.stammdaten.ean || '—' }}</p>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">Name</span>
              <p class="text-[13px] text-[var(--color-text-primary)]">{{ previewData.stammdaten.name || '—' }}</p>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">Status</span>
              <span :class="[
                'pim-badge text-[11px]',
                previewData.stammdaten.status === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]'
              ]">
                {{ previewData.stammdaten.status || '—' }}
              </span>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">Produkttyp</span>
              <p class="text-[13px] text-[var(--color-text-primary)]">{{ previewData.stammdaten.product_type?.name || '—' }}</p>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">Hierarchie</span>
              <p class="text-[13px] text-[var(--color-text-primary)]">
                {{ previewData.stammdaten.category_breadcrumb?.map(b => b.name).join(' › ') || '—' }}
              </p>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">Erstellt</span>
              <p class="text-[13px] text-[var(--color-text-primary)]">
                {{ previewData.stammdaten.created_at ? new Date(previewData.stammdaten.created_at).toLocaleDateString('de-DE') : '—' }}
                <span v-if="previewData.stammdaten.created_by" class="text-[var(--color-text-tertiary)]">von {{ previewData.stammdaten.created_by }}</span>
              </p>
            </div>
            <div>
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">Aktualisiert</span>
              <p class="text-[13px] text-[var(--color-text-primary)]">
                {{ previewData.stammdaten.updated_at ? new Date(previewData.stammdaten.updated_at).toLocaleDateString('de-DE') : '—' }}
                <span v-if="previewData.stammdaten.updated_by" class="text-[var(--color-text-tertiary)]">von {{ previewData.stammdaten.updated_by }}</span>
              </p>
            </div>
          </div>
        </PimCollectionGroup>

        <!-- Attribute Sections -->
        <PimCollectionGroup
          v-for="section in previewData.attribute_sections"
          :key="section.section_name"
          :title="section.section_name"
          :filledCount="section.attributes.filter(a => a.display_value !== null).length"
          :totalCount="section.attributes.length"
          :defaultOpen="false"
        >
          <div class="space-y-0 pt-3">
            <div
              v-for="attr in section.attributes"
              :key="attr.attribute_id + (attr.language || '')"
              class="flex items-center justify-between py-1.5 border-b border-[var(--color-border)] last:border-0"
            >
              <span class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                {{ attr.label }}
                <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
                <span v-if="attr.language" class="text-[10px] text-[var(--color-text-tertiary)] ml-1">[{{ attr.language }}]</span>
              </span>
              <span class="text-[12px] text-[var(--color-text-primary)]">
                {{ attr.display_value || '—' }}
                <span v-if="attr.unit" class="text-[var(--color-text-tertiary)]">{{ attr.unit }}</span>
              </span>
            </div>
          </div>
        </PimCollectionGroup>

        <!-- Relations -->
        <PimCollectionGroup
          v-if="previewData.relations.length > 0"
          title="Beziehungen"
          :filledCount="previewData.relations.length"
          :totalCount="previewData.relations.length"
          :defaultOpen="false"
        >
          <div class="pt-3">
            <PimTable
              :columns="[
                { key: 'relation_type', label: 'Typ' },
                { key: 'target_product.sku', label: 'Ziel-SKU', mono: true },
                { key: 'target_product.name', label: 'Zielprodukt' },
                { key: 'sort_order', label: 'Reihenfolge' },
              ]"
              :rows="previewData.relations"
              emptyText="Keine Beziehungen"
            />
          </div>
        </PimCollectionGroup>

        <!-- Prices -->
        <PimCollectionGroup
          v-if="previewData.prices.length > 0"
          title="Preise"
          :filledCount="previewData.prices.length"
          :totalCount="previewData.prices.length"
          :defaultOpen="false"
        >
          <div class="pt-3">
            <PimTable
              :columns="[
                { key: 'price_type', label: 'Preistyp' },
                { key: 'amount', label: 'Betrag', align: 'right' },
                { key: 'currency', label: 'Währung' },
                { key: 'valid_from', label: 'Gültig ab' },
                { key: 'valid_to', label: 'Gültig bis' },
                { key: 'country', label: 'Land' },
              ]"
              :rows="previewData.prices"
              emptyText="Keine Preise"
            >
              <template #cell-amount="{ value }">
                <span class="font-mono">{{ value ? Number(value).toFixed(2) : '—' }}</span>
              </template>
              <template #cell-valid_from="{ value }">
                <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
              </template>
              <template #cell-valid_to="{ value }">
                <span class="text-xs">{{ value ? new Date(value).toLocaleDateString('de-DE') : '—' }}</span>
              </template>
            </PimTable>
          </div>
        </PimCollectionGroup>

        <!-- Media -->
        <PimCollectionGroup
          v-if="previewData.media.length > 0"
          title="Media"
          :filledCount="previewData.media.length"
          :totalCount="previewData.media.length"
          :defaultOpen="false"
        >
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 pt-3">
            <div v-for="m in previewData.media" :key="m.id" class="pim-card overflow-hidden">
              <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
                <img :src="m.url" class="w-full h-full object-cover" loading="lazy" :alt="m.alt || ''" />
              </div>
              <div class="p-2">
                <span class="text-[11px] text-[var(--color-text-primary)] truncate block">{{ m.file_name || '—' }}</span>
                <div class="flex items-center gap-1 mt-0.5">
                  <span v-if="m.is_primary" class="text-[10px] text-[var(--color-accent)] font-medium">Primär</span>
                  <span v-if="m.usage_type" class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.usage_type }}</span>
                </div>
              </div>
            </div>
          </div>
        </PimCollectionGroup>

        <!-- Variants -->
        <PimCollectionGroup
          v-if="previewData.variants.length > 0"
          title="Varianten"
          :filledCount="previewData.variants.length"
          :totalCount="previewData.variants.length"
          :defaultOpen="false"
        >
          <div class="pt-3">
            <PimTable
              :columns="previewVariantColumns"
              :rows="previewVariantRows"
              emptyText="Keine Varianten"
            >
              <template #cell-status="{ value }">
                <span :class="['pim-badge', value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
                  {{ value === 'active' ? 'Aktiv' : value || '—' }}
                </span>
              </template>
            </PimTable>
          </div>
        </PimCollectionGroup>
      </template>

      <!-- Empty state -->
      <div v-else class="pim-card p-12 text-center">
        <p class="text-sm text-[var(--color-text-tertiary)]">Vorschau konnte nicht geladen werden</p>
      </div>
    </div>

    <!-- ═══ Versions Tab ═══ -->
    <ProductVersionsTab
      v-else-if="activeTab === 'versions' && product"
      :productId="product.id"
    />
  </div>
</template>
