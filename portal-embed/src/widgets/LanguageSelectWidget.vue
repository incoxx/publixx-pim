<script setup>
import { computed } from 'vue'
import { useStore } from '../store.js'

const { state, actions } = useStore()

const LANG_NAMES = {
  de: 'Deutsch', en: 'English', fr: 'Fran\u00e7ais', es: 'Espa\u00f1ol',
  it: 'Italiano', nl: 'Nederlands', pl: 'Polski', pt: 'Portugu\u00eas',
  ru: '\u0420\u0443\u0441\u0441\u043a\u0438\u0439', zh: '\u4e2d\u6587', ja: '\u65e5\u672c\u8a9e', ko: '\ud55c\uad6d\uc5b4',
  cs: '\u010ce\u0161tina', hu: 'Magyar', hr: 'Hrvatski', da: 'Dansk',
  sv: 'Svenska', no: 'Norsk', fi: 'Suomi', el: '\u0395\u03bb\u03bb\u03b7\u03bd\u03b9\u03ba\u03ac',
  tr: 'T\u00fcrk\u00e7e', ro: 'Rom\u00e2n\u0103', ar: '\u0627\u0644\u0639\u0631\u0628\u064a\u0629',
}

const filterStep = computed(() =>
  state.filterValues.find(f => f.widget === 'language-select')
)

const selectedValue = computed(() => {
  const key = filterStep.value?.key
  return key ? state.selections[key] : null
})

function select(value) {
  const key = filterStep.value?.key
  if (key) actions.setSelection(key, value)
}
</script>

<template>
  <div class="pe-lang" v-if="filterStep">
    <h3 class="pe-lang__title">{{ filterStep.label }}</h3>
    <div class="pe-lang__list">
      <button
        v-for="item in filterStep.values"
        :key="item.value"
        class="pe-lang__badge"
        :class="{ 'pe-lang__badge--active': selectedValue === item.value }"
        @click="select(item.value)"
      >
        {{ LANG_NAMES[item.value] || item.label }}
      </button>
    </div>
  </div>
</template>
