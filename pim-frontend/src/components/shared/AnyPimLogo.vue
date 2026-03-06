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
    <!-- Icon: Abstract data-hub with hexagonal shape -->
    <svg
      :width="sizes[size].icon"
      :height="sizes[size].icon"
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <linearGradient :id="'pim-grad-' + size" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#6366f1" />
          <stop offset="50%" stop-color="#8b5cf6" />
          <stop offset="100%" stop-color="#06b6d4" />
        </linearGradient>
      </defs>
      <!-- Rounded hexagon outline -->
      <path
        d="M24 4L40 13v22L24 44L8 35V13L24 4z"
        fill="none"
        :stroke="`url(#pim-grad-${size})`"
        stroke-width="2.5"
        stroke-linejoin="round"
      />
      <!-- Central hub -->
      <circle cx="24" cy="24" r="4.5" :fill="`url(#pim-grad-${size})`" />
      <!-- Top-right connector + node -->
      <line x1="27" y1="20.5" x2="34" y2="15" :stroke="`url(#pim-grad-${size})`" stroke-width="2" stroke-linecap="round" />
      <circle cx="35" cy="14" r="2.5" :fill="`url(#pim-grad-${size})`" />
      <!-- Top-left connector + node -->
      <line x1="21" y1="20.5" x2="14" y2="15" :stroke="`url(#pim-grad-${size})`" stroke-width="2" stroke-linecap="round" />
      <circle cx="13" cy="14" r="2.5" :fill="`url(#pim-grad-${size})`" />
      <!-- Bottom connector + node -->
      <line x1="24" y1="28.5" x2="24" y2="36" :stroke="`url(#pim-grad-${size})`" stroke-width="2" stroke-linecap="round" />
      <circle cx="24" cy="37.5" r="2.5" :fill="`url(#pim-grad-${size})`" />
      <!-- Left connector + node -->
      <line x1="19.5" y1="24" x2="12" y2="27" :stroke="`url(#pim-grad-${size})`" stroke-width="2" stroke-linecap="round" />
      <circle cx="11" cy="27.5" r="2" :fill="`url(#pim-grad-${size})`" />
      <!-- Right connector + node -->
      <line x1="28.5" y1="24" x2="36" y2="27" :stroke="`url(#pim-grad-${size})`" stroke-width="2" stroke-linecap="round" />
      <circle cx="37" cy="27.5" r="2" :fill="`url(#pim-grad-${size})`" />
    </svg>

    <!-- Text block -->
    <div v-if="showText" class="flex flex-col leading-none">
      <span
        class="font-bold tracking-tight"
        :style="{ fontSize: sizes[size].text1 + 'px', lineHeight: 1.1, background: 'linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }"
      >anyPIM</span>
      <span
        class="text-[var(--color-text-tertiary)] font-medium tracking-wide"
        :style="{ fontSize: sizes[size].text2 + 'px', lineHeight: 1.3 }"
      >by incoxx</span>
    </div>
  </div>
</template>
