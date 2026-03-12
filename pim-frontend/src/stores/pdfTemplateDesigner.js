import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import pdfTemplatesApi from '@/api/pdfTemplates'

export const usePdfTemplateDesignerStore = defineStore('pdfTemplateDesigner', () => {
  // Template list
  const templates = ref([])
  const loading = ref(false)

  // Current template being edited
  const currentTemplate = ref(null)
  const templateJson = ref(createEmptyTemplate())

  // Available fields for palette
  const availableFields = ref(null)
  const fieldsLoading = ref(false)

  // Selection state
  const selectedElementId = ref(null)

  // Grid & snap
  const gridSize = ref(5) // mm
  const showGrid = ref(true)
  const snapToGrid = ref(true)

  // Dirty tracking
  const isDirty = ref(false)

  // Reference product & preview mode
  const referenceProductId = ref(null)
  const referenceProductLabel = ref('')
  const previewMode = ref(false)
  const resolvedElements = ref([])
  const previewLoading = ref(false)

  // --- Template List ---
  async function loadTemplates() {
    loading.value = true
    try {
      const { data } = await pdfTemplatesApi.list()
      templates.value = data.data || data
    } finally {
      loading.value = false
    }
  }

  // --- Single Template ---
  async function loadTemplate(id) {
    loading.value = true
    try {
      const { data } = await pdfTemplatesApi.get(id)
      const tmpl = data.data || data
      currentTemplate.value = tmpl
      templateJson.value = tmpl.template_json || createEmptyTemplate()
      isDirty.value = false
    } finally {
      loading.value = false
    }
  }

  async function saveTemplate() {
    if (!currentTemplate.value) return

    const payload = {
      name: currentTemplate.value.name,
      description: currentTemplate.value.description,
      template_json: templateJson.value,
      page_orientation: currentTemplate.value.page_orientation || 'portrait',
      page_size: currentTemplate.value.page_size || 'A4',
      is_shared: currentTemplate.value.is_shared || false,
    }

    const { data } = await pdfTemplatesApi.update(currentTemplate.value.id, payload)
    currentTemplate.value = data.data || data
    isDirty.value = false
  }

  async function createTemplate(name, defaultFontFamily = 'DejaVu Sans') {
    const payload = {
      name,
      template_json: createEmptyTemplate(defaultFontFamily),
    }
    const { data } = await pdfTemplatesApi.create(payload)
    const tmpl = data.data || data
    templates.value.push(tmpl)
    return tmpl
  }

  async function deleteTemplate(id) {
    await pdfTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
    if (currentTemplate.value?.id === id) {
      currentTemplate.value = null
      templateJson.value = createEmptyTemplate()
    }
  }

  // --- Fields ---
  async function loadFields() {
    if (availableFields.value) return
    fieldsLoading.value = true
    try {
      const { data } = await pdfTemplatesApi.fields()
      availableFields.value = data.data || data
    } finally {
      fieldsLoading.value = false
    }
  }

  // --- Element Management ---
  function addElement(element) {
    const el = { ...element, id: generateId() }
    templateJson.value.elements.push(el)
    isDirty.value = true
    selectedElementId.value = el.id
    return el
  }

  function removeElement(elementId) {
    templateJson.value.elements = templateJson.value.elements.filter(e => e.id !== elementId)
    if (selectedElementId.value === elementId) selectedElementId.value = null
    isDirty.value = true
  }

  function updateElement(elementId, updates) {
    const el = templateJson.value.elements.find(e => e.id === elementId)
    if (el) {
      Object.assign(el, updates)
      isDirty.value = true
    }
  }

  function duplicateElement(elementId) {
    const el = templateJson.value.elements.find(e => e.id === elementId)
    if (!el) return
    const newEl = { ...JSON.parse(JSON.stringify(el)), id: generateId(), x: (el.x || 0) + 5, y: (el.y || 0) + 5 }
    templateJson.value.elements.push(newEl)
    selectedElementId.value = newEl.id
    isDirty.value = true
    return newEl
  }

  function selectElement(elementId) {
    selectedElementId.value = elementId
  }

  function clearSelection() {
    selectedElementId.value = null
  }

  function bringToFront(elementId) {
    const idx = templateJson.value.elements.findIndex(e => e.id === elementId)
    if (idx < 0) return
    const [el] = templateJson.value.elements.splice(idx, 1)
    templateJson.value.elements.push(el)
    isDirty.value = true
  }

  function sendToBack(elementId) {
    const idx = templateJson.value.elements.findIndex(e => e.id === elementId)
    if (idx < 0) return
    const [el] = templateJson.value.elements.splice(idx, 1)
    templateJson.value.elements.unshift(el)
    isDirty.value = true
  }

  // --- Selected element (computed) ---
  const selectedElement = computed(() => {
    if (!selectedElementId.value) return null
    return templateJson.value.elements.find(e => e.id === selectedElementId.value) || null
  })

  // --- Snap helper ---
  function snapValue(value) {
    if (!snapToGrid.value) return value
    return Math.round(value / gridSize.value) * gridSize.value
  }

  // --- Default font helper ---
  function getDefaultFontFamily() {
    return templateJson.value.style?.defaultFontFamily || 'DejaVu Sans'
  }

  // --- Reference product & preview ---
  async function setReferenceProduct(productId, label = '') {
    referenceProductId.value = productId
    referenceProductLabel.value = label
    if (productId && previewMode.value) {
      await loadPreview()
    }
  }

  async function loadPreview() {
    if (!referenceProductId.value || !currentTemplate.value) return
    previewLoading.value = true
    try {
      const { data } = await pdfTemplatesApi.resolvePreview(
        currentTemplate.value.id,
        referenceProductId.value,
      )
      resolvedElements.value = data.data || data
    } catch (e) {
      resolvedElements.value = []
    } finally {
      previewLoading.value = false
    }
  }

  async function togglePreviewMode() {
    previewMode.value = !previewMode.value
    if (previewMode.value && referenceProductId.value) {
      // Save first if dirty so preview uses latest template
      if (isDirty.value) await saveTemplate()
      await loadPreview()
    }
  }

  function getResolvedElement(elementId) {
    return resolvedElements.value.find(e => e.id === elementId) || null
  }

  return {
    templates, loading, currentTemplate, templateJson,
    availableFields, fieldsLoading,
    selectedElementId, selectedElement,
    gridSize, showGrid, snapToGrid,
    isDirty,
    referenceProductId, referenceProductLabel, previewMode, resolvedElements, previewLoading,
    loadTemplates, loadTemplate, saveTemplate, createTemplate, deleteTemplate,
    loadFields,
    addElement, removeElement, updateElement, duplicateElement,
    selectElement, clearSelection,
    bringToFront, sendToBack,
    snapValue, getDefaultFontFamily,
    setReferenceProduct, loadPreview, togglePreviewMode, getResolvedElement,
  }
})

// --- Helpers ---

export function createEmptyTemplate(defaultFontFamily = 'DejaVu Sans') {
  return {
    version: 1,
    pageWidth: 210,   // mm (A4)
    pageHeight: 297,  // mm (A4)
    elements: [],
    style: {
      backgroundColor: '#ffffff',
      defaultFontFamily,
    },
  }
}

function generateId() {
  return 'el_' + Math.random().toString(36).slice(2, 10)
}
