import { defineStore } from 'pinia'
import { ref } from 'vue'

// Session-Storage: Kontext bleibt bei Reload erhalten, ist aber pro Browser-Tab isoliert.
const STORAGE_KEY = 'pim_record_navigator'

function loadContext() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function persistContext(context) {
  try {
    if (context) {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(context))
    } else {
      sessionStorage.removeItem(STORAGE_KEY)
    }
  } catch { /* ignore */ }
}

/**
 * Hält eine geordnete Liste von Produkt-IDs (z.B. aus der Merkliste), damit der
 * Produkteditor einen "Produkt X/Y"-Navigator mit vor/zurück anzeigen kann.
 */
export const useRecordNavigatorStore = defineStore('recordNavigator', () => {
  const stored = loadContext()
  const label = ref(stored?.label ?? null)
  const ids = ref(stored?.ids ?? [])

  function setContext(newLabel, newIds) {
    label.value = newLabel
    ids.value = newIds
    persistContext({ label: newLabel, ids: newIds })
  }

  function clear() {
    label.value = null
    ids.value = []
    persistContext(null)
  }

  function indexOf(id) {
    return ids.value.indexOf(id)
  }

  return { label, ids, setContext, clear, indexOf }
})
