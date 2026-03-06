<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Save, Plus, Trash2, Image, Star, X, Search, Download, Languages, Copy, Sparkles } from 'lucide-vue-next'
import productsApi from '@/api/products'
import mediaApi from '@/api/media'
import { mediaUsageTypes } from '@/api/mediaUsageTypes'
import { priceTypes, relationTypes } from '@/api/prices'
import attributesApiDefault, { productTypes, valueLists } from '@/api/attributes'
import dictionaryApi from '@/api/dictionary'
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
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const { t } = useI18n()

const activeTab = ref('attributes')
const saving = ref(false)
const activeDataLang = ref(localeStore.activeDataLocales[0] || 'de')

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
  { key: 'output-hierarchies', label: 'Ausgabehierarchien' },
  { key: 'preview', label: t('product.preview') },
  { key: 'versions', label: t('product.versions') },
]

const product = computed(() => store.current)

const masterNodePath = computed(() => {
  const nodeId = product.value?.master_hierarchy_node_id
  if (!nodeId) return null
  return hierarchyNodes.value.find(n => n.id === nodeId)?.label || null
})

// ─── Attribute Values ─────────────────────────────────
const schema = ref(null)
const attributeValues = ref({})       // non-translatable: { attrId: value }
const translatedValues = ref({})      // translatable: { `${attrId}_${lang}`: value }
const attrLoaded = ref(false)
const valueListMap = ref({})
const dictionaryEntries = ref([])

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
  const parts = children.map(c => attributeValues.value[c.id])

  // Use composite_format if defined (e.g. "{0} x {1} x {2} mm")
  if (compositeAttr.composite_format) {
    let result = compositeAttr.composite_format
    children.forEach((_, i) => {
      result = result.replace(`{${i}}`, parts[i] !== undefined && parts[i] !== null ? String(parts[i]) : '')
    })
    return result.trim() || null
  }

  const filled = parts.filter(v => v !== undefined && v !== null && v !== '')
  if (filled.length === 0) return null
  return filled.join(' × ')
}

function mapDataTypeToInput(backendType) {
  const map = {
    'String': 'text', 'Number': 'number', 'Float': 'decimal',
    'Date': 'date', 'Flag': 'boolean', 'Selection': 'select',
    'Dictionary': 'dictionary', 'Composite': 'composite', 'RichText': 'richtext',
  }
  return map[backendType] || 'text'
}

function getSelectionOptions(attr) {
  // Try embedded value_list entries first (from attribute API with include)
  if (attr.value_list?.entries?.length) {
    return attr.value_list.entries.map(e => ({ value: e.id, label: e.display_value_de || e.value_de || e.label_de || e.code || e.technical_name }))
  }
  // Fallback to valueListMap (loaded separately for resolved attributes)
  const vlId = attr.value_list_id
  if (vlId && valueListMap.value[vlId]?.entries?.length) {
    return valueListMap.value[vlId].entries.map(e => ({ value: e.id, label: e.display_value_de || e.value_de || e.label_de || e.code || e.technical_name }))
  }
  return []
}

async function loadAttributeData(overrideNodeId = null) {
  if (attrLoaded.value || !product.value) return
  try {
    // Try resolved attributes from hierarchy first (includes inheritance info)
    let resolvedAttrs = null
    const nodeId = overrideNodeId || product.value.master_hierarchy_node_id
    if (nodeId) {
      try {
        const { data: resolvedData } = await productsApi.getResolvedAttributes(product.value.id, nodeId)
        resolvedAttrs = resolvedData.data || resolvedData
      } catch (e) { console.warn('Resolved attributes unavailable, falling back to schema:', e.message) }
    }

    if (resolvedAttrs && resolvedAttrs.length > 0) {
      // Use hierarchy-resolved attributes as schema
      schema.value = resolvedAttrs.map(ra => ({
        id: ra.attribute_id,
        technical_name: ra.attribute_technical_name,
        name_de: ra.attribute_name_de || ra.attribute_technical_name,
        name_en: ra.attribute_name_en,
        data_type: ra.data_type,
        value_list_id: ra.value_list_id || null,
        is_mandatory: ra.is_mandatory,
        is_translatable: ra.is_translatable,
        is_variant_attribute: ra.is_variant_attribute || false,
        parent_attribute_id: ra.parent_attribute_id || null,
        group: ra.collection_name || 'Vererbte Attribute',
        _source: ra.source,
        _is_inherited: ra.is_inherited,
        _access: ra.access_product,
      }))
      // Populate values from resolved data (primary language)
      for (const ra of resolvedAttrs) {
        if (ra.value !== null && ra.value !== undefined) {
          if (ra.is_translatable) {
            const lang = activeDataLang.value || 'de'
            translatedValues.value[`${ra.attribute_id}_${lang}`] = ra.value
          } else {
            attributeValues.value[ra.attribute_id] = ra.value
          }
        }
      }
    } else {
      // Fallback: load attribute values + product type schema
      const langs = localeStore.activeDataLocales.join(',')
      const { data: valData } = await productsApi.getAttributeValues(product.value.id, { lang: langs })
      const vals = valData.data || valData
      if (Array.isArray(vals)) {
        for (const val of vals) {
          const attrId = val.attribute_id || val.attribute?.id
          if (!attrId) continue
          const rawValue = val.value_string ?? val.value_number ?? val.value_date ?? val.value_flag ?? val.value_selection_id ?? ''
          if (val.language) {
            translatedValues.value[`${attrId}_${val.language}`] = rawValue
          } else {
            attributeValues.value[attrId] = rawValue
          }
        }
      }
      if (product.value.product_type_id) {
        try {
          const { data: schemaData } = await productTypes.getSchema(product.value.product_type_id)
          schema.value = schemaData.data || schemaData
        } catch (e) { console.warn('Product type schema not found:', e.message) }
      }
    }

    // Load translated values for all active data languages
    if (schema.value) {
      const translatableAttrs = (Array.isArray(schema.value) ? schema.value : []).filter(a => a.is_translatable)
      if (translatableAttrs.length > 0) {
        const langs = localeStore.activeDataLocales.join(',')
        try {
          const { data: tvData } = await productsApi.getAttributeValues(product.value.id, { lang: langs })
          const tvVals = tvData.data || tvData
          if (Array.isArray(tvVals)) {
            for (const val of tvVals) {
              const attrId = val.attribute_id || val.attribute?.id
              if (!attrId || !val.language) continue
              const rawValue = val.value_string ?? val.value_number ?? val.value_date ?? val.value_flag ?? val.value_selection_id ?? ''
              translatedValues.value[`${attrId}_${val.language}`] = rawValue
            }
          }
        } catch (e) { console.warn('Failed to load translated values:', e.message) }
      }
    }

    attrLoaded.value = true

    // Load value lists for Selection-type attributes
    const selectionAttrs = (Array.isArray(schema.value) ? schema.value : schema.value?.attributes || [])
      .filter(a => a.data_type === 'Selection' && a.value_list_id)
    if (selectionAttrs.length > 0) {
      try {
        const { data: vlData } = await valueLists.list({ include: 'entries', perPage: 200 })
        const allLists = vlData.data || vlData
        const map = {}
        for (const vl of allLists) {
          map[vl.id] = vl
        }
        valueListMap.value = map
      } catch (e) { console.error('Failed to load value lists:', e.message) }
    }

    // Load dictionary entries for Dictionary-type attributes
    const dictAttrs = (Array.isArray(schema.value) ? schema.value : schema.value?.attributes || [])
      .filter(a => a.data_type === 'Dictionary')
    if (dictAttrs.length > 0) {
      try {
        const { data: dictData } = await dictionaryApi.list({ perPage: 1000 })
        dictionaryEntries.value = (dictData.data || dictData).map(e => ({
          value: e.id,
          label: e.short_text_de || e.short_text_en || e.category || String(e.id),
        }))
      } catch (e) { console.error('Failed to load dictionary entries:', e.message) }
    }
  } catch (e) { console.error('Failed to load attribute data:', e.message) }
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
    } catch (e) { console.warn('Failed to load variant attribute definitions:', e.message) }

    // Load attribute values for each variant
    if (variantAttributeDefs.value.length > 0 && variants.value.length > 0) {
      const promises = variants.value.map(async (v) => {
        try {
          const { data: valData } = await productsApi.getAttributeValues(v.id)
          return { id: v.id, values: valData.data || valData }
        } catch (e) { console.warn(`Failed to load attribute values for variant ${v.id}:`, e.message); return { id: v.id, values: [] } }
      })
      const results = await Promise.all(promises)
      const map = {}
      for (const r of results) {
        map[r.id] = Array.isArray(r.values) ? r.values : []
      }
      variantAttrValuesMap.value = map
    }
  } catch (e) { console.error('Failed to load variants:', e.message) }
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

