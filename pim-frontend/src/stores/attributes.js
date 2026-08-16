import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import attributesApi, { attributeTypes, valueLists, formattingRules, productTypes, attributeMetadataDefinitions } from '@/api/attributes'
import { unitGroups as unitGroupsApi } from '@/api/units'
import { comparisonOperatorGroups as compOpGroupsApi } from '@/api/comparisonOperators'

export const useAttributeStore = defineStore('attributes', () => {
  const items = ref([])
  const allItems = ref([])
  const types = ref([])
  const lists = ref([])
  const formattingRulesList = ref([])
  const prodTypes = ref([])
  const unitGroupsList = ref([])
  const compOpGroupsList = ref([])
  const metadataDefinitions = ref([])
  const loading = ref(false)
  const error = ref(null)
  const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 50 })

  // Dropdown-Optionen für Formular-Selects
  const attributeTypeOptions = computed(() =>
    types.value.map(t => ({ value: t.id, label: t.name_de || t.technical_name }))
  )

  const valueListOptions = computed(() =>
    lists.value.map(l => ({ value: l.id, label: l.name_de || l.technical_name }))
  )

  const formattingRuleOptions = computed(() =>
    formattingRulesList.value.map(r => ({ value: r.id, label: r.name, rule_type: r.rule_type }))
  )

  const unitGroupOptions = computed(() =>
    unitGroupsList.value.map(g => ({ value: g.id, label: g.name_de || g.technical_name }))
  )

  const comparisonOperatorGroupOptions = computed(() =>
    compOpGroupsList.value.map(g => ({ value: g.id, label: g.name_de || g.technical_name }))
  )

  async function fetchAttributes(options = {}) {
    loading.value = true
    try {
      const { data } = await attributesApi.list({
        perPage: meta.value.per_page,
        page: meta.value.current_page,
        include: 'valueList,unitGroup,children',
        ...options,
      })
      items.value = data.data
      if (data.meta) meta.value = data.meta
    } catch (e) {
      error.value = e.response?.data?.title || 'Fehler'
    } finally {
      loading.value = false
    }
  }

  async function fetchAllAttributes() {
    try {
      const { data } = await attributesApi.list({ perPage: 1000, include: 'children' })
      allItems.value = data.data
    } catch (e) {
      console.error('Failed to fetch all attributes', e)
    }
  }

  async function fetchTypes() {
    try {
      const { data } = await attributeTypes.list()
      types.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch attribute types', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Typen'
    }
  }

  async function fetchValueLists() {
    try {
      const { data } = await valueLists.list({ include: 'entries' })
      lists.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch value lists', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Wertelisten'
    }
  }

  async function fetchFormattingRules() {
    try {
      const { data } = await formattingRules.list({ perPage: 200 })
      formattingRulesList.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch formatting rules', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Formatierungsregeln'
    }
  }

  async function fetchProductTypes() {
    try {
      const { data } = await productTypes.list()
      prodTypes.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch product types', e)
      error.value = e.response?.data?.title || 'Fehler beim Laden der Produkttypen'
    }
  }

  async function fetchMetadataDefinitions() {
    try {
      const { data } = await attributeMetadataDefinitions.list({ perPage: 200, sort: 'sort_order' })
      metadataDefinitions.value = data.data || data
    } catch (e) {
      // Fehlende Leseberechtigung darf das Attribut-Formular nicht blockieren
      console.error('Failed to fetch metadata definitions', e)
      metadataDefinitions.value = []
    }
  }

  async function fetchUnitGroups() {
    try {
      const { data } = await unitGroupsApi.list({ include: 'units', perPage: 200 })
      unitGroupsList.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch unit groups', e)
    }
  }

  async function fetchComparisonOperatorGroups() {
    try {
      const { data } = await compOpGroupsApi.list({ include: 'operators', perPage: 200 })
      compOpGroupsList.value = data.data || data
    } catch (e) {
      console.error('Failed to fetch comparison operator groups', e)
    }
  }

  async function createAttribute(attrData) {
    const { data } = await attributesApi.create(attrData)
    return data.data || data
  }

  async function updateAttribute(id, attrData) {
    const { data } = await attributesApi.update(id, attrData)
    return data.data || data
  }

  async function copyAttribute(id) {
    const { data } = await attributesApi.copy(id)
    return data.data || data
  }

  async function deleteAttribute(id, { force = false } = {}) {
    await attributesApi.delete(id, { force })
    items.value = items.value.filter(a => a.id !== id)
  }

  function setPage(page) {
    meta.value = { ...meta.value, current_page: page }
  }

  async function bulkUpdate(ids, fields) {
    const { data } = await attributesApi.bulkUpdate(ids, fields)
    return data
  }

  async function bulkDelete(ids) {
    const { data } = await attributesApi.bulkDelete(ids)
    items.value = items.value.filter(a => !ids.includes(a.id))
    return data
  }

  async function bulkAssignViews(ids, mode, attributeViewIds) {
    const { data } = await attributesApi.bulkAssignViews(ids, mode, attributeViewIds)
    return data
  }

  return {
    items, allItems, types, lists, formattingRulesList, prodTypes, unitGroupsList, compOpGroupsList, metadataDefinitions, loading, error, meta,
    attributeTypeOptions, valueListOptions, formattingRuleOptions, unitGroupOptions, comparisonOperatorGroupOptions,
    fetchAttributes, fetchAllAttributes, fetchTypes, fetchValueLists, fetchFormattingRules, fetchProductTypes, fetchUnitGroups, fetchComparisonOperatorGroups, fetchMetadataDefinitions,
    createAttribute, updateAttribute, copyAttribute, deleteAttribute, setPage, bulkUpdate, bulkDelete, bulkAssignViews,
  }
})
