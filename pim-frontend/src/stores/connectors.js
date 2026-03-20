import { ref } from 'vue'
import { defineStore } from 'pinia'
import connectorsApi from '@/api/connectors'

export const useConnectorsStore = defineStore('connectors', () => {
  const connectors = ref([])
  const connections = ref([])
  const loading = ref(false)
  const configuredPlugins = ref([])
  const configuredPluginsLoaded = ref(false)

  async function loadConfiguredPlugins() {
    try {
      const { data } = await connectorsApi.configuredPlugins()
      configuredPlugins.value = data.data || data
    } catch {
      configuredPlugins.value = []
    } finally {
      configuredPluginsLoaded.value = true
    }
  }

  function isPluginConfigured(key) {
    return configuredPlugins.value.includes(key)
  }

  async function loadConnectors() {
    loading.value = true
    try {
      const { data } = await connectorsApi.list()
      connectors.value = data.data || data
    } finally {
      loading.value = false
    }
  }

  async function loadConnections() {
    loading.value = true
    try {
      const { data } = await connectorsApi.connections()
      connections.value = data.data || data
    } finally {
      loading.value = false
    }
  }

  async function deleteConnection(id) {
    await connectorsApi.deleteConnection(id)
    connections.value = connections.value.filter(c => c.id !== id)
  }

  return {
    connectors,
    connections,
    loading,
    configuredPlugins,
    configuredPluginsLoaded,
    loadConfiguredPlugins,
    isPluginConfigured,
    loadConnectors,
    loadConnections,
    deleteConnection,
  }
})
