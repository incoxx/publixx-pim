import { ref } from 'vue'
import columnProfilesApi from '@/api/columnProfiles'

export function useColumnProfiles(context, visibleKeys) {
  const columnProfiles = ref([])
  const selectedColumnProfileId = ref(null)

  async function loadColumnProfiles() {
    try {
      const { data } = await columnProfilesApi.list(context)
      columnProfiles.value = data.data || data
    } catch { /* ignore */ }
  }

  function loadColumnProfile(id) {
    const profile = columnProfiles.value.find(p => p.id === id)
    if (profile?.visible_keys) {
      visibleKeys.value = [...profile.visible_keys]
    }
  }

  async function saveColumnProfile({ name, is_shared }) {
    await columnProfilesApi.create({ name, is_shared, context, visible_keys: visibleKeys.value })
    await loadColumnProfiles()
  }

  async function updateColumnProfile({ id, name, is_shared }) {
    await columnProfilesApi.update(id, { name, is_shared, visible_keys: visibleKeys.value })
    await loadColumnProfiles()
  }

  async function deleteColumnProfile(id) {
    await columnProfilesApi.remove(id)
    selectedColumnProfileId.value = null
    await loadColumnProfiles()
  }

  return {
    columnProfiles,
    selectedColumnProfileId,
    loadColumnProfiles,
    loadColumnProfile,
    saveColumnProfile,
    updateColumnProfile,
    deleteColumnProfile,
  }
}
