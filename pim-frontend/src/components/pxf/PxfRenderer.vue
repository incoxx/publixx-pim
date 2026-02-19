<script setup>
import { ref, computed } from 'vue'
import { ZoomIn, ZoomOut, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import PxfPage from './PxfPage.vue'

const props = defineProps({
  pxf: { type: Object, required: true },
  zoom: { type: Number, default: 0.5 },
  showControls: { type: Boolean, default: true },
})

const currentZoom = ref(props.zoom)
const currentPageIndex = ref(0)

const template = computed(() => props.pxf?.template || {})
const data = computed(() => props.pxf?.data || [])
const config = computed(() => props.pxf?.config || {})
const routing = computed(() => props.pxf?.templateRouting || {})

const pageCount = computed(() => Math.max(data.value.length, 1))

const orientation = computed(() => template.value.orientation || 'a4hoch')
const pageWidth = computed(() => orientation.value === 'a4quer' ? 1122 : 794)
const pageHeight = computed(() => orientation.value === 'a4quer' ? 794 : 1122)

// Resolve template elements per page (via templateRouting)
function getElementsForPage(pageIndex) {
  const record = data.value[pageIndex]
  if (!record) return template.value.elements || []

  if (routing.value?.enabled && routing.value.rules?.length) {
    for (const rule of routing.value.rules) {
      const fieldValue = record[rule.field]
      let match = false
      if (rule.operator === 'equals') match = fieldValue === rule.value
      else if (rule.operator === 'contains') match = String(fieldValue || '').includes(rule.value)
      if (match && routing.value.templates?.[rule.templateIndex]) {
        return routing.value.templates[rule.templateIndex].elements || []
      }
    }
  }

  return template.value.elements || []
}

function zoomIn() {
  currentZoom.value = Math.min(currentZoom.value + 0.1, 2)
}

function zoomOut() {
  currentZoom.value = Math.max(currentZoom.value - 0.1, 0.2)
}

function prevPage() {
  currentPageIndex.value = Math.max(0, currentPageIndex.value - 1)
}

function nextPage() {
  currentPageIndex.value = Math.min(pageCount.value - 1, currentPageIndex.value + 1)
}
</script>

<template>
  <div class="flex flex-col items-center gap-4">
    <!-- Controls -->
    <div v-if="showControls" class="flex items-center gap-3">
      <button class="pim-btn pim-btn-ghost p-1.5" @click="zoomOut" :disabled="currentZoom <= 0.2">
        <ZoomOut class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <span class="text-xs text-[var(--color-text-secondary)] min-w-[40px] text-center font-mono">
        {{ Math.round(currentZoom * 100) }}%
      </span>
      <button class="pim-btn pim-btn-ghost p-1.5" @click="zoomIn" :disabled="currentZoom >= 2">
        <ZoomIn class="w-4 h-4" :stroke-width="1.75" />
      </button>

      <div v-if="pageCount > 1" class="flex items-center gap-1 ml-4">
        <button class="pim-btn pim-btn-ghost p-1.5" @click="prevPage" :disabled="currentPageIndex === 0">
          <ChevronLeft class="w-4 h-4" :stroke-width="1.75" />
        </button>
        <span class="text-xs text-[var(--color-text-secondary)] font-mono">
          {{ currentPageIndex + 1 }} / {{ pageCount }}
        </span>
        <button class="pim-btn pim-btn-ghost p-1.5" @click="nextPage" :disabled="currentPageIndex >= pageCount - 1">
          <ChevronRight class="w-4 h-4" :stroke-width="1.75" />
        </button>
      </div>
    </div>

    <!-- Page -->
    <div
      class="overflow-auto"
      :style="{ maxHeight: showControls ? 'calc(100vh - 200px)' : '100%' }"
    >
      <PxfPage
        :elements="getElementsForPage(currentPageIndex)"
        :data="data[currentPageIndex] || {}"
        :config="config"
        :width="pageWidth"
        :height="pageHeight"
        :zoom="currentZoom"
      />
    </div>
  </div>
</template>
