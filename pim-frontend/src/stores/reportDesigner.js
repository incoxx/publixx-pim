import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import reportTemplatesApi from '@/api/reportTemplates'

export const useReportDesignerStore = defineStore('reportDesigner', () => {
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
  const selectedGroupId = ref(null)

  // Focus state for double-click add
  const focusedSection = ref({ groupId: null, section: null })

  // Dirty tracking
  const isDirty = ref(false)

  // --- Template List ---
  async function loadTemplates() {
    loading.value = true
    try {
      const { data } = await reportTemplatesApi.list()
      templates.value = data.data || data
    } finally {
      loading.value = false
    }
  }

  // --- Single Template ---
  async function loadTemplate(id) {
    loading.value = true
    try {
      const { data } = await reportTemplatesApi.get(id)
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
      search_profile_id: currentTemplate.value.search_profile_id,
      template_json: templateJson.value,
      format: currentTemplate.value.format || 'pdf',
      page_orientation: currentTemplate.value.page_orientation || 'portrait',
      page_size: currentTemplate.value.page_size || 'A4',
      language: currentTemplate.value.language || 'de',
      is_shared: currentTemplate.value.is_shared || false,
    }

    const { data } = await reportTemplatesApi.update(currentTemplate.value.id, payload)
    currentTemplate.value = data.data || data
    isDirty.value = false
  }

  async function createTemplate(name) {
    const payload = {
      name,
      template_json: createEmptyTemplate(),
      format: 'pdf',
    }
    const { data } = await reportTemplatesApi.create(payload)
    const tmpl = data.data || data
    templates.value.push(tmpl)
    return tmpl
  }

  async function deleteTemplate(id) {
    await reportTemplatesApi.remove(id)
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
      const { data } = await reportTemplatesApi.fields()
      availableFields.value = data.data || data
    } finally {
      fieldsLoading.value = false
    }
  }

  // --- Group Management ---
  function addGroup(parentGroupId = null) {
    const newGroup = {
      id: generateId(),
      field: 'product_type',
      label: 'Neue Gruppe',
      sortOrder: 'asc',
      pageBreak: false,
      detailLayout: 'table',
      tableStyle: createDefaultTableStyle(),
      header: { elements: [] },
      detail: { elements: [] },
      footer: { elements: [] },
      groups: [],
    }

    if (parentGroupId) {
      const parent = findGroup(templateJson.value.groups, parentGroupId)
      if (parent && getGroupDepth(templateJson.value.groups, parentGroupId) < 3) {
        parent.groups.push(newGroup)
      }
    } else {
      templateJson.value.groups.push(newGroup)
    }
    isDirty.value = true
  }

  function removeGroup(groupId) {
    removeFromGroups(templateJson.value.groups, groupId)
    if (selectedGroupId.value === groupId) selectedGroupId.value = null
    isDirty.value = true
  }

  function updateGroup(groupId, updates) {
    const group = findGroup(templateJson.value.groups, groupId)
    if (group) {
      Object.assign(group, updates)
      isDirty.value = true
    }
  }

  // --- Element Management ---
  function addElement(groupId, section, element) {
    const group = findGroup(templateJson.value.groups, groupId)
    if (!group) return

    const el = { ...element, id: generateId() }
    group[section].elements.push(el)
    isDirty.value = true
    return el
  }

  function removeElement(groupId, section, elementId) {
    const group = findGroup(templateJson.value.groups, groupId)
    if (!group) return

    group[section].elements = group[section].elements.filter(e => e.id !== elementId)
    if (selectedElementId.value === elementId) selectedElementId.value = null
    isDirty.value = true
  }

  function updateElement(groupId, section, elementId, updates) {
    const group = findGroup(templateJson.value.groups, groupId)
    if (!group) return

    const el = group[section].elements.find(e => e.id === elementId)
    if (el) {
      Object.assign(el, updates)
      isDirty.value = true
    }
  }

  function moveElement(groupId, section, fromIndex, toIndex) {
    const group = findGroup(templateJson.value.groups, groupId)
    if (!group) return

    const elements = group[section].elements
    if (fromIndex < 0 || fromIndex >= elements.length || toIndex < 0 || toIndex >= elements.length) return

    const [moved] = elements.splice(fromIndex, 1)
    elements.splice(toIndex, 0, moved)
    isDirty.value = true
  }

  // --- Selection ---
  function selectElement(elementId, groupId, section) {
    selectedElementId.value = elementId
    selectedGroupId.value = groupId
  }

  function selectGroup(groupId) {
    selectedGroupId.value = groupId
    selectedElementId.value = null
  }

  function clearSelection() {
    selectedElementId.value = null
    selectedGroupId.value = null
  }

  function setFocusedSection(groupId, section) {
    focusedSection.value = { groupId, section }
  }

  // --- Page Header/Footer ---
  function updatePageHeader(elements) {
    templateJson.value.pageHeader = { elements }
    isDirty.value = true
  }

  function updatePageFooter(elements) {
    templateJson.value.pageFooter = { elements }
    isDirty.value = true
  }

  // --- Selected element (computed) ---
  const selectedElement = computed(() => {
    if (!selectedElementId.value || !selectedGroupId.value) return null
    const group = findGroup(templateJson.value.groups, selectedGroupId.value)
    if (!group) return null
    for (const section of ['header', 'detail', 'footer']) {
      const el = group[section].elements.find(e => e.id === selectedElementId.value)
      if (el) return { element: el, section, groupId: selectedGroupId.value }
    }
    return null
  })

  const selectedGroup = computed(() => {
    if (!selectedGroupId.value) return null
    return findGroup(templateJson.value.groups, selectedGroupId.value)
  })

  return {
    templates, loading, currentTemplate, templateJson,
    availableFields, fieldsLoading,
    selectedElementId, selectedGroupId, selectedElement, selectedGroup,
    focusedSection,
    isDirty,
    loadTemplates, loadTemplate, saveTemplate, createTemplate, deleteTemplate,
    loadFields,
    addGroup, removeGroup, updateGroup,
    addElement, removeElement, updateElement, moveElement,
    selectElement, selectGroup, clearSelection,
    setFocusedSection,
    updatePageHeader, updatePageFooter,
  }
})

// --- Helpers ---

export function createEmptyTemplate() {
  return {
    version: 1,
    title: '',
    pageHeader: { elements: [] },
    pageFooter: { elements: [] },
    groups: [],
    style: { font: 'Arial', size: 11, primaryColor: '#2563eb' },
  }
}

export function createDefaultTableStyle() {
  return {
    showBorders: true,
    borderColor: '#e5e7eb',
    alternateRowBg: true,
    alternateRowColor: '#f9fafb',
    columnWidths: {},
    headerBg: '#f3f4f6',
    headerColor: '#374151',
    compact: false,
  }
}

function generateId() {
  return 'el_' + Math.random().toString(36).slice(2, 10)
}

function findGroup(groups, id) {
  for (const g of groups) {
    if (g.id === id) return g
    if (g.groups?.length) {
      const found = findGroup(g.groups, id)
      if (found) return found
    }
  }
  return null
}

function removeFromGroups(groups, id) {
  const idx = groups.findIndex(g => g.id === id)
  if (idx >= 0) {
    groups.splice(idx, 1)
    return true
  }
  for (const g of groups) {
    if (g.groups?.length && removeFromGroups(g.groups, id)) return true
  }
  return false
}

function getGroupDepth(groups, id, depth = 1) {
  for (const g of groups) {
    if (g.id === id) return depth
    if (g.groups?.length) {
      const d = getGroupDepth(g.groups, id, depth + 1)
      if (d) return d
    }
  }
  return 0
}