// ─── Variant Delete ──────────────────────────────────
const variantDeleteTarget = ref(null)
const variantDeleting = ref(false)

async function confirmDeleteVariant() {
  variantDeleting.value = true
  try {
    await productsApi.delete(variantDeleteTarget.value.id)
    variantDeleteTarget.value = null
    variantsLoaded.value = false
    await loadVariants()
  } finally { variantDeleting.value = false }
}

// ─── Product Copy ───────────────────────────────────
const showCopyDialog = ref(false)
const copyOptions = ref({
  include_attributes: true,
  include_prices: true,
  include_media: true,
  include_relations: true,
})
const copying = ref(false)

function selectAllCopyOptions(val) {
  copyOptions.value.include_attributes = val
  copyOptions.value.include_prices = val
  copyOptions.value.include_media = val
  copyOptions.value.include_relations = val
}

async function duplicateProduct() {
  copying.value = true
  try {
    const { data } = await productsApi.duplicate(product.value.id, copyOptions.value)
    const newId = data.data?.id || data.id
    showCopyDialog.value = false
    router.push(`/products/${newId}`)
  } catch (e) {
    console.error('Failed to duplicate product:', e.message)
    alert('Fehler beim Kopieren: ' + (e.response?.data?.message || e.message))
  } finally { copying.value = false }
}

// ─── Variant Generator ──────────────────────────────
const showGenerator = ref(false)
const generatorStep = ref(1)
const generatorDimensions = ref([])
const generatorSKUPrefix = ref('')
const generatorLoading = ref(false)
const generatorResult = ref(null)
const generatorExcluded = ref(new Set())

function initGenerator() {
  showGenerator.value = true
  generatorStep.value = 1
  generatorResult.value = null
  generatorExcluded.value = new Set()
  generatorSKUPrefix.value = product.value?.sku || ''
  generatorDimensions.value = variantAttributeDefs.value.map(attr => ({
    attribute_id: attr.id,
    attribute: attr,
    selected: false,
    values: [],
    textInput: '',
  }))
}

const generatorPreview = computed(() => {
  const activeDims = generatorDimensions.value.filter(d => d.selected && d.values.length > 0)
  if (activeDims.length === 0) return []
  // Cartesian product
  let combos = [[]]
  for (const dim of activeDims) {
    const next = []
    for (const combo of combos) {
      for (const val of dim.values) {
        next.push([...combo, { attribute_id: dim.attribute_id, label: dim.attribute.name_de || dim.attribute.technical_name, value: val }])
      }
    }
    combos = next
  }
  const prefix = generatorSKUPrefix.value || product.value?.sku || 'VAR'
  return combos.map((combo, idx) => {
    const slugParts = combo.map(c => String(c.value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '').substring(0, 20))
    const sku = `${prefix}-${slugParts.join('-')}`
    const name = `${product.value?.name || ''} — ${combo.map(c => c.value).join(' / ')}`
    return { idx, sku, name, combo }
  })
})

const generatorPreviewFiltered = computed(() => {
  return generatorPreview.value.filter(p => !generatorExcluded.value.has(p.idx))
})

const generatorTotalCombinations = computed(() => {
  const activeDims = generatorDimensions.value.filter(d => d.selected && d.values.length > 0)
  if (activeDims.length === 0) return 0
  return activeDims.reduce((acc, d) => acc * d.values.length, 1)
})

function toggleGeneratorRow(idx) {
  const s = new Set(generatorExcluded.value)
  if (s.has(idx)) s.delete(idx)
  else s.add(idx)
  generatorExcluded.value = s
}

