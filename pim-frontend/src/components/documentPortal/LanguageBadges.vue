<script setup>
defineProps({
  languages: { type: Array, default: () => [] },
  activeLanguage: { type: String, default: null },
  compact: { type: Boolean, default: false },
})
defineEmits(['select'])

const LANGUAGE_NAMES = {
  de: 'Deutsch', en: 'English', fr: 'Fran\u00e7ais', es: 'Espa\u00f1ol',
  it: 'Italiano', nl: 'Nederlands', pl: 'Polski', pt: 'Portugu\u00eas',
  ru: '\u0420\u0443\u0441\u0441\u043a\u0438\u0439', zh: '\u4e2d\u6587', ja: '\u65e5\u672c\u8a9e', ko: '\ud55c\uad6d\uc5b4',
  cs: '\u010ce\u0161tina', hu: 'Magyar', hr: 'Hrvatski', da: 'Dansk',
  sv: 'Svenska', no: 'Norsk', fi: 'Suomi', el: '\u0395\u03bb\u03bb\u03b7\u03bd\u03b9\u03ba\u03ac',
  tr: 'T\u00fcrk\u00e7e', ro: 'Rom\u00e2n\u0103', ar: '\u0627\u0644\u0639\u0631\u0628\u064a\u0629',
  mul: 'Mehrsprachig', lv: 'Latvie\u0161u', lt: 'Lietuvi\u0173',
}

function langName(code) {
  return LANGUAGE_NAMES[code] || code.toUpperCase()
}

function langFlag(code) {
  const map = {
    de: 'DE', en: 'GB', fr: 'FR', es: 'ES', it: 'IT', nl: 'NL', pl: 'PL',
    pt: 'PT', ru: 'RU', zh: 'CN', ja: 'JP', ko: 'KR', cs: 'CZ', hu: 'HU',
    hr: 'HR', da: 'DK', sv: 'SE', no: 'NO', fi: 'FI', el: 'GR', tr: 'TR',
    ro: 'RO', ar: 'SA', lv: 'LV', lt: 'LT',
  }
  return map[code] || code.toUpperCase()
}
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
