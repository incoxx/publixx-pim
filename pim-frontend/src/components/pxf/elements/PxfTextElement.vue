<script setup>
import { computed } from 'vue'
import { resolveBinding, applyNumberFormat, applyDateFormat, applyTextTransform, applyFormattingRules } from '../PxfDataResolver.js'

const props = defineProps({
  element: { type: Object, required: true },
  data: { type: Object, default: () => ({}) },
  config: { type: Object, default: () => ({}) },
})

const text = computed(() => {
  let val
  if (props.element.type === 'fixedText') {
    val = props.element.text || ''
  } else {
    val = resolveBinding(props.data, props.element.bind)
  }
  if (val == null) return ''
  val = applyNumberFormat(val, props.element.numberFormat)
  val = applyDateFormat(val, props.element.dateFormat)
  val = applyTextTransform(val, props.element.textTransform)
  const style = props.element.style || {}
  if (style.prefix) val = style.prefix + val
  if (style.suffix) val = val + style.suffix
  return String(val)
})

const dynamicStyles = computed(() => applyFormattingRules(props.data, props.element.formattingRules))

const style = computed(() => {
  const s = props.element.style || {}
  const d = dynamicStyles.value
  return {
    fontSize: (d.fontSize || s.fontSize || props.config.defaultFontSize || 12) + 'px',
    fontWeight: d.fontWeight || s.fontWeight || 400,
    fontFamily: s.fontFamily || props.config.defaultFontFamily || 'Arial, sans-serif',
    color: d.textColor || s.textColor || '#111111',
    textAlign: s.textAlign || 'left',
    display: 'flex',
    alignItems: s.verticalAlign === 'middle' ? 'center' : s.verticalAlign === 'bottom' ? 'flex-end' : 'flex-start',
    padding: (s.padding || 0) + 'px',
    backgroundColor: d.bg || s.bg || 'transparent',
    borderRadius: (s.radius || 0) + 'px',
    overflow: 'hidden',
    lineHeight: 1.4,
    width: '100%',
    height: '100%',
    boxSizing: 'border-box',
  }
})
</script>

<template>
  <div :style="style">
    <span>{{ text }}</span>
  </div>
</template>