function addDimensionValues(dim) {
  if (!dim.textInput.trim()) return
  const newVals = dim.textInput.split(',').map(v => v.trim()).filter(v => v)
  dim.values = [...new Set([...dim.values, ...newVals])]
  dim.textInput = ''
}

function removeDimensionValue(dim, val) {
  dim.values = dim.values.filter(v => v !== val)
}

function toggleValueListEntry(dim, entryValue) {
  const idx = dim.values.indexOf(entryValue)
  if (idx >= 0) dim.values.splice(idx, 1)
  else dim.values.push(entryValue)
}

async function runGenerator() {
  generatorLoading.value = true
  try {
    const activeDims = generatorDimensions.value.filter(d => d.selected && d.values.length > 0)
    const excluded = generatorExcluded.value
    // Filter out excluded combos by regenerating only included ones
    const dimensions = activeDims.map(d => ({
      attribute_id: d.attribute_id,
      values: d.values,
    }))
    const { data } = await productsApi.generateVariants(product.value.id, {
      dimensions,
      sku_prefix: generatorSKUPrefix.value || undefined,
      status: 'draft',
    })
    generatorResult.value = data
    generatorStep.value = 3
    // Reload variants
    variantsLoaded.value = false
    await loadVariants()
  } catch (e) {
    console.error('Failed to generate variants:', e.message)
    alert('Fehler: ' + (e.response?.data?.message || e.message))
  } finally { generatorLoading.value = false }
}

// ─── Variant Inheritance Rules ───────────────────────
const inheritanceRulesLoaded = ref(false)
const inheritanceRulesLoading = ref(false)
const inheritanceRulesSaving = ref(false)
const showInheritanceRules = ref(false)
const editedInheritanceRules = ref({})

const inheritedAttributeIds = computed(() => {
  const ids = new Set()
  for (const [attrId, mode] of Object.entries(editedInheritanceRules.value)) {
    if (mode === 'inherit') ids.add(attrId)
  }
  return ids
})

function isAttributeInherited(attrId) {
  if (product.value?.product_type_ref !== 'variant') return false
  return inheritedAttributeIds.value.has(attrId)
}

async function loadInheritanceRules() {
  if (inheritanceRulesLoaded.value || !product.value) return
  const rulesProductId = product.value.product_type_ref === 'variant'
    ? product.value.parent_product_id
    : product.value.id
  if (!rulesProductId) return
  inheritanceRulesLoading.value = true
  try {
    const { data } = await productsApi.getVariantRules(rulesProductId)
    const rules = data.data || data
    const map = {}
    for (const rule of rules) {
      map[rule.attribute_id] = rule.inheritance_mode
    }
    editedInheritanceRules.value = map
    inheritanceRulesLoaded.value = true
  } catch { /* silently fail */ }
  finally { inheritanceRulesLoading.value = false }
}

function toggleInheritance(attrId) {
  editedInheritanceRules.value = {
    ...editedInheritanceRules.value,
    [attrId]: (editedInheritanceRules.value[attrId] || 'override') === 'inherit' ? 'override' : 'inherit',
  }
}

async function saveInheritanceRules() {
  inheritanceRulesSaving.value = true
  try {
    const rules = Object.entries(editedInheritanceRules.value).map(([attribute_id, inheritance_mode]) => ({
      attribute_id,
      inheritance_mode,
    }))
    await productsApi.setVariantRules(product.value.id, rules)
  } catch { /* silently fail */ }
  finally { inheritanceRulesSaving.value = false }
}

// ─── Media ────────────────────────────────────────────
const mediaItems = ref([])
const mediaLoaded = ref(false)
const mediaLoading = ref(false)
const showMediaPicker = ref(false)
const availableMedia = ref([])
const mediaPickerLoading = ref(false)
const usageTypesList = ref([])
const selectedUsageTypeId = ref(null)

async function loadMedia() {
  if (mediaLoaded.value || !product.value) return
  mediaLoading.value = true
  try {
    const { data } = await productsApi.getMedia(product.value.id)
    mediaItems.value = data.data || data
    mediaLoaded.value = true
  } catch (e) { console.error('Failed to load media:', e.message) }
  finally { mediaLoading.value = false }
}

async function openMediaPicker() {
  showMediaPicker.value = true
  mediaPickerLoading.value = true
  try {
    const [mediaRes, typesRes] = await Promise.all([
      mediaApi.list({ perPage: 100 }),
      mediaUsageTypes.list(),
    ])
    availableMedia.value = mediaRes.data.data || mediaRes.data
    const types = typesRes.data.data || typesRes.data
    usageTypesList.value = types
    if (types.length > 0 && !selectedUsageTypeId.value) {
      selectedUsageTypeId.value = types[0].id
    }
  } catch (e) { console.error('Failed to load available media:', e.message) }
  finally { mediaPickerLoading.value = false }
}

async function attachMedia(mediaItem) {
  try {
    await productsApi.attachMedia(product.value.id, {
      media_id: mediaItem.id,
      usage_type_id: selectedUsageTypeId.value,
      sort_order: mediaItems.value.length,
    })
    showMediaPicker.value = false
    mediaLoaded.value = false
    await loadMedia()
  } catch (e) { console.error('Failed to attach media:', e.message) }
}

async function detachMedia(item) {
  const pivotId = item.pivot?.id || item.id
  try {
    await productsApi.detachMedia(pivotId)
    mediaLoaded.value = false
    await loadMedia()
  } catch (e) { console.error('Failed to detach media:', e.message) }
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
  } catch (e) { console.error('Failed to load prices:', e.message) }
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
  } catch (e) { console.error('Failed to load relations:', e.message) }
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
    } catch (e) { console.warn('Product search failed:', e.message); productSearchResults.value = [] }
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

function getPreviewCompositeSummary(compositeAttr, allAttrs) {
  const children = allAttrs.filter(a => a.parent_attribute_id === compositeAttr.attribute_id)
  const values = children.map(c => c.display_value)

  if (compositeAttr.composite_format) {
    let result = compositeAttr.composite_format
    children.forEach((_, i) => {
      result = result.replace(`{${i}}`, values[i] != null ? String(values[i]) : '')
    })
    return result.trim() || null
  }

  const filled = values.filter(v => v != null && v !== '')
  return filled.length > 0 ? filled.join(' × ') : null
}

