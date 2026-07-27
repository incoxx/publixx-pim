<script setup>
defineProps({
  showText: { type: Boolean, default: true },
  size: { type: String, default: 'md', validator: v => ['sm', 'md', 'lg'].includes(v) },
})

const sizes = {
  sm: { icon: 28, text1: 15, text2: 9, gap: 8 },
  md: { icon: 36, text1: 19, text2: 11, gap: 10 },
  lg: { icon: 48, text1: 26, text2: 14, gap: 14 },
}
</script>

<template>
  <div class="inline-flex items-center" :style="{ gap: sizes[size].gap + 'px' }">
    <!-- Icon: anyPIM-Mark (Quelle: public/anypim-mark.svg) -->
    <svg
      :width="sizes[size].icon"
      :height="sizes[size].icon"
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      aria-label="anyPIM"
    >
      <defs>
        <linearGradient :id="'anypim-grad-' + size" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#6366F1" />
          <stop offset="50%" stop-color="#8B5CF6" />
          <stop offset="100%" stop-color="#06B6D4" />
        </linearGradient>
      </defs>
      <!-- Hexagon-Umriss -->
      <path
        d="M24 4 41.32 14v20L24 44 6.68 34V14L24 4z"
        fill="none"
        :stroke="`url(#anypim-grad-${size})`"
        stroke-width="3"
        stroke-linejoin="round"
      />
      <!-- Verbindungen zu den Knoten -->
      <g fill="none" :stroke="`url(#anypim-grad-${size})`" stroke-width="2.2" stroke-linecap="round">
        <path d="M24 17.4 24 11.4" />
        <path d="M29.72 20.7 34.91 17.7" />
        <path d="M29.72 27.3 34.91 30.3" />
        <path d="M24 30.6 24 36.6" />
        <path d="M18.28 27.3 13.09 30.3" />
        <path d="M18.28 20.7 13.09 17.7" />
      </g>
      <!-- Zentrale Nabe + Knoten -->
      <g :fill="`url(#anypim-grad-${size})`">
        <circle cx="24" cy="24" r="4.4" />
        <circle cx="24" cy="8.4" r="2.5" />
        <circle cx="37.51" cy="16.2" r="2.5" />
        <circle cx="37.51" cy="31.8" r="2.5" />
        <circle cx="24" cy="39.6" r="2.5" />
        <circle cx="10.49" cy="31.8" r="2.5" />
        <circle cx="10.49" cy="16.2" r="2.5" />
      </g>
    </svg>

    <!-- Text block -->
    <div v-if="showText" class="flex flex-col">
      <span
        class="tracking-tight"
        :style="{ fontSize: sizes[size].text1 + 'px', lineHeight: 1.3 }"
      ><span style="color: #2563eb; font-weight: 500">any</span><span style="color: var(--color-text-primary); font-weight: 800">PIM</span></span>
      <span
        class="text-[var(--color-text-tertiary)] font-medium tracking-wide"
        :style="{ fontSize: sizes[size].text2 + 'px', lineHeight: 1.3 }"
      >by incoxx</span>
    </div>
  </div>
</template>
