import { describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { useInheritance } from '../useInheritance'

describe('useInheritance', () => {
  const values = [
    {
      attribute_id: 'a1',
      value: 'Vererbt',
      inherited_from: 'parent-1',
      inherited_from_label: 'Elternprodukt',
      is_overridden: false,
    },
    {
      attribute_id: 'a2',
      value: 'Überschrieben',
      inherited_from: 'parent-1',
      is_overridden: true,
    },
    {
      attribute_id: 'a3',
      value: 'Eigen',
    },
  ]

  it('builds the inheritance map only from inherited values', () => {
    const { inheritanceMap } = useInheritance(ref(values))

    expect(Object.keys(inheritanceMap.value)).toEqual(['a1', 'a2'])
    expect(inheritanceMap.value.a1).toEqual({
      source: 'parent-1',
      sourceLabel: 'Elternprodukt',
      value: 'Vererbt',
      isOverridden: false,
    })
  })

  it('isInherited distinguishes inherited from own values', () => {
    const { isInherited } = useInheritance(ref(values))

    expect(isInherited('a1')).toBe(true)
    expect(isInherited('a3')).toBe(false)
    expect(isInherited('unknown')).toBe(false)
  })

  it('isOverridden reflects the override flag', () => {
    const { isOverridden } = useInheritance(ref(values))

    expect(isOverridden('a1')).toBe(false)
    expect(isOverridden('a2')).toBe(true)
    expect(isOverridden('a3')).toBe(false)
  })

  it('getInheritanceSource falls back to the source id without label', () => {
    const { getInheritanceSource } = useInheritance(ref(values))

    expect(getInheritanceSource('a1')).toBe('Elternprodukt')
    expect(getInheritanceSource('a2')).toBe('parent-1')
    expect(getInheritanceSource('a3')).toBeNull()
  })

  it('reacts to changes in the attribute values', () => {
    const vals = ref([])
    const { isInherited } = useInheritance(vals)

    expect(isInherited('a1')).toBe(false)

    vals.value = [{ attribute_id: 'a1', value: 'x', inherited_from: 'p1' }]
    expect(isInherited('a1')).toBe(true)
  })

  it('works with the default empty ref', () => {
    const { inheritanceMap, isInherited } = useInheritance()

    expect(inheritanceMap.value).toEqual({})
    expect(isInherited('a1')).toBe(false)
  })
})
