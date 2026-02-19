<script setup>
import { computed, defineAsyncComponent } from 'vue'
import { checkVisibility } from './PxfDataResolver.js'

const PxfTextElement = defineAsyncComponent(() => import('./elements/PxfTextElement.vue'))
const PxfImageElement = defineAsyncComponent(() => import('./elements/PxfImageElement.vue'))
const PxfRectElement = defineAsyncComponent(() => import('./elements/PxfRectElement.vue'))
const PxfLineElement = defineAsyncComponent(() => import('./elements/PxfLineElement.vue'))
const PxfListElement = defineAsyncComponent(() => import('./elements/PxfListElement.vue'))
const PxfTableElement = defineAsyncComponent(() => import('./elements/PxfTableElement.vue'))
const PxfChartElement = defineAsyncComponent(() => import('./elements/PxfChartElement.vue'))
const PxfBarcodeElement = defineAsyncComponent(() => import('./elements/PxfBarcodeElement.vue'))
const PxfMapElement = defineAsyncComponent(() => import('./elements/PxfMapElement.vue'))
const PxfGroupElement = defineAsyncComponent(() => import('./elements/PxfGroupElement.vue'))
const PxfVideoElement = defineAsyncComponent(() => import('./elements/PxfVideoElement.vue'))
const PxfAudioElement = defineAsyncComponent(() => import('./elements/PxfAudioElement.vue'))
const PxfSmartObject = defineAsyncComponent(() => import('./elements/PxfSmartObject.vue'))

const props = defineProps({
  elements: { type: Array, default: () => [] },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
  width: { type: Number, default: 794 },
  height: { type: Number, default: 1122 },
  zoom: { type: Number, default: 0.5 },
})

const componentMap = {
  text: PxfTextElement,
  fixedText: PxfTextElement,
  image: PxfImageElement,
  rect: PxfRectElement,
  line: PxfLineElement,
  list: PxfListElement,
  table: PxfTableElement,
  smartTable: PxfTableElement,
  chart: PxfChartElement,
  barcode: PxfBarcodeElement,
  qrcode: PxfBarcodeElement,
  map: PxfMapElement,
  group: PxfGroupElement,
  video: PxfVideoElement,
  audio: PxfAudioElement,
  smartObject: PxfSmartObject,
}

const visibleElements = computed(() =>
  props.elements.filter(el => {
    if (el.invisible) return false
    return checkVisibility(props.data, el.visibility)
  })
)

function getComponent(type) {
  return componentMap[type] || null
}
</script>

<template>
  <div
    class="relative bg-white shadow-lg"
    :style="{
      width: width * zoom + 'px',
      height: height * zoom + 'px',
      overflow: 'hidden',
    }"
  >
    <div
      :style="{
        width: width + 'px',
        height: height + 'px',
        transform: `scale(${zoom})`,
        transformOrigin: 'top left',
        position: 'relative',
      }"
    >
      <component
        v-for="el in visibleElements"
        :key="el.id"
        :is="getComponent(el.type)"
        :element="el"
        :data="data"
        :config="config"
        :style="{
          position: 'absolute',
          left: (el.x || 0) + 'px',
          top: (el.y || 0) + 'px',
          width: (el.width || el.w || 100) + 'px',
          height: (el.height || el.h || 40) + 'px',
        }"
      />
    </div>
  </div>
</template>
