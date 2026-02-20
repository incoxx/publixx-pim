<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useAuthStore } from '@/stores/auth'
import hierarchiesApi from '@/api/hierarchies'
import PimForm from '@/components/shared/PimForm.vue'

const props = defineProps({
  productTypes: { type: Array, default: () => [] },
})

const router = useRouter()
const store = useProductStore()
const authStore = useAuthStore()
const loading = ref(false)
const errors = ref({})
const hierarchyNodes = ref([])

const formData = ref({
  sku: '',
  name: '',
  product_type_id: '',
  ean: '',
  status: 'draft',
  master_hierarchy_node_id: '',
})

async function loadHierarchyNodes() {
  try {
    const { data } = await hierarchiesApi.list()
    const hierarchies = data.data || data
    for (const h of hierarchies) {
      try {
        const { data: treeData } = await hierarchiesApi.getTree(h.id)
        const tree = treeData.data || treeData
        flattenTree(tree, '')
      } catch { /* silently fail */ }
    }
  } catch { /* silently fail */ }
}

function flattenTree(nodes, prefix) {
  for (const node of (Array.isArray(nodes) ? nodes : [])) {
    const label = prefix + (node.name_de || node.name_en || node.id)
    hierarchyNodes.value.push({ value: node.id, label })
    if (node.children?.length) {
      flattenTree(node.children, label + ' › ')
    }
  }
}

const fields = computed(() => [
  { key: 'sku', label: 'SKU / Artikelnummer', type: 'text', required: true },
  { key: 'name', label: 'Name', type: 'text', required: true },
  {
    key: 'product_type_id', label: 'Produkttyp', type: 'select', required: true,
    options: props.productTypes.map(t => ({ value: t.id, label: t.name_de || t.technical_name })),
  },
  { key: 'ean', label: 'EAN', type: 'text' },
  {
    key: 'status', label: 'Status', type: 'select',
    options: [
      { value: 'draft', label: 'Entwurf' },
      { value: 'active', label: 'Aktiv' },
      { value: 'inactive', label: 'Inaktiv' },
      { value: 'discontinued', label: 'Auslaufend' },
    ],
  },
  {
    key: 'master_hierarchy_node_id', label: 'Master-Hierarchie-Knoten', type: 'select',
    options: [{ value: '', label: '— Kein Knoten —' }, ...hierarchyNodes.value],
  },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data }
  if (!payload.master_hierarchy_node_id) delete payload.master_hierarchy_node_id
  try {
    const result = await store.create(payload)
    authStore.closePanel()
    if (result?.id) {
      router.push(`/products/${result.id}`)
    }
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => loadHierarchyNodes())
</script>

<template>
  <div class="p-4">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">Neues Produkt</h3>
    <PimForm
      :fields="fields"
      :modelValue="formData"
      :errors="errors"
      :loading="loading"
      @update:modelValue="formData = $event"
      @submit="handleSubmit"
      @cancel="authStore.closePanel()"
    />
  </div>
</template>