// ─── Output Hierarchy Assignments ──────────────────────
const outputHierarchyAssignments = ref([])
const outputHierarchyLoading = ref(false)
const outputHierarchyLoaded = ref(false)
const showOutputHierarchyForm = ref(false)
const selectedOutputHierarchyId = ref(null)
const selectedOutputNodeId = ref(null)
const outputHierarchyNodeOptions = ref([])
const outputHierarchyDeleteTarget = ref(null)
const outputHierarchyDeleting = ref(false)

const outputHierarchies = computed(() => hierarchies.value.filter(h => h.hierarchy_type === 'output'))

async function loadOutputHierarchyAssignments() {
  if (outputHierarchyLoaded.value || !product.value) return
  outputHierarchyLoading.value = true
  try {
    const { data } = await productsApi.getOutputHierarchyAssignments(product.value.id)
    outputHierarchyAssignments.value = data.data || data
    outputHierarchyLoaded.value = true
  } catch (e) { console.error('Failed to load output hierarchy assignments:', e.message) }
  finally { outputHierarchyLoading.value = false }
}

async function onOutputHierarchyChange(hierarchyId) {
  selectedOutputHierarchyId.value = hierarchyId
  selectedOutputNodeId.value = null
  if (!hierarchyId) { outputHierarchyNodeOptions.value = []; return }
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    const tree = data.data || data
    outputHierarchyNodeOptions.value = flattenTree(tree, hierarchyId)
  } catch { outputHierarchyNodeOptions.value = [] }
}

async function assignOutputHierarchyNode() {
  if (!selectedOutputNodeId.value || !product.value) return
  try {
    await hierarchiesApi.assignOutputProduct(selectedOutputNodeId.value, { product_id: product.value.id })
    showOutputHierarchyForm.value = false
    selectedOutputHierarchyId.value = null
    selectedOutputNodeId.value = null
    outputHierarchyNodeOptions.value = []
    outputHierarchyLoaded.value = false
    await loadOutputHierarchyAssignments()
    showFeedback?.('Zuordnung erstellt') // showFeedback may not exist in this component
  } catch (e) {
    console.error('Failed to assign output hierarchy:', e.message)
  }
}

async function confirmDeleteOutputHierarchyAssignment() {
  if (!outputHierarchyDeleteTarget.value) return
  outputHierarchyDeleting.value = true
  try {
    await hierarchiesApi.removeOutputProductAssignment(outputHierarchyDeleteTarget.value.id)
    outputHierarchyDeleteTarget.value = null
    outputHierarchyLoaded.value = false
    await loadOutputHierarchyAssignments()
  } catch (e) { console.error('Failed to remove output hierarchy assignment:', e.message) }
  finally { outputHierarchyDeleting.value = false }
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
  } catch (e) { console.error('Failed to load preview:', e.message) }
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
  try {
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
  } finally {
    setTimeout(() => URL.revokeObjectURL(url), 200)
  }
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

    // Build attribute values payload with language support
    const values = []

    // Non-translatable attribute values
    for (const [attribute_id, value] of Object.entries(attributeValues.value)) {
      values.push({ attribute_id, value })
    }

    // Translatable attribute values (one entry per language)
    for (const [key, value] of Object.entries(translatedValues.value)) {
      const lastUnderscore = key.lastIndexOf('_')
      const attribute_id = key.substring(0, lastUnderscore)
      const language = key.substring(lastUnderscore + 1)
      values.push({ attribute_id, value, language })
    }

    if (values.length > 0) {
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
  } catch (e) { console.error('Failed to load hierarchies:', e.message) }
}

