import { ref, computed } from 'vue'

export function useInheritance(attributeValues = ref([])) {
  const inheritanceMap = computed(() => {
    const map = {}
    for (const val of attributeValues.value) {
      if (val.inherited_from) {
        map[val.attribute_id] = {
          source: val.inherited_from,
          sourceLabel: val.inherited_from_label || val.inherited_from,
          value: val.value,
          isOverridden: val.is_overridden || false,
        }
      }
    }
    return map
  })

  function isInherited(attributeId) {
    return !!inheritanceMap.value[attributeId]
  }

  function isOverridden(attributeId) {
    return inheritanceMap.value[attributeId]?.isOverridden || false
  }

  function getInheritanceSource(attributeId) {
    return inheritanceMap.value[attributeId]?.sourceLabel || null
  }

  return { inheritanceMap, isInherited, isOverridden, getInheritanceSource }
}
