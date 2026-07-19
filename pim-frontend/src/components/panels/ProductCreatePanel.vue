<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useAuthStore } from '@/stores/auth'
import hierarchiesApi from '@/api/hierarchies'
import manufacturersApi from '@/api/manufacturers'
import PimForm from '@/components/shared/PimForm.vue'
import { useToastStore } from '@/stores/toast'

const props = defineProps({
  productTypes: { type: Array, default: () => [] },
})

const router = useRouter()
const store = useProductStore()
const authStore = useAuthStore()
const toastStore = useToastStore()
const loading = ref(false)
const errors = ref({})
const hierarchyNodes = ref([])
const manufacturerOptions = ref([])

const formData = ref({
  sku: '',
  name: '',
  product_type_id: '',
  ean: '',
  status: 'draft',
  master_hierarchy_node_id: '',
  manufacturer_id: '',
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

async function loadManufacturers() {
  try {
    const { data } = await manufacturersApi.list({ perPage: 500 })
    const items = data.data || data
    manufacturerOptions.value = items.map(m => ({ value: m.id, label: m.name }))
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
  {
    key: 'manufacturer_id', label: 'Hersteller', type: 'select',
    options: [{ value: '', label: '— Kein Hersteller —' }, ...manufacturerOptions.value],
  },
])

async function handleSubmit(data) {
  loading.value = true
  errors.value = {}
  const payload = { ...data }
  if (!payload.master_hierarchy_node_id) delete payload.master_hierarchy_node_id
  if (!payload.manufacturer_id) delete payload.manufacturer_id
  try {
    const result = await store.create(payload)
    toastStore.showToast('Produkt angelegt', 'success')
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

onMounted(() => {
  loadHierarchyNodes()
  loadManufacturers()
})
</script>

<template>
  <div class="p-4" data-testid="product-create-panel">
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
