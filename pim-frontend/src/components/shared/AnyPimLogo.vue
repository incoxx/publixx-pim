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
    <!-- Icon: Abstract data-hub / connection symbol -->
    <svg
      :width="sizes[size].icon"
      :height="sizes[size].icon"
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <!-- Outer shield/badge shape -->
      <path
        d="M24 2L6 10v14c0 11.1 7.7 21.4 18 24 10.3-2.6 18-12.9 18-24V10L24 2z"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linejoin="round"
        class="text-[var(--color-primary)]"
      />
      <!-- Central hub node -->
      <circle cx="24" cy="20" r="4" fill="currentColor" class="text-[var(--color-primary)]" />
      <!-- Top connector -->
      <line x1="24" y1="10" x2="24" y2="16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-[var(--color-primary)]" />
      <!-- Bottom connector -->
      <line x1="24" y1="24" x2="24" y2="34" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-[var(--color-primary)]" />
      <!-- Left connector -->
      <line x1="14" y1="26" x2="20.5" y2="21.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-[var(--color-primary)]" />
      <!-- Right connector -->
      <line x1="34" y1="26" x2="27.5" y2="21.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="text-[var(--color-primary)]" />
      <!-- Bottom-left node -->
      <circle cx="12" cy="28" r="3" fill="currentColor" class="text-[var(--color-primary)]" />
      <!-- Bottom-right node -->
      <circle cx="36" cy="28" r="3" fill="currentColor" class="text-[var(--color-primary)]" />
      <!-- Bottom node -->
      <circle cx="24" cy="36" r="3" fill="currentColor" class="text-[var(--color-primary)]" />
      <!-- Top node -->
      <circle cx="24" cy="9" r="2.5" fill="currentColor" class="text-[var(--color-primary)]" />
    </svg>

    <!-- Text block -->
    <div v-if="showText" class="flex flex-col leading-none">
      <span
        class="font-bold tracking-tight text-[var(--color-primary)]"
        :style="{ fontSize: sizes[size].text1 + 'px', lineHeight: 1.1 }"
      >anyPIM</span>
      <span
        class="text-[var(--color-text-tertiary)] font-medium tracking-wide"
        :style="{ fontSize: sizes[size].text2 + 'px', lineHeight: 1.3 }"
      >by incoxx</span>
    </div>
  </div>
</template>
