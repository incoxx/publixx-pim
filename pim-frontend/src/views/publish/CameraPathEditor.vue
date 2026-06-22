<script setup>
import { ref, reactive, computed, watch, onBeforeUnmount } from 'vue'
import { Play, ImageOff } from 'lucide-vue-next'

/**
 * Visueller Kamerafahrt-Editor (Fokuspunkt-Zoom) für ein Produktbild.
 * Der/die Nutzer:in setzt Start- und Ziel-Fokuspunkt (Klick/Ziehen) plus Zoom;
 * eine Live-Vorschau fährt vom Start zum Ziel. Die Logik (transform-origin 0 0,
 * translate((50 - zoom*x)%, (50 - zoom*y)%) scale(zoom)) ist identisch zum
 * Renderer (reel-renderer.ts) → WYSIWYG.
 */
const props = defineProps({
  image: { type: String, default: null },
  modelValue: { type: Object, default: null }, // { from:{x,y,zoom}, to:{x,y,zoom} }
  format: { type: String, default: '9x16' },
})
const emit = defineEmits(['update:modelValue'])

const aspectClass = computed(() => ({
  '9x16': 'aspect-[9/16]',
  '1x1': 'aspect-square',
  '16x9': 'aspect-video',
}[props.format] || 'aspect-[9/16]'))

function defaultMotion() {
  return { from: { x: 50, y: 50, zoom: 1.0 }, to: { x: 50, y: 50, zoom: 1.3 } }
}
const motion = reactive(props.modelValue ? clone(props.modelValue) : defaultMotion())
function clone(o) { return JSON.parse(JSON.stringify(o)) }

const active = ref('from') // 'from' | 'to'

const imgBox = ref(null)
let dragging = false

function setFromEvent(e) {
  const el = imgBox.value
  if (!el) return
  const r = el.getBoundingClientRect()
  const cx = (e.touches ? e.touches[0].clientX : e.clientX)
  const cy = (e.touches ? e.touches[0].clientY : e.clientY)
  const x = Math.max(0, Math.min(100, ((cx - r.left) / r.width) * 100))
  const y = Math.max(0, Math.min(100, ((cy - r.top) / r.height) * 100))
  motion[active.value].x = Math.round(x * 10) / 10
  motion[active.value].y = Math.round(y * 10) / 10
}
function onDown(e) { dragging = true; setFromEvent(e) }
function onMove(e) { if (dragging) { e.preventDefault(); setFromEvent(e) } }
function onUp() { dragging = false }

watch(motion, () => emit('update:modelValue', clone(motion)), { deep: true })

// Identische Transform-Formel wie im Renderer.
function transformFor(p) {
  return `translate(${(50 - p.zoom * p.x).toFixed(2)}%, ${(50 - p.zoom * p.y).toFixed(2)}%) scale(${p.zoom})`
}

// Sichtbarer Ausschnitt (Rahmen) eines Keyframes als % der Box.
function frameStyle(p) {
  const size = 100 / p.zoom
  return {
    width: size + '%',
    height: size + '%',
    left: (p.x - size / 2) + '%',
    top: (p.y - size / 2) + '%',
  }
}
function markerStyle(p) {
  return { left: p.x + '%', top: p.y + '%' }
}

// Live-Vorschau via Web Animations API (kein zusätzliches CSS nötig).
const previewImg = ref(null)
let anim = null
function playPreview() {
  if (!previewImg.value) return
  if (anim) anim.cancel()
  anim = previewImg.value.animate(
    [{ transform: transformFor(motion.from) }, { transform: transformFor(motion.to) }],
    { duration: 4000, easing: 'ease-out', fill: 'forwards' },
  )
}
onBeforeUnmount(() => { if (anim) anim.cancel() })

function reset() {
  Object.assign(motion, defaultMotion())
}
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <!-- Editor: Fokuspunkte setzen -->
    <div>
      <div class="flex items-center gap-1 mb-1.5">
        <button
          type="button"
          class="px-2 py-0.5 text-[11px] rounded-md border transition-colors"
          :class="active === 'from'
            ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)]'
            : 'border-[var(--color-border)] text-[var(--color-text-secondary)]'"
          @click="active = 'from'"
        >● Start</button>
        <button
          type="button"
          class="px-2 py-0.5 text-[11px] rounded-md border transition-colors"
          :class="active === 'to'
            ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)]'
            : 'border-[var(--color-border)] text-[var(--color-text-secondary)]'"
          @click="active = 'to'"
        >◎ Ende</button>
        <button type="button" class="ml-auto text-[10px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]" @click="reset">Zurücksetzen</button>
      </div>

      <div
        ref="imgBox"
        class="relative w-full overflow-hidden rounded-lg bg-black cursor-crosshair select-none"
        :class="aspectClass"
        @mousedown="onDown" @mousemove="onMove" @mouseup="onUp" @mouseleave="onUp"
        @touchstart.passive="onDown" @touchmove="onMove" @touchend="onUp"
      >
        <img v-if="image" :src="image" class="absolute inset-0 w-full h-full object-cover pointer-events-none" alt="" @error="$event.target.style.display='none'" />
        <div v-else class="absolute inset-0 flex items-center justify-center text-white/30">
          <ImageOff class="w-8 h-8" />
        </div>

        <!-- Rahmen + Marker für beide Keyframes -->
        <div class="absolute border-2 border-emerald-400/80 pointer-events-none" :style="frameStyle(motion.from)"></div>
        <div class="absolute border-2 border-rose-400/80 border-dashed pointer-events-none" :style="frameStyle(motion.to)"></div>
        <div class="absolute -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full bg-emerald-400 ring-2 ring-white pointer-events-none" :style="markerStyle(motion.from)"></div>
        <div class="absolute -translate-x-1/2 -translate-y-1/2 w-3 h-3 rounded-full bg-rose-400 ring-2 ring-white pointer-events-none" :style="markerStyle(motion.to)"></div>
      </div>

      <!-- Zoom des aktiven Keyframes -->
      <div class="flex items-center gap-2 mt-2">
        <span class="text-[11px] text-[var(--color-text-tertiary)] w-10">Zoom</span>
        <input type="range" min="1" max="2.5" step="0.05" v-model.number="motion[active].zoom" class="flex-1 accent-[var(--color-accent)]" />
        <span class="text-[11px] tabular-nums w-10 text-right text-[var(--color-text-secondary)]">{{ motion[active].zoom.toFixed(2) }}×</span>
      </div>
      <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
        Auf das Bild klicken/ziehen, um den {{ active === 'from' ? 'Start' : 'Ziel' }}-Fokus zu setzen.
      </p>
    </div>

    <!-- Live-Vorschau der Fahrt -->
    <div>
      <div class="flex items-center justify-between mb-1.5">
        <span class="text-[11px] text-[var(--color-text-tertiary)]">Vorschau</span>
        <button
          type="button"
          class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] rounded-md border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]"
          @click="playPreview"
        >
          <Play class="w-3 h-3" /> Abspielen
        </button>
      </div>
      <div class="relative w-full overflow-hidden rounded-lg bg-black" :class="aspectClass">
        <img
          v-if="image"
          ref="previewImg"
          :src="image"
          class="absolute inset-0 w-full h-full object-cover pointer-events-none"
          style="transform-origin: 0 0;"
          :style="{ transform: transformFor(motion.from) }"
          alt=""
          @error="$event.target.style.display='none'"
        />
        <div v-else class="absolute inset-0 flex items-center justify-center text-white/30">
          <ImageOff class="w-8 h-8" />
        </div>
      </div>
    </div>
  </div>
</template>
