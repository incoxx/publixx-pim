<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { resolveBinding } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
})

const containerRef = ref(null)
const qrDataUrl = ref('')

const value = computed(() => {
  return String(resolveBinding(props.data, props.element.bind) || '')
})

const s = computed(() => props.element.style || {})
const isQr = computed(() => props.element.type === 'qrcode')

async function renderBarcode() {
  if (!value.value) return

  if (isQr.value) {
    try {
      const QRCode = await import('qrcode')
      qrDataUrl.value = await QRCode.toDataURL(value.value, {
        width: parseInt(props.element.width || props.element.w || 100),
        margin: 1,
        color: { dark: s.value.lineColor || '#000000', light: '#ffffff' },
      })
    } catch (e) {
      console.warn('QR generation failed:', e)
    }
  } else {
    try {
      const JsBarcode = (await import('jsbarcode')).default
      if (containerRef.value) {
        const svg = containerRef.value.querySelector('svg')
        if (svg) {
          JsBarcode(svg, value.value, {
            format: 'CODE128',
            lineColor: s.value.lineColor || '#000000',
            width: 2,
            height: 60,
            displayValue: true,
            fontSize: 12,
            margin: 4,
          })
        }
      }
    } catch (e) {
      console.warn('Barcode generation failed:', e)
    }
  }
}

onMounted(() => renderBarcode())
watch(value, () => renderBarcode())
</script>

<template>
  <div
    ref="containerRef"
    :style="{
      padding: (s.padding || 8) + 'px',
      width: '100%',
      height: '100%',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      boxSizing: 'border-box',
    }"
  >
    <img v-if="isQr && qrDataUrl" :src="qrDataUrl" class="max-w-full max-h-full" alt="QR Code" />
    <svg v-else-if="!isQr" class="max-w-full max-h-full" />
    <span v-else class="text-xs text-gray-400">Kein Wert</span>
  </div>
</template>
