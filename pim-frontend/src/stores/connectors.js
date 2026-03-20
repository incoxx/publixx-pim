import { ref } from 'vue'
import { defineStore } from 'pinia'
import connectorsApi from '@/api/connectors'

export const useConnectorsStore = defineStore('connectors', () => {
  const connectors = ref([])
  const connections = ref([])
  const loading = ref(false)

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
    loadConnectors,
    loadConnections,
    deleteConnection,
  }
})
