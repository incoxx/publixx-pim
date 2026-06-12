import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useToastStore } from '../toast'

describe('Toast Store', () => {
  let store

  beforeEach(() => {
    vi.useFakeTimers()
    setActivePinia(createPinia())
    store = useToastStore()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows a toast with default type success', () => {
    store.showToast('Gespeichert')

    expect(store.messages).toHaveLength(1)
    expect(store.messages[0].text).toBe('Gespeichert')
    expect(store.messages[0].type).toBe('success')
  })

  it('removes the toast after the default duration (3s)', () => {
    store.showToast('Gespeichert')

    vi.advanceTimersByTime(2999)
    expect(store.messages).toHaveLength(1)

    vi.advanceTimersByTime(1)
    expect(store.messages).toHaveLength(0)
  })

  it('honors a custom duration', () => {
    store.showToast('Fehler', 'error', 5000)

    vi.advanceTimersByTime(3000)
    expect(store.messages).toHaveLength(1)

    vi.advanceTimersByTime(2000)
    expect(store.messages).toHaveLength(0)
  })

  it('assigns unique ids and removes only the expired toast', () => {
    store.showToast('Erster', 'info', 1000)
    vi.advanceTimersByTime(500)
    store.showToast('Zweiter', 'info', 1000)

    expect(store.messages.map(m => m.id)).toEqual([1, 2])

    vi.advanceTimersByTime(500)
    expect(store.messages).toHaveLength(1)
    expect(store.messages[0].text).toBe('Zweiter')
  })
})
