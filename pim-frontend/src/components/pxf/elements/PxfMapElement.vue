<script setup>
import { ref, computed, onMounted } from 'vue'
import { resolveBinding } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
})

const mapContainer = ref(null)
const mc = computed(() => props.element.mapConfig || {})

const lat = computed(() => {
  const v = resolveBinding(props.data, mc.value.latBind)
  return parseFloat(v) || 48.8566
})

const lon = computed(() => {
  const v = resolveBinding(props.data, mc.value.lonBind)
  return parseFloat(v) || 2.3522
})

onMounted(async () => {
  if (!mapContainer.value) return
  try {
    const L = await import('leaflet')
    await import('leaflet/dist/leaflet.css')

    const map = L.map(mapContainer.value).setView([lat.value, lon.value], mc.value.zoom || 13)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19,
    }).addTo(map)

    if (mc.value.markerEnabled !== false) {
      L.circleMarker([lat.value, lon.value], {
        radius: 8,
        fillColor: mc.value.markerColor || '#e4002b',
        color: '#fff',
        weight: 2,
        fillOpacity: 0.9,
      }).addTo(map)
    }

    setTimeout(() => map.invalidateSize(), 100)
  } catch (e) {
    console.warn('Map init failed:', e)
  }
})
</script>

<template>
  <div ref="mapContainer" class="w-full h-full" />
</template>
