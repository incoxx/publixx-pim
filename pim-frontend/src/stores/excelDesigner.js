import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import excelTemplatesApi from '@/api/excelTemplates'

export const useExcelDesignerStore = defineStore('excelDesigner', () => {
  // Template-Liste
  const templates = ref([])
  const loading = ref(false)

  // Aktuelles Template
  const currentTemplate = ref(null)
  const templateJson = ref(createEmptyTemplate())
  const excelSettings = ref(defaultExcelSettings())

  // Verfügbare Felder
  const availableFields = ref(null)
  const fieldsLoading = ref(false)

  // Auswahl
  const selectedSheetId = ref(null)
  const selectedColumnId = ref(null)

  // Dirty-Tracking
  const isDirty = ref(false)

  // Vorschau
  const previewData = ref(null)
  const previewLoading = ref(false)

  // Async-Export
  const exportProgress = ref(null)
  const exportKey = ref(null)
  const exportPolling = ref(false)

  // --- Template-Liste ---

  async function loadTemplates() {
    loading.value = true
    try {
      const { data } = await excelTemplatesApi.list()
      templates.value = data.data || data
    } finally {
      loading.value = false
    }
  }

  async function loadTemplate(id) {
    loading.value = true
    try {
      const { data } = await excelTemplatesApi.get(id)
      const tmpl = data.data || data
      currentTemplate.value = tmpl
      templateJson.value = tmpl.template_json || createEmptyTemplate()
      excelSettings.value = tmpl.excel_settings || defaultExcelSettings()
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
      search_profile_id: currentTemplate.value.search_profile_id || null,
      template_json: templateJson.value,
      excel_settings: excelSettings.value,
      language: currentTemplate.value.language || 'de',
      is_shared: currentTemplate.value.is_shared || false,
    }

    const { data } = await excelTemplatesApi.update(currentTemplate.value.id, payload)
    const tmpl = data.data || data
    currentTemplate.value = tmpl
    templateJson.value = tmpl.template_json || templateJson.value
    excelSettings.value = tmpl.excel_settings || excelSettings.value
    isDirty.value = false
  }

  async function saveTemplateAs(name) {
    if (!currentTemplate.value) return null

    const payload = {
      name,
      description: currentTemplate.value.description,
      search_profile_id: currentTemplate.value.search_profile_id || null,
      template_json: templateJson.value,
      excel_settings: excelSettings.value,
      language: currentTemplate.value.language || 'de',
      is_shared: currentTemplate.value.is_shared || false,
    }

    const { data } = await excelTemplatesApi.create(payload)
    const tmpl = data.data || data
    templates.value.push(tmpl)
    currentTemplate.value = tmpl
    templateJson.value = tmpl.template_json || templateJson.value
    excelSettings.value = tmpl.excel_settings || excelSettings.value
    isDirty.value = false
    return tmpl
  }

  async function createTemplate(name) {
    const payload = {
      name,
      template_json: createEmptyTemplate(),
    }
    const { data } = await excelTemplatesApi.create(payload)
    const tmpl = data.data || data
    templates.value.push(tmpl)
    return tmpl
  }

  async function deleteTemplate(id) {
    await excelTemplatesApi.remove(id)
    templates.value = templates.value.filter(t => t.id !== id)
    if (currentTemplate.value?.id === id) {
      currentTemplate.value = null
      templateJson.value = createEmptyTemplate()
    }
  }

  // --- Felder ---

  async function loadFields() {
    if (availableFields.value) return
    fieldsLoading.value = true
    try {
      const { data } = await excelTemplatesApi.fields()
      availableFields.value = data.data || data
    } finally {
      fieldsLoading.value = false
    }
  }

  // --- Sheet-Verwaltung ---

  function addSheet() {
    const newSheet = {
      id: generateId('sh'),
      label: 'Neues Blatt',
      field: 'none',
      sortOrder: 'asc',
      columns: [],
      headerRows: [],
      footerRows: [],
      sheets: [],
    }
    templateJson.value.sheets.push(newSheet)
    selectedSheetId.value = newSheet.id
    isDirty.value = true
  }

  function removeSheet(sheetId) {
    const sheets = templateJson.value.sheets
    const idx = sheets.findIndex(s => s.id === sheetId)
    if (idx >= 0) {
      sheets.splice(idx, 1)
      if (selectedSheetId.value === sheetId) {
        selectedSheetId.value = sheets.length > 0 ? sheets[0].id : null
      }
      isDirty.value = true
    }
  }

  function updateSheet(sheetId, updates) {
    const sheet = findSheet(sheetId)
    if (sheet) {
      Object.assign(sheet, updates)
      isDirty.value = true
    }
  }

  function duplicateSheet(sheetId) {
    const sheet = findSheet(sheetId)
    if (!sheet) return

    const copy = JSON.parse(JSON.stringify(sheet))
    copy.id = generateId('sh')
    copy.label = sheet.label + ' (Kopie)'
    copy.columns = copy.columns.map(c => ({ ...c, id: generateId('col') }))
    templateJson.value.sheets.push(copy)
    selectedSheetId.value = copy.id
    isDirty.value = true
  }

  // --- Spalten-Verwaltung ---

  function addColumn(sheetId, column) {
    const sheet = findSheet(sheetId)
    if (!sheet) return null

    const col = { ...column, id: generateId('col') }
    sheet.columns.push(col)
    isDirty.value = true
    return col
  }

  function removeColumn(sheetId, columnId) {
    const sheet = findSheet(sheetId)
    if (!sheet) return

    sheet.columns = sheet.columns.filter(c => c.id !== columnId)
    if (selectedColumnId.value === columnId) selectedColumnId.value = null
    isDirty.value = true
  }

  function updateColumn(sheetId, columnId, updates) {
    const sheet = findSheet(sheetId)
    if (!sheet) return

    const col = sheet.columns.find(c => c.id === columnId)
    if (col) {
      Object.assign(col, updates)
      isDirty.value = true
    }
  }

  function moveColumn(sheetId, fromIndex, toIndex) {
    const sheet = findSheet(sheetId)
    if (!sheet) return

    const columns = sheet.columns
    if (fromIndex < 0 || fromIndex >= columns.length || toIndex < 0 || toIndex >= columns.length) return

    const [moved] = columns.splice(fromIndex, 1)
    columns.splice(toIndex, 0, moved)
    isDirty.value = true
  }

  // --- Header/Footer-Zeilen ---

  function addHeaderRow(sheetId, content = '') {
    const sheet = findSheet(sheetId)
    if (!sheet) return
    sheet.headerRows.push({ id: generateId('hr'), content })
    isDirty.value = true
  }

  function removeHeaderRow(sheetId, rowId) {
    const sheet = findSheet(sheetId)
    if (!sheet) return
    sheet.headerRows = sheet.headerRows.filter(r => r.id !== rowId)
    isDirty.value = true
  }

  function addFooterRow(sheetId, content = '') {
    const sheet = findSheet(sheetId)
    if (!sheet) return
    sheet.footerRows.push({ id: generateId('fr'), content })
    isDirty.value = true
  }

  function removeFooterRow(sheetId, rowId) {
    const sheet = findSheet(sheetId)
    if (!sheet) return
    sheet.footerRows = sheet.footerRows.filter(r => r.id !== rowId)
    isDirty.value = true
  }

  // --- Auswahl ---

  function selectSheet(sheetId) {
    selectedSheetId.value = sheetId
    selectedColumnId.value = null
  }

  function selectColumn(columnId) {
    selectedColumnId.value = columnId
  }

  function clearSelection() {
    selectedColumnId.value = null
  }

  // --- Vorschau & Download ---

  async function loadPreview() {
    if (!currentTemplate.value) return
    previewLoading.value = true
    try {
      if (isDirty.value) await saveTemplate()
      const params = { limit: 5 }
      if (currentTemplate.value.search_profile_id) {
        params.search_profile_id = currentTemplate.value.search_profile_id
      }
      const { data } = await excelTemplatesApi.preview(currentTemplate.value.id, params)
      previewData.value = data.data || data
    } finally {
      previewLoading.value = false
    }
  }

  async function downloadExcel() {
    if (!currentTemplate.value) return
    if (isDirty.value) await saveTemplate()

    const params = {}
    if (currentTemplate.value.search_profile_id) {
      params.search_profile_id = currentTemplate.value.search_profile_id
    }

    // Async-Export starten
    const { data } = await excelTemplatesApi.startExport(currentTemplate.value.id, params)
    const key = (data.data || data).export_key
    exportKey.value = key
    exportProgress.value = { status: 'queued', processed: 0, total: 0, percent: 0 }
    exportPolling.value = true

    // Polling starten
    pollExportProgress()
  }

  let pollTimeout = null

  function pollExportProgress() {
    if (!exportKey.value || !exportPolling.value) return

    pollTimeout = setTimeout(async () => {
      pollTimeout = null
      if (!exportKey.value || !exportPolling.value) return

      try {
        const { data } = await excelTemplatesApi.exportProgress(exportKey.value)
        exportProgress.value = data.data || data

        const status = exportProgress.value.status
        if (status === 'completed') {
          exportPolling.value = false
          // Datei herunterladen
          await downloadExportResult()
        } else if (status === 'failed' || status === 'cancelled') {
          exportPolling.value = false
        } else {
          // Weiter pollen
          pollExportProgress()
        }
      } catch {
        exportPolling.value = false
        exportProgress.value = { ...exportProgress.value, status: 'failed', error: 'Verbindungsfehler' }
      }
    }, 1500)
  }

  async function downloadExportResult() {
    if (!exportKey.value) return
    try {
      const response = await excelTemplatesApi.downloadExport(exportKey.value)
      const blob = new Blob([response.data], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      const customName = excelSettings.value?.fileName?.trim()
      const baseName = customName || currentTemplate.value?.name || 'excel-export'
      link.download = `${baseName}-${new Date().toISOString().slice(0, 10)}.xlsx`
      link.click()
      URL.revokeObjectURL(url)
    } finally {
      exportKey.value = null
      exportProgress.value = null
    }
  }

  async function cancelExport() {
    if (!exportKey.value) return
    try {
      await excelTemplatesApi.cancelExport(exportKey.value)
      exportProgress.value = { ...exportProgress.value, status: 'cancelling' }
    } catch {
      // ignore
    }
  }

  function dismissExport() {
    exportPolling.value = false
    if (pollTimeout) {
      clearTimeout(pollTimeout)
      pollTimeout = null
    }
    exportKey.value = null
    exportProgress.value = null
  }

  async function importFromExcel(file) {
    const { data } = await excelTemplatesApi.import(file)
    const imported = data.data || data
    // Importierte Struktur als templateJson setzen
    if (imported.sheets) {
      templateJson.value = imported
    } else if (imported.template_json) {
      templateJson.value = imported.template_json
    }
    isDirty.value = true
    return imported
  }

  // --- Computed ---

  const selectedSheet = computed(() => {
    if (!selectedSheetId.value) return null
    return findSheet(selectedSheetId.value)
  })

  const selectedColumn = computed(() => {
    if (!selectedColumnId.value || !selectedSheetId.value) return null
    const sheet = findSheet(selectedSheetId.value)
    if (!sheet) return null
    return sheet.columns.find(c => c.id === selectedColumnId.value) || null
  })

  // --- Helpers ---

  function findSheet(sheetId) {
    return findSheetRecursive(templateJson.value.sheets, sheetId)
  }

  function findSheetRecursive(sheets, sheetId) {
    for (const s of sheets) {
      if (s.id === sheetId) return s
      if (s.sheets?.length) {
        const found = findSheetRecursive(s.sheets, sheetId)
        if (found) return found
      }
    }
    return null
  }

  return {
    // State
    templates, loading, currentTemplate, templateJson, excelSettings,
    availableFields, fieldsLoading,
    selectedSheetId, selectedColumnId, selectedSheet, selectedColumn,
    isDirty,
    previewData, previewLoading,
    exportProgress, exportKey, exportPolling,

    // Actions
    loadTemplates, loadTemplate, saveTemplate, saveTemplateAs, createTemplate, deleteTemplate,
    loadFields,
    addSheet, removeSheet, updateSheet, duplicateSheet,
    addColumn, removeColumn, updateColumn, moveColumn,
    addHeaderRow, removeHeaderRow, addFooterRow, removeFooterRow,
    selectSheet, selectColumn, clearSelection,
    loadPreview, downloadExcel, cancelExport, dismissExport, importFromExcel,
  }
})

// --- Exported Helpers ---

export function createEmptyTemplate() {
  return {
    version: 1,
    sheets: [{
      id: generateId('sh'),
      label: 'Blatt 1',
      field: 'none',
      sortOrder: 'asc',
      columns: [],
      headerRows: [],
      footerRows: [],
      sheets: [],
    }],
  }
}

export function defaultExcelSettings() {
  return {
    headerStyle: { bgColor: '4472C4', fontColor: 'FFFFFF', bold: true },
    freezeHeader: true,
    autoFilter: true,
    defaultThumbnailHeight: 50,
    defaultColumnWidth: null,
  }
}

function generateId(prefix = 'el') {
  return prefix + '_' + Math.random().toString(36).slice(2, 10)
}
