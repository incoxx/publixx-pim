<script setup>
import { languageName, languageCountryCode } from './languageConstants'

defineProps({
  languages: { type: Array, default: () => [] },
  activeLanguage: { type: String, default: null },
  compact: { type: Boolean, default: false },
})
defineEmits(['select'])

const langName = languageName
const langFlag = languageCountryCode
</script>

<template>
  <div class="lb-root" :class="{ 'lb-root--compact': compact }">
    <button
      v-for="lang in languages"
      :key="lang"
      class="lb-badge"
      :class="{ 'lb-badge--active': activeLanguage === lang }"
      @click.stop="$emit('select', lang)"
    >
      <span class="lb-badge__flag">{{ langFlag(lang) }}</span>
      <span class="lb-badge__name">{{ langName(lang) }}</span>
    </button>
  </div>
</template>

<style scoped>
.lb-root {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.lb-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border: 1px solid var(--dp-border, #bae6fd);
  border-radius: 6px;
  background: #fff;
  font-size: 0.8125rem;
  cursor: pointer;
  transition: all 0.15s;
  color: var(--dp-dark, #0c4a6e);
}
.lb-badge:hover {
  border-color: var(--dp-primary, #0891b2);
  background: var(--dp-surface, #f0f9ff);
}
.lb-badge--active {
  border-color: var(--dp-primary, #0891b2);
  background: var(--dp-primary, #0891b2);
  color: #fff;
}
.lb-badge__flag {
  font-size: 0.6875rem;
  font-weight: 700;
  opacity: 0.6;
}
.lb-badge--active .lb-badge__flag {
  opacity: 1;
}
.lb-root--compact .lb-badge {
  padding: 2px 6px;
  font-size: 0.6875rem;
}
</style>
