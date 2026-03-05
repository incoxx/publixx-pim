import { ref, computed, watch, isRef } from 'vue'

/**
 * Composable for configurable table columns with localStorage persistence.
 *
 * @param {string} storageKey - localStorage key, e.g. 'columns:products'
 * @param {Array} defaultColumns - Default visible columns [{key, label, sortable?, mono?}]
 * @param {Array} extraColumns - Additional optional columns (hidden by default)
 * @param {Ref<Array>|Array} dynamicColumns - Reactive ref of dynamically loaded columns (e.g. attributes)
 */
export function useColumnConfig(storageKey, defaultColumns, extraColumns = [], dynamicColumns = []) {
  const staticColumns = [...defaultColumns, ...extraColumns]
  const defaultKeys = defaultColumns.map(c => c.key)

  // allColumns is reactive to support dynamicColumns that load asynchronously
  const allColumns = computed(() => {
    const dynamic = isRef(dynamicColumns) ? dynamicColumns.value : dynamicColumns
    return [...staticColumns, ...dynamic]
  })

  // Load from localStorage or use defaults
  function loadVisibleKeys() {
    try {
      const stored = localStorage.getItem(storageKey)
      if (stored) {
        const keys = JSON.parse(stored)
        // Only validate against static columns at load time;
        // dynamic columns may not be loaded yet — keep their keys
        const staticKeys = new Set(staticColumns.map(c => c.key))
        const validKeys = keys.filter(k => staticKeys.has(k) || k.startsWith('attributes.'))
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
      .map(key => allColumns.value.find(c => c.key === key))
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
