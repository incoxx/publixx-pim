<script setup>
import { computed } from 'vue'
import { ImageOff } from 'lucide-vue-next'

const props = defineProps({
  scene: { type: Object, required: true },
  format: { type: String, default: '9x16' },
  theme: { type: Object, default: () => ({}) },
})

// Seitenverhältnis-Klasse passend zum Reel-Format
const aspectClass = computed(() => ({
  '9x16': 'aspect-[9/16]',
  '1x1': 'aspect-square',
  '16x9': 'aspect-video',
}[props.format] || 'aspect-[9/16]'))

// Akzent-/Hintergrundfarbe als CSS-Variablen (gleiche Logik wie reel-renderer)
const frameVars = computed(() => ({
  '--accent': props.theme?.accent || '#06b6d4',
  '--bg': props.theme?.background || '#0f0f23',
}))

// Medien-Animation als sichtbare Endlosschleife in der Vorschau
const mediaAnimClass = computed(() => 'pv-' + (props.theme?.media_animation || 'kenburns'))

const imageUrl = computed(() => props.scene.image || null)

const priceFormatted = computed(() => {
  const v = props.scene.value
  return typeof v === 'number'
    ? v.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
    : ''
})

const isCentered = computed(() => ['price', 'cta'].includes(props.scene.type))
const badge = computed(() => (props.scene.type === 'feature' ? (props.scene.badge || 'Highlight') : props.scene.type === 'price' ? 'Preis' : null))
</script>

<template>
  <div class="scene-frame" :class="aspectClass" :style="frameVars">
    <!-- Hintergrundbild + Blur-Backdrop + Scrim (wie reel-renderer) -->
    <template v-if="imageUrl && scene.type !== 'cta'">
      <div class="bg-blur" :style="{ backgroundImage: `url('${imageUrl}')` }"></div>
      <img :src="imageUrl" class="bg-img" :class="mediaAnimClass" alt="" @error="$event.target.style.display = 'none'" />
      <div class="scrim"></div>
    </template>
    <div v-else class="bg-fallback">
      <ImageOff v-if="scene.type !== 'cta'" class="placeholder-ico" />
    </div>

    <!-- Inhalt (reine Bild-Szene zeigt nur das Bild) -->
    <div v-if="scene.type !== 'image'" class="content" :class="{ centered: isCentered }">
      <!-- Preis -->
      <template v-if="scene.type === 'price'">
        <div class="badge">{{ badge }}</div>
        <div>
          <span class="price">{{ priceFormatted }}</span>
          <span class="price-cur"> {{ scene.currency || 'EUR' }}</span>
        </div>
      </template>

      <!-- Call-to-Action -->
      <template v-else-if="scene.type === 'cta'">
        <div class="cta-btn">{{ scene.headline || 'Jetzt entdecken' }}</div>
        <div class="cta-url">www.incoxx.com</div>
      </template>

      <!-- Hero / Feature -->
      <template v-else>
        <div v-if="badge" class="badge">{{ badge }}</div>
        <div class="headline">{{ scene.headline || '—' }}</div>
        <div v-if="scene.subline" class="subline">{{ scene.subline }}</div>
      </template>
    </div>
  </div>
</template>

<style scoped>
/* Container-Query-Einheiten (cqw) halten die Proportionen exakt wie im 1080px-Render. */
.scene-frame {
  container-type: size;
  position: relative;
  width: 100%;
  overflow: hidden;
  border-radius: 0.75rem;
  background: var(--bg, #0f0f23);
  color: #fff;
  font-family: system-ui, -apple-system, sans-serif;
}

.bg-blur {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: blur(6cqw) brightness(0.5);
  transform: scale(1.2);
  z-index: 0;
}
.bg-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 1;
}
.scrim {
  position: absolute;
  inset: 0;
  z-index: 2;
  background: linear-gradient(
    to top,
    color-mix(in srgb, var(--bg, #0f0f23), transparent 5%) 0%,
    color-mix(in srgb, var(--bg, #0f0f23), transparent 90%) 55%,
    color-mix(in srgb, var(--bg, #0f0f23), transparent 70%) 100%
  );
}
.bg-fallback {
  position: absolute;
  inset: 0;
  z-index: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, color-mix(in srgb, var(--accent, #06b6d4) 25%, var(--bg, #0f0f23)), var(--bg, #0f0f23));
}
.placeholder-ico {
  width: 12cqw;
  height: 12cqw;
  opacity: 0.25;
}

.content {
  position: absolute;
  z-index: 3;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  text-align: center;
  padding: 0 6cqw 13cqw;
}
.content.centered {
  justify-content: center;
  gap: 3cqw;
  padding-bottom: 6cqw;
}

.badge {
  font-size: 2.4cqw;
  font-weight: 700;
  letter-spacing: 0.4cqw;
  text-transform: uppercase;
  color: var(--accent, #06b6d4);
  margin-bottom: 1.8cqw;
}
.headline {
  font-size: 7cqw;
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.1cqw;
  text-shadow: 0 0.4cqw 2.4cqw rgba(0, 0, 0, 0.5);
}
.subline {
  font-size: 3.3cqw;
  color: #cbd5e1;
  margin-top: 2.2cqw;
  line-height: 1.3;
}
.price {
  font-size: 11cqw;
  font-weight: 900;
  background: linear-gradient(135deg, color-mix(in srgb, var(--accent, #06b6d4) 65%, #000), var(--accent, #06b6d4));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
.price-cur {
  font-size: 4.4cqw;
  font-weight: 700;
  color: #94a3b8;
}
.cta-btn {
  font-size: 4cqw;
  font-weight: 800;
  padding: 2.5cqw 6cqw;
  border-radius: 999px;
  background: linear-gradient(135deg, color-mix(in srgb, var(--accent, #06b6d4) 65%, #000), var(--accent, #06b6d4));
}
.cta-url {
  font-size: 2.8cqw;
  color: #94a3b8;
  letter-spacing: 0.2cqw;
}

/* Medien-Animationen (Vorschau: Endlosschleife zur Veranschaulichung) */
.pv-kenburns { animation: pvKen 6s ease-in-out infinite alternate; }
.pv-zoom-in { animation: pvZoomIn 4s ease-in-out infinite alternate; }
.pv-zoom-out { animation: pvZoomOut 4s ease-in-out infinite alternate; }
.pv-fade-in { animation: pvFade 3s ease-in-out infinite alternate; }
.pv-fade-out { animation: pvFade 3s ease-in-out infinite alternate-reverse; }
.pv-pan { animation: pvPan 5s linear infinite alternate; }
.pv-none { animation: none; }
@keyframes pvKen { from { transform: scale(1.08); } to { transform: scale(1); } }
@keyframes pvZoomIn { from { transform: scale(1); } to { transform: scale(1.14); } }
@keyframes pvZoomOut { from { transform: scale(1.14); } to { transform: scale(1); } }
@keyframes pvFade { from { opacity: 0.25; } to { opacity: 1; } }
@keyframes pvPan { from { transform: scale(1.18) translateX(-4%); } to { transform: scale(1.18) translateX(4%); } }
</style>