async function loadHierarchyTree(hierarchyId) {
  try {
    const { data } = await hierarchiesApi.getTree(hierarchyId)
    const tree = data.data || data
    const flatNodes = flattenTree(tree, hierarchyId)
    hierarchyNodes.value = [...hierarchyNodes.value.filter(n => n._hierarchyId !== hierarchyId), ...flatNodes]
    // Auto-select hierarchy that contains the product's current node
    const nodeId = product.value?.master_hierarchy_node_id
    if (nodeId && flatNodes.some(n => n.id === nodeId)) {
      selectedHierarchyId.value = hierarchyId
    } else if (!selectedHierarchyId.value) {
      selectedHierarchyId.value = hierarchyId
    }
  } catch (e) { console.error('Failed to load hierarchy tree:', e.message) }
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

// ─── Hierarchy node change → reload attributes ───────
watch(() => product.value?.master_hierarchy_node_id, async (newNodeId, oldNodeId) => {
  if (newNodeId === oldNodeId) return
  // Reset attribute state and reload with new hierarchy
  attrLoaded.value = false
  schema.value = null
  attributeValues.value = {}
  translatedValues.value = {}
  valueListMap.value = {}
  await loadAttributeData(newNodeId || null)
})

// ─── Tab lazy loading ─────────────────────────────────
watch(activeTab, (tab) => {
  if (tab === 'attributes') loadAttributeData()
  if (tab === 'variant-attributes') loadAttributeData()
  if (tab === 'variants') { loadVariants(); loadAttributeData() }
  if (tab === 'media') loadMedia()
  if (tab === 'prices') loadPrices()
  if (tab === 'relations') loadRelations()
  if (tab === 'output-hierarchies') loadOutputHierarchyAssignments()
  if (tab === 'preview') loadPreview()
})

onMounted(async () => {
  await store.fetchOne(route.params.id)
  loadAttributeData()
  loadHierarchies()
  // If variant, load parent's inheritance rules
  if (product.value?.product_type_ref === 'variant' && product.value?.parent_product_id) {
    loadInheritanceRules()
  }
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
  outputHierarchyLoaded.value = false

  // Clear stale data
  schema.value = null
  attributeValues.value = {}
  translatedValues.value = {}
  variants.value = []
  variantAttributeDefs.value = []
  variantAttrValuesMap.value = {}
  mediaItems.value = []
  prices.value = []
  relations.value = []
  previewData.value = null
  completenessData.value = null

  // Reset inheritance state
  inheritanceRulesLoaded.value = false
  editedInheritanceRules.value = {}
  showInheritanceRules.value = false

  // Close open forms
  showVariantForm.value = false
  showPriceForm.value = false
  showRelationForm.value = false
  showMediaPicker.value = false
  showCopyDialog.value = false
  showGenerator.value = false

  // Reset tab to attributes
  activeTab.value = 'attributes'

  // Reload product/variant data
  await store.fetchOne(newId)
  loadAttributeData()
  loadHierarchies()
  // If variant, load parent's inheritance rules
  if (product.value?.product_type_ref === 'variant' && product.value?.parent_product_id) {
    loadInheritanceRules()
  }
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
            <router-link
              v-if="product.parent_product_id"
              class="ml-2 text-xs text-[var(--color-accent)] hover:underline"
              :to="`/products/${product.parent_product_id}`"
            >
              ← Zum Elternprodukt
            </router-link>
          </p>
        </template>
      </div>
      <button
        v-if="authStore.hasPermission('products.create') && product && product.product_type_ref !== 'variant'"
        class="pim-btn pim-btn-secondary text-xs"
        @click="showCopyDialog = true"
      >
        <Copy class="w-4 h-4" :stroke-width="1.75" />
        <span class="hidden sm:inline">Kopieren</span>
      </button>
      <button v-if="authStore.hasPermission('products.edit')" class="pim-btn pim-btn-primary" :disabled="saving" @click="save">
        <Save class="w-4 h-4" :stroke-width="1.75" />
        {{ saving ? 'Speichern…' : t('common.save') }}
      </button>
    </div>

    <!-- Copy Dialog -->
    <div v-if="showCopyDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showCopyDialog = false">
      <div class="pim-card p-6 w-full max-w-sm space-y-4 shadow-xl">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Produkt kopieren</h3>
          <button class="pim-btn pim-btn-ghost p-1" @click="showCopyDialog = false"><X class="w-4 h-4" /></button>
        </div>
        <p class="text-xs text-[var(--color-text-secondary)]">Was soll in die Kopie übernommen werden?</p>
        <div class="space-y-2">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_attributes" class="pim-checkbox" />
            Attributwerte
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_prices" class="pim-checkbox" />
            Preise
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_media" class="pim-checkbox" />
            Medien-Zuordnungen
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="copyOptions.include_relations" class="pim-checkbox" />
            Relationen
          </label>
        </div>
        <div class="flex gap-3 text-xs text-[var(--color-accent)]">
          <button class="hover:underline" @click="selectAllCopyOptions(true)">Alle auswählen</button>
          <button class="hover:underline" @click="selectAllCopyOptions(false)">Keine auswählen</button>
        </div>
        <div class="flex gap-2 pt-2">
          <button class="pim-btn pim-btn-primary text-xs flex-1" :disabled="copying" @click="duplicateProduct">
            <Copy class="w-3.5 h-3.5" :stroke-width="2" />
            {{ copying ? 'Wird kopiert…' : 'Kopie erstellen' }}
          </button>
          <button class="pim-btn pim-btn-secondary text-xs" @click="showCopyDialog = false">Abbrechen</button>
        </div>
      </div>
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
              <select class="pim-input text-xs flex-1" :value="product.master_hierarchy_node_id || ''" @change="product.master_hierarchy_node_id = $event.target.value || null">
                <option value="">— Kein Knoten —</option>
                <option v-for="node in hierarchyNodes.filter(n => !selectedHierarchyId || n._hierarchyId === selectedHierarchyId)" :key="node.id" :value="node.id">{{ node.label }}</option>
              </select>
            </div>
            <p v-if="masterNodePath" class="text-[11px] text-[var(--color-text-tertiary)] mt-1 font-mono">{{ masterNodePath }}</p>
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

      <!-- Language switcher for translatable attributes -->
      <div v-if="localeStore.activeDataLocales.length > 1 && schemaAttributes.some(a => a.is_translatable)" class="flex items-center gap-2 px-1">
        <Languages class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
        <div class="flex gap-0 border border-[var(--color-border)] rounded-lg overflow-hidden">
          <button
            v-for="loc in localeStore.activeDataLocales"
            :key="loc"
            :class="[
              'px-3 py-1 text-[11px] font-medium transition-colors',
              activeDataLang === loc
                ? 'bg-[var(--color-accent)] text-white'
                : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]',
            ]"
            @click="activeDataLang = loc"
          >
            {{ loc.toUpperCase() }}
          </button>
        </div>
        <span class="text-[11px] text-[var(--color-text-tertiary)]">{{ t('product.dataLanguage') }}</span>
      </div>

      <!-- Dynamic attributes from product type schema -->
      <PimCollectionGroup
        v-for="group in attributeGroups"
        :key="group.name"
        :title="group.name"
        :filledCount="group.attributes.filter(a => a.is_translatable ? translatedValues[`${a.id}_${activeDataLang}`] : attributeValues[a.id]).length"
        :totalCount="group.attributes.length"
        :defaultOpen="false"
      >
        <div class="space-y-3 pt-3">
          <div v-for="attr in group.attributes" :key="attr.id">
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
              {{ attr.name_de || attr.technical_name }}
              <span v-if="attr.is_mandatory" class="text-[var(--color-error)]">*</span>
              <span v-if="attr.is_translatable" class="ml-1 text-[10px] text-[var(--color-accent)] font-normal">
                <Languages class="inline w-3 h-3 -mt-0.5" :stroke-width="1.75" /> {{ activeDataLang.toUpperCase() }}
              </span>
              <span v-if="attr._is_inherited" class="ml-1 text-[10px] text-blue-500 font-normal">(vererbt)</span>
              <span v-if="isAttributeInherited(attr.id)" class="ml-1 text-[10px] text-purple-500 font-normal">(vererbt vom Elternprodukt)</span>
            </label>
            <!-- Composite: Button with summary -->
            <button
              v-if="attr.data_type === 'Composite'"
              class="w-full flex items-center justify-between pim-input text-left cursor-pointer hover:border-[var(--color-accent)] transition-colors"
              :disabled="attr._access === 'read_only' || isAttributeInherited(attr.id)"
              @click="openCompositeModal(attr)"
            >
              <span class="text-[13px]" :class="getCompositeSummary(attr) ? 'text-[var(--color-text-primary)]' : 'text-[var(--color-text-tertiary)]'">
                {{ getCompositeSummary(attr) || 'Bearbeiten…' }}
              </span>
              <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0 ml-2">{{ (attr._children || []).length }} Felder</span>
            </button>
            <!-- Translatable attribute: bind to translatedValues -->
            <PimAttributeInput
              v-else-if="attr.is_translatable"
              :type="mapDataTypeToInput(attr.data_type)"
              :modelValue="translatedValues[`${attr.id}_${activeDataLang}`]"
              :options="attr.data_type === 'Dictionary' ? dictionaryEntries : (attr.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || [])"
              :disabled="attr._access === 'read_only' || isAttributeInherited(attr.id)"
              @update:modelValue="translatedValues[`${attr.id}_${activeDataLang}`] = $event"
            />
            <!-- Normal (non-translatable) attribute -->
            <PimAttributeInput
              v-else
              :type="mapDataTypeToInput(attr.data_type)"
              :modelValue="attributeValues[attr.id]"
              :options="attr.data_type === 'Dictionary' ? dictionaryEntries : (attr.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || [])"
              :disabled="attr._access === 'read_only' || isAttributeInherited(attr.id)"
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
                <span v-if="isAttributeInherited(attr.id)" class="ml-1 text-[10px] text-purple-500 font-normal">(vererbt vom Elternprodukt)</span>
              </label>
              <PimAttributeInput
                :type="mapDataTypeToInput(attr.data_type)"
                :modelValue="attributeValues[attr.id]"
                :options="attr.value_list?.entries?.map(e => ({ value: e.id, label: e.value_de || e.label_de || e.code })) || []"
                :disabled="attr._access === 'read_only' || isAttributeInherited(attr.id)"
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
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Varianten</h3>
        <div class="flex gap-2">
          <button class="pim-btn pim-btn-secondary text-xs" @click="initGenerator">
            <Sparkles class="w-3.5 h-3.5" :stroke-width="2" /> Varianten generieren
          </button>
          <button class="pim-btn pim-btn-primary text-xs" @click="showVariantForm = !showVariantForm">
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neue Variante
          </button>
        </div>
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

      <!-- Variant Generator Panel -->
      <div v-if="showGenerator" class="pim-card p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-semibold text-[var(--color-text-primary)] flex items-center gap-1.5">
            <Sparkles class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
            Variantengenerator
          </h4>
          <button class="pim-btn pim-btn-ghost p-1" @click="showGenerator = false"><X class="w-4 h-4" /></button>
        </div>

        <!-- Step 1: Select attributes + enter values -->
        <template v-if="generatorStep === 1">
          <p class="text-xs text-[var(--color-text-secondary)]">Wählen Sie Variantenattribute und geben Sie die gewünschten Werte ein.</p>
          <div v-if="generatorDimensions.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-xs text-yellow-800 space-y-1">
            <p class="font-medium">Keine Varianten-Attribute vorhanden</p>
            <p>Markieren Sie zuerst mindestens ein Attribut als Varianten-Attribut (unter <strong>Attribute</strong> → Attribut bearbeiten → <em>Varianten-Attribut</em> aktivieren).</p>
          </div>
          <div class="space-y-3">
            <div v-for="dim in generatorDimensions" :key="dim.attribute_id" class="border border-[var(--color-border)] rounded-lg p-3">
              <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input type="checkbox" v-model="dim.selected" class="pim-checkbox" />
                {{ dim.attribute.name_de || dim.attribute.technical_name }}
              </label>
              <div v-if="dim.selected" class="mt-2 space-y-2">
                <!-- Value list entries (Selection/Dictionary) -->
                <template v-if="dim.attribute.value_list?.entries?.length">
                  <div class="flex flex-wrap gap-1.5">
                    <label
                      v-for="entry in dim.attribute.value_list.entries"
                      :key="entry.id"
                      class="flex items-center gap-1 text-xs px-2 py-1 rounded border cursor-pointer transition-colors"
                      :class="dim.values.includes(entry.display_value_de || entry.value_de || entry.code)
                        ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/10 text-[var(--color-accent)]'
                        : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-accent)]'"
                      @click.prevent="toggleValueListEntry(dim, entry.display_value_de || entry.value_de || entry.code)"
                    >
                      <input
                        type="checkbox"
                        :checked="dim.values.includes(entry.display_value_de || entry.value_de || entry.code)"
                        class="pim-checkbox w-3 h-3"
                        @click.stop
                        @change="toggleValueListEntry(dim, entry.display_value_de || entry.value_de || entry.code)"
                      />
                      {{ entry.display_value_de || entry.value_de || entry.code }}
                    </label>
                  </div>
                </template>
                <!-- Free text input (String/Number) -->
                <template v-else>
                  <div class="flex gap-2">
                    <input
                      class="pim-input text-xs flex-1"
                      v-model="dim.textInput"
                      placeholder="Werte kommasepariert eingeben (z.B. 30, 31, 32)"
                      @keydown.enter.prevent="addDimensionValues(dim)"
                    />
                    <button class="pim-btn pim-btn-secondary text-xs" @click="addDimensionValues(dim)">
                      <Plus class="w-3 h-3" /> Hinzufügen
                    </button>
                  </div>
                </template>
                <!-- Show selected values as tags -->
                <div v-if="dim.values.length" class="flex flex-wrap gap-1">
                  <span
                    v-for="val in dim.values"
                    :key="val"
                    class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-[var(--color-accent)]/10 text-[var(--color-accent)]"
                  >
                    {{ val }}
                    <button class="hover:text-[var(--color-error)]" @click="removeDimensionValue(dim, val)"><X class="w-3 h-3" /></button>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2">
            <p class="text-xs text-[var(--color-text-tertiary)]">
              {{ generatorTotalCombinations }} Kombination{{ generatorTotalCombinations !== 1 ? 'en' : '' }}
            </p>
            <div class="flex gap-2">
              <button class="pim-btn pim-btn-secondary text-xs" @click="showGenerator = false">Abbrechen</button>
              <button
                class="pim-btn pim-btn-primary text-xs"
                :disabled="generatorTotalCombinations === 0"
                @click="generatorStep = 2"
              >
                Weiter zur Vorschau
              </button>
            </div>
          </div>
        </template>

        <!-- Step 2: Preview -->
        <template v-if="generatorStep === 2">
          <div class="space-y-3">
            <div class="flex flex-wrap items-end gap-3">
              <div>
                <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">SKU-Prefix</label>
                <input class="pim-input text-xs w-48" v-model="generatorSKUPrefix" />
              </div>
              <p class="text-xs text-[var(--color-text-tertiary)] pb-1.5">
                {{ generatorPreviewFiltered.length }} von {{ generatorPreview.length }} Varianten ausgewählt
              </p>
            </div>

            <div class="max-h-80 overflow-auto border border-[var(--color-border)] rounded-lg">
              <table class="w-full text-xs">
                <thead class="bg-[var(--color-bg)] sticky top-0">
                  <tr>
                    <th class="px-3 py-2 text-left font-medium text-[var(--color-text-secondary)]"></th>
                    <th class="px-3 py-2 text-left font-medium text-[var(--color-text-secondary)]">SKU</th>
                    <th
                      v-for="dim in generatorDimensions.filter(d => d.selected && d.values.length > 0)"
                      :key="dim.attribute_id"
                      class="px-3 py-2 text-left font-medium text-[var(--color-text-secondary)]"
                    >
                      {{ dim.attribute.name_de || dim.attribute.technical_name }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in generatorPreview"
                    :key="row.idx"
                    class="border-t border-[var(--color-border)]"
                    :class="generatorExcluded.has(row.idx) ? 'opacity-40' : ''"
                  >
                    <td class="px-3 py-1.5">
                      <input
                        type="checkbox"
                        class="pim-checkbox"
                        :checked="!generatorExcluded.has(row.idx)"
                        @change="toggleGeneratorRow(row.idx)"
                      />
                    </td>
                    <td class="px-3 py-1.5 font-mono text-[var(--color-text-tertiary)]">{{ row.sku }}</td>
                    <td
                      v-for="c in row.combo"
                      :key="c.attribute_id"
                      class="px-3 py-1.5"
                    >
                      {{ c.value }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex gap-2 justify-end pt-1">
              <button class="pim-btn pim-btn-secondary text-xs" @click="generatorStep = 1">Zurück</button>
              <button
                class="pim-btn pim-btn-primary text-xs"
                :disabled="generatorLoading || generatorPreviewFiltered.length === 0"
                @click="runGenerator"
              >
                <Sparkles class="w-3.5 h-3.5" />
                {{ generatorLoading ? 'Wird erstellt…' : `${generatorPreviewFiltered.length} Varianten erstellen` }}
              </button>
            </div>
          </div>
        </template>

        <!-- Step 3: Result -->
        <template v-if="generatorStep === 3 && generatorResult">
          <div class="text-center space-y-2 py-4">
            <div class="text-3xl">✓</div>
            <p class="text-sm font-medium text-[var(--color-text-primary)]">
              {{ generatorResult.created }} Variante{{ generatorResult.created !== 1 ? 'n' : '' }} erstellt
            </p>
            <p v-if="generatorResult.skipped > 0" class="text-xs text-[var(--color-text-tertiary)]">
              {{ generatorResult.skipped }} übersprungen (SKU bereits vorhanden)
            </p>
            <button class="pim-btn pim-btn-secondary text-xs mt-3" @click="showGenerator = false">Schließen</button>
          </div>
        </template>
      </div>

      <PimTable
        :columns="variantColumns"
        :rows="variantRows"
        :loading="variantsLoading"
        emptyText="Keine Varianten vorhanden"
        @row-click="(row) => router.push(`/products/${row.id}`)"
        @row-action="(row) => variantDeleteTarget = row"
      >
        <template #cell-status="{ value }">
          <span :class="['pim-badge', value === 'active' ? 'bg-[var(--color-success-light)] text-[var(--color-success)]' : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]']">
            {{ value === 'active' ? 'Aktiv' : 'Entwurf' }}
          </span>
        </template>
      </PimTable>

      <PimConfirmDialog
        :open="!!variantDeleteTarget"
        title="Variante löschen?"
        message="Diese Variante wird unwiderruflich gelöscht."
        :loading="variantDeleting"
        @confirm="confirmDeleteVariant"
        @cancel="variantDeleteTarget = null"
      />

      <!-- Variant Inheritance Rules (only on parent products) -->
      <div v-if="product.product_type_ref !== 'variant'" class="pt-2">
        <button
          class="text-xs text-[var(--color-accent)] hover:underline"
          @click="showInheritanceRules = !showInheritanceRules; if (!inheritanceRulesLoaded) { loadAttributeData(); loadInheritanceRules() }"
        >
          {{ showInheritanceRules ? 'Vererbungsregeln ausblenden' : 'Vererbungsregeln verwalten' }}
        </button>

        <div v-if="showInheritanceRules" class="pim-card p-4 mt-2 space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-[var(--color-text-primary)]">Vererbungsregeln</h4>
            <button
              class="pim-btn pim-btn-primary text-xs"
              :disabled="inheritanceRulesSaving"
              @click="saveInheritanceRules"
            >
              {{ inheritanceRulesSaving ? 'Speichern…' : 'Regeln speichern' }}
            </button>
          </div>
          <p class="text-[11px] text-[var(--color-text-tertiary)]">
            Legen Sie fest, welche Attribute Varianten vom Elternprodukt erben (nicht editierbar) oder selbst überschreiben können.
          </p>

          <div v-if="inheritanceRulesLoading" class="space-y-2">
            <div v-for="i in 4" :key="i" class="pim-skeleton h-8 w-full rounded" />
          </div>
          <div v-else-if="schemaAttributes.length > 0" class="divide-y divide-[var(--color-border)]">
            <div
              v-for="attr in schemaAttributes"
              :key="attr.id"
              class="flex items-center justify-between py-2"
            >
              <span class="text-xs text-[var(--color-text-primary)]">
                {{ attr.name_de || attr.technical_name }}
                <span v-if="attr.is_variant_attribute" class="ml-1 text-[10px] text-purple-500">(Varianten-Attribut)</span>
              </span>
              <button
                :class="[
                  'text-[11px] px-2.5 py-1 rounded-full font-medium transition-colors',
                  (editedInheritanceRules[attr.id] || 'override') === 'inherit'
                    ? 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                    : 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-border)]',
                ]"
                @click="toggleInheritance(attr.id)"
              >
                {{ (editedInheritanceRules[attr.id] || 'override') === 'inherit' ? 'Vererben' : 'Überschreiben' }}
              </button>
            </div>
          </div>
          <div v-else class="text-xs text-[var(--color-text-tertiary)]">
            Keine Attribute im Produkttyp-Schema gefunden.
          </div>
        </div>
      </div>
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
          <div class="p-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] text-[var(--color-text-primary)] truncate flex-1">{{ m.file_name || m.media?.file_name || '—' }}</span>
              <button class="p-0.5 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors" @click="detachMedia(m)">
                <X class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
            <span v-if="m.usage_type" class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.usage_type?.name_de || m.usage_type?.technical_name || '' }}</span>
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
                <!-- Usage type selector -->
                <div v-if="usageTypesList.length > 0" class="mb-3">
                  <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Bildtyp</label>
                  <select class="pim-input" v-model="selectedUsageTypeId">
                    <option v-for="ut in usageTypesList" :key="ut.id" :value="ut.id">{{ ut.name_de || ut.technical_name }}</option>
                  </select>
                </div>
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

    <!-- ═══ Output Hierarchies Tab ═══ -->
    <div v-else-if="activeTab === 'output-hierarchies' && product" class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Ausgabehierarchie-Zuordnungen</h3>
        <button class="pim-btn pim-btn-primary text-xs" @click="showOutputHierarchyForm = !showOutputHierarchyForm">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Zuordnung hinzufugen
        </button>
      </div>

      <!-- Add form -->
      <div v-if="showOutputHierarchyForm" class="pim-card p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Ausgabehierarchie</label>
            <select class="pim-input text-xs w-full" :value="selectedOutputHierarchyId || ''" @change="onOutputHierarchyChange($event.target.value)">
              <option value="">— Hierarchie wählen —</option>
              <option v-for="h in outputHierarchies" :key="h.id" :value="h.id">{{ h.name_de || h.technical_name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Knoten</label>
            <select class="pim-input text-xs w-full" v-model="selectedOutputNodeId" :disabled="!selectedOutputHierarchyId">
              <option :value="null">— Knoten wählen —</option>
              <option v-for="node in outputHierarchyNodeOptions" :key="node.id" :value="node.id">{{ node.label }}</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-primary text-xs" :disabled="!selectedOutputNodeId" @click="assignOutputHierarchyNode">
            Zuordnen
          </button>
          <button class="pim-btn pim-btn-ghost text-xs" @click="showOutputHierarchyForm = false">Abbrechen</button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="outputHierarchyLoading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="pim-skeleton h-10 rounded" />
      </div>

      <!-- Assignment list -->
      <div v-else-if="outputHierarchyAssignments.length > 0" class="pim-card overflow-hidden">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-[var(--color-bg)] text-[var(--color-text-secondary)] text-[10px] uppercase tracking-wider">
              <th class="px-3 py-2 text-left">Hierarchie</th>
              <th class="px-3 py-2 text-left">Knoten</th>
              <th class="px-3 py-2 text-right w-12">#</th>
              <th class="px-3 py-2 text-right w-16">Aktion</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="assignment in outputHierarchyAssignments"
              :key="assignment.id"
              class="border-t border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
            >
              <td class="px-3 py-2 text-[var(--color-text-secondary)]">{{ assignment.hierarchy_node?.hierarchy?.name_de || '—' }}</td>
              <td class="px-3 py-2 font-medium text-[var(--color-text-primary)]">{{ assignment.hierarchy_node?.name_de || '—' }}</td>
              <td class="px-3 py-2 text-right font-mono text-[var(--color-text-tertiary)]">{{ assignment.sort_order ?? 0 }}</td>
              <td class="px-3 py-2 text-right">
                <button class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]" @click="outputHierarchyDeleteTarget = assignment" title="Entfernen">
                  <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-else class="text-xs text-[var(--color-text-tertiary)] py-8 text-center">Keine Ausgabehierarchie-Zuordnungen. Klicken Sie auf "Zuordnung hinzufügen" um eine Hierarchie zuzuweisen.</p>

      <!-- Delete confirm -->
      <PimConfirmDialog
        :open="!!outputHierarchyDeleteTarget"
        title="Zuordnung entfernen?"
        message="Die Zuordnung dieses Produkts zum Ausgabehierarchie-Knoten wird entfernt."
        :loading="outputHierarchyDeleting"
        @confirm="confirmDeleteOutputHierarchyAssignment"
        @cancel="outputHierarchyDeleteTarget = null"
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
            <template v-for="attr in section.attributes" :key="attr.attribute_id + (attr.language || '')">
              <!-- Skip child attributes that belong to a composite (shown grouped below their parent) -->
              <template v-if="!attr.parent_attribute_id">
                <!-- Composite attribute: show label + formatted summary + children -->
                <div v-if="attr.data_type === 'Composite'" class="py-1.5 border-b border-[var(--color-border)] last:border-0">
                  <div class="flex items-center justify-between">
                    <span class="text-[12px] font-medium text-[var(--color-text-secondary)]">
                      {{ attr.label }}
                      <span class="pim-badge bg-[var(--color-bg)] text-[var(--color-text-tertiary)] text-[9px] ml-1">Composite</span>
                    </span>
                    <span class="text-[12px] text-[var(--color-text-primary)]">
                      {{ getPreviewCompositeSummary(attr, section.attributes) || '—' }}
                    </span>
                  </div>
                  <!-- Child attributes indented -->
                  <div class="ml-4 mt-1 space-y-0">
                    <div
                      v-for="child in section.attributes.filter(a => a.parent_attribute_id === attr.attribute_id)"
                      :key="child.attribute_id"
                      class="flex items-center justify-between py-1 text-[11px] text-[var(--color-text-tertiary)]"
                    >
                      <span>{{ child.label }}</span>
                      <span class="text-[var(--color-text-secondary)]">
                        {{ child.display_value || '—' }}
                        <span v-if="child.unit">{{ child.unit }}</span>
                      </span>
                    </div>
                  </div>
                </div>
                <!-- Normal attribute -->
                <div v-else class="flex items-center justify-between py-1.5 border-b border-[var(--color-border)] last:border-0">
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
              </template>
            </template>
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
                  <span v-if="m.usage_type" class="text-[10px] text-[var(--color-text-tertiary)]">{{ m.usage_type?.name_de || m.usage_type?.technical_name || '' }}</span>
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
