import { ref, computed, watch } from 'vue'

/**
 * Composable for configurable table columns with localStorage persistence.
 *
 * @param {string} storageKey - localStorage key, e.g. 'columns:products'
 * @param {Array} defaultColumns - Default visible columns [{key, label, sortable?, mono?}]
 * @param {Array} extraColumns - Additional optional columns (hidden by default)
 */
export function useColumnConfig(storageKey, defaultColumns, extraColumns = []) {
  const allColumns = [...defaultColumns, ...extraColumns]
  const defaultKeys = defaultColumns.map(c => c.key)

  // Load from localStorage or use defaults
  function loadVisibleKeys() {
    try {
      const stored = localStorage.getItem(storageKey)
      if (stored) {
        const keys = JSON.parse(stored)
        // Filter out keys that no longer exist
        const validKeys = keys.filter(k => allColumns.some(c => c.key === k))
        if (validKeys.length > 0) return validKeys
      }
    } catch (e) { console.warn('Failed to load column config:', e) }
    return [...defaultKeys]
  }

  const visibleKeys = ref(loadVisibleKeys())

  // Persist on change
  watch(visibleKeys, (keys) => {
    localStorage.setItem(storageKey, JSON.stringify(keys))
  }, { deep: true })

  const visibleColumns = computed(() =>
    visibleKeys.value
      .map(key => allColumns.find(c => c.key === key))
      .filter(Boolean)
  )

  function isColumnVisible(key) {
    return visibleKeys.value.includes(key)
  }

  function toggleColumn(key) {
    const idx = visibleKeys.value.indexOf(key)
    if (idx === -1) {
      visibleKeys.value.push(key)
    } else if (visibleKeys.value.length > 1) {
      visibleKeys.value.splice(idx, 1)
    }
  }

  function moveColumn(key, direction) {
    const idx = visibleKeys.value.indexOf(key)
    if (idx === -1) return
    const newIdx = direction === 'up' ? idx - 1 : idx + 1
    if (newIdx < 0 || newIdx >= visibleKeys.value.length) return
    const keys = [...visibleKeys.value]
    ;[keys[idx], keys[newIdx]] = [keys[newIdx], keys[idx]]
    visibleKeys.value = keys
  }

  function resetColumns() {
    visibleKeys.value = [...defaultKeys]
  }

  return {
    allColumns,
    visibleColumns,
    visibleKeys,
    isColumnVisible,
    toggleColumn,
    moveColumn,
    resetColumns,
  }
}
