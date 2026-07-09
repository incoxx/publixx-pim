import { ref } from 'vue'

/**
 * Teilt sich den Show/Hide- und Event-Klebstoff für PimTable's Quick-Lookup-Zeile,
 * damit Filterung serverseitig über die komplette Treffermenge läuft statt nur über
 * die aktuell geladene Seite. Die eigentliche Feld-Zuordnung (Spalte → Backend-Param)
 * und der Refetch bleiben Sache der aufrufenden View (siehe ProductListView.vue).
 *
 * @param {(filters: Record<string, any>) => void} onApply - wird mit den rohen
 *   Quick-Lookup-Werten (Spalten-Key → Wert) aufgerufen, sobald sie sich ändern.
 */
export function useServerQuickLookup(onApply) {
  const showQuickLookup = ref(false)
  const quickLookupFilters = ref({})

  function onQuickLookupChange(filters) {
    quickLookupFilters.value = filters
    onApply(filters)
  }

  function toggleQuickLookup(pimTableRef) {
    showQuickLookup.value = !showQuickLookup.value
    if (!showQuickLookup.value && Object.keys(quickLookupFilters.value).length > 0) {
      quickLookupFilters.value = {}
      pimTableRef?.value?.clearQuickLookup?.()
      onApply({})
    }
  }

  return {
    showQuickLookup,
    quickLookupFilters,
    onQuickLookupChange,
    toggleQuickLookup,
  }
}
