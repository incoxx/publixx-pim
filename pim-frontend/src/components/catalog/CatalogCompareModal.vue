<script setup>
import { ref, computed, watch } from 'vue'
import { useCatalogStore } from '@/stores/catalog'
import { GitCompareArrows, X } from 'lucide-vue-next'
import catalogApi from '@/api/catalog'

const props = defineProps({
  open: { type: Boolean, default: false },
  productIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:open'])
const store = useCatalogStore()

const loading = ref(false)
const compareData = ref(null)
const showDiffsOnly = ref(false)
const error = ref(null)

const compareRows = computed(() => {
  if (!compareData.value?.rows) return []
  if (showDiffsOnly.value) return compareData.value.rows.filter(r => r.is_different)
  return compareData.value.rows
})

watch(() => props.open, async (val) => {
  if (val && props.productIds.length >= 2) {
    loading.value = true
    compareData.value = null
    error.value = null
    try {
      const { data } = await catalogApi.compareProducts(props.productIds, store.locale)
      compareData.value = data.data || data
    } catch (e) {
      error.value = e.response?.data?.message || 'Vergleich fehlgeschlagen'
      console.error('Compare failed:', e)
    } finally {
      loading.value = false
    }
  }
})

function close() {
  emit('update:open', false)
}
</script>

<template>
  <transition name="fade">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />
      <div class="relative w-full max-w-6xl max-h-[90vh] bg-base-100 border border-base-300 rounded-xl shadow-xl mx-4 overflow-hidden flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-base-300 shrink-0">
          <div class="flex items-center gap-3">
            <GitCompareArrows class="w-5 h-5 text-primary" />
            <span class="text-sm font-semibold">Produktvergleich</span>
            <span v-if="compareData" class="text-[11px] text-base-content/50">
              {{ compareData.total_differences }} Unterschiede von {{ compareData.total_attributes }} Feldern
            </span>
          </div>
          <div class="flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-[11px] text-base-content/60 cursor-pointer">
              <input type="checkbox" v-model="showDiffsOnly" class="checkbox checkbox-xs checkbox-primary" />
              Nur Unterschiede
            </label>
            <button class="btn btn-ghost btn-sm btn-circle" @click="close">
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-auto">
          <!-- Error -->
          <div v-if="error" class="p-6 text-center text-error text-sm">{{ error }}</div>

          <!-- Loading -->
          <div v-else-if="loading" class="p-8 space-y-3">
            <div v-for="i in 8" :key="i" class="skeleton h-8 w-full rounded" />
          </div>

          <!-- Compare table -->
          <table v-else-if="compareData" class="w-full text-[13px]">
            <thead class="sticky top-0 z-10">
              <tr class="bg-base-200 border-b border-base-300">
                <th class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-base-content/50 w-[200px]">Attribut</th>
                <th
                  v-for="(prod, idx) in compareData.products"
                  :key="prod.id"
                  class="px-4 py-2.5 text-left font-medium text-[11px] uppercase tracking-wider text-primary"
                >
                  {{ prod.sku }}
                  <span class="font-normal normal-case text-base-content/50">{{ prod.name }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, i) in compareRows"
                :key="i"
                :class="['border-b border-base-300', row.is_different ? 'bg-warning/10' : '']"
              >
                <td class="px-4 py-2 text-[12px] font-medium text-base-content/60">
                  {{ row.attribute_name }}
                </td>
                <td
                  v-for="(val, idx) in row.values"
                  :key="idx"
                  class="px-4 py-2 text-[12px]"
                  :class="row.is_different ? 'font-medium' : ''"
                >
                  {{ val ?? '—' }}
                </td>
              </tr>
              <tr v-if="compareRows.length === 0">
                <td :colspan="1 + (compareData?.products?.length || 0)" class="px-4 py-8 text-center text-base-content/40 text-sm">
                  {{ showDiffsOnly ? 'Keine Unterschiede gefunden' : 'Keine Attribute vorhanden' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </transition>
</template>
