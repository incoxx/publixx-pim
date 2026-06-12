import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useHierarchyStore } from '../hierarchies'

// Mock the hierarchies API
vi.mock('@/api/hierarchies', () => ({
  default: {
    list: vi.fn(),
    getTree: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
    createNode: vi.fn(),
    updateNode: vi.fn(),
    deleteNode: vi.fn(),
    moveNode: vi.fn(),
    duplicateNode: vi.fn(),
  },
}))

import hierarchiesApi from '@/api/hierarchies'

describe('Hierarchy Store', () => {
  let store

  beforeEach(() => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    store = useHierarchyStore()
  })

  describe('fetchHierarchies', () => {
    it('loads hierarchies from wrapped response', async () => {
      hierarchiesApi.list.mockResolvedValue({ data: { data: [{ id: 'h1' }, { id: 'h2' }] } })

      await store.fetchHierarchies()

      expect(store.hierarchies).toHaveLength(2)
      expect(store.loading).toBe(false)
    })

    it('accepts unwrapped array response', async () => {
      hierarchiesApi.list.mockResolvedValue({ data: [{ id: 'h1' }] })

      await store.fetchHierarchies()

      expect(store.hierarchies).toEqual([{ id: 'h1' }])
    })

    it('sets error on failure', async () => {
      hierarchiesApi.list.mockRejectedValue({ response: { data: { title: 'Verboten' } } })

      await store.fetchHierarchies()

      expect(store.error).toBe('Verboten')
    })
  })

  describe('fetchTree', () => {
    it('loads tree and resolves currentHierarchy from list', async () => {
      store.hierarchies = [
        { id: 'h1', hierarchy_type: 'master' },
        { id: 'h2', hierarchy_type: 'output' },
      ]
      hierarchiesApi.getTree.mockResolvedValue({ data: { data: [{ id: 'n1', children: [] }] } })

      await store.fetchTree('h1')

      expect(store.tree).toEqual([{ id: 'n1', children: [] }])
      expect(store.currentHierarchy.id).toBe('h1')
    })

    it('sets currentHierarchy to null when id unknown', async () => {
      hierarchiesApi.getTree.mockResolvedValue({ data: { data: [] } })

      await store.fetchTree('unknown')

      expect(store.currentHierarchy).toBeNull()
    })

    it('includes HTTP status in error message', async () => {
      hierarchiesApi.getTree.mockRejectedValue({
        response: { status: 403, data: { title: 'Kein Zugriff' } },
      })

      await store.fetchTree('h1')

      expect(store.error).toBe('Kein Zugriff (Status: 403)')
    })

    it('falls back to plain error message without response', async () => {
      hierarchiesApi.getTree.mockRejectedValue(new Error('Netzwerkfehler'))

      await store.fetchTree('h1')

      expect(store.error).toBe('Netzwerkfehler (Status: unknown)')
    })
  })

  describe('isMasterHierarchy', () => {
    it('is true for master type', () => {
      store.currentHierarchy = { id: 'h1', hierarchy_type: 'master' }
      expect(store.isMasterHierarchy).toBe(true)
    })

    it('is false for output type or no hierarchy', () => {
      store.currentHierarchy = { id: 'h2', hierarchy_type: 'output' }
      expect(store.isMasterHierarchy).toBe(false)

      store.currentHierarchy = null
      expect(store.isMasterHierarchy).toBe(false)
    })
  })

  describe('node CRUD', () => {
    it('createNode returns created node', async () => {
      hierarchiesApi.createNode.mockResolvedValue({ data: { data: { id: 'n-new' } } })

      const node = await store.createNode('h1', { name: 'Neu' })

      expect(node).toEqual({ id: 'n-new' })
      expect(hierarchiesApi.createNode).toHaveBeenCalledWith('h1', { name: 'Neu' })
    })

    it('moveNode sends parent and sort order', async () => {
      hierarchiesApi.moveNode.mockResolvedValue({})

      await store.moveNode('n1', 'parent-2', 5)

      expect(hierarchiesApi.moveNode).toHaveBeenCalledWith('n1', {
        parent_node_id: 'parent-2',
        sort_order: 5,
      })
    })

    it('deleteNode passes force flag', async () => {
      hierarchiesApi.deleteNode.mockResolvedValue({})

      await store.deleteNode('n1', { force: true })

      expect(hierarchiesApi.deleteNode).toHaveBeenCalledWith('n1', { force: true })
    })
  })

  describe('tree UI state', () => {
    it('selectNode sets the selected node', () => {
      const node = { id: 'n1' }
      store.selectNode(node)
      expect(store.selectedNode).toEqual(node)
    })

    it('toggleExpanded expands and collapses nodes', () => {
      expect(store.isExpanded('n1')).toBe(false)

      store.toggleExpanded('n1')
      expect(store.isExpanded('n1')).toBe(true)

      store.toggleExpanded('n1')
      expect(store.isExpanded('n1')).toBe(false)
    })

    it('tracks multiple expanded nodes independently', () => {
      store.toggleExpanded('n1')
      store.toggleExpanded('n2')
      store.toggleExpanded('n1')

      expect(store.isExpanded('n1')).toBe(false)
      expect(store.isExpanded('n2')).toBe(true)
    })
  })
})
