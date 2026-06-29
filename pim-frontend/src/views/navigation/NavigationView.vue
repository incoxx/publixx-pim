<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import navigationApi from '@/api/navigation'
import PimTree from '@/components/shared/PimTree.vue'
import NavigationNodeFormPanel from '@/components/panels/NavigationNodeFormPanel.vue'
import NavigationThemePanel from '@/components/panels/NavigationThemePanel.vue'
import { Plus, Network, Globe, Palette, Lock, Globe2 } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const emptyset = new Set()

// aktuell gewählte Navigation
const currentNav = computed(() => navStore.navigations.find((n) => n.id === navStore.currentId))
const currentNavKey = computed(() => currentNav.value?.technical_name)

// Zugriff: öffentlich vs. nur mit Login
async function setAccessMode(event) {
  const mode = event.target.value
  await navigationApi.update(navStore.currentId, { access_mode: mode })
  await navStore.fetchNavigations()
}

function openPreview() {
  router.push({ name: 'website-preview', query: currentNavKey.value ? { nav: currentNavKey.value } : {} })
}

function openTheme() {
  const nav = navStore.navigations.find((n) => n.id === navStore.currentId)
  if (nav) authStore.openPanel(NavigationThemePanel, { navigation: nav }, '420px')
}

// PimTree rendert node.name_de — wir spiegeln label_de dorthin und hängen
// ein Ziel-Kürzel an, ohne die Originalfelder zu verlieren.
const targetBadge = {
  folder: '📁', content_page: '📄', product_category: '🗂️', product: '📦', external_url: '🔗',
}
function decorate(nodes) {
  return (nodes || []).map((n) => ({
    ...n,
    name_de: `${targetBadge[n.target_type] || ''} ${n.label_de}`.trim(),
    children: decorate(n.children),
  }))
}
const displayTree = computed(() => decorate(navStore.tree))

async function switchNavigation(id) {
  await navStore.fetchTree(id)
}

function newNavigation() {
  const name = prompt('Name der neuen Navigation (z. B. Footer):')
  if (!name) return
  const technical = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
  navStore.createNavigation({ technical_name: technical, name_de: name })
}

function addRoot() {
  authStore.openPanel(NavigationNodeFormPanel, { parentNodeId: null }, '460px')
}

function addChild(parentNode) {
  authStore.openPanel(NavigationNodeFormPanel, { parentNodeId: parentNode.id }, '460px')
}

function editNode(node) {
  navStore.selectNode(node)
  // Originalknoten aus dem Baum (ohne Deko) anhand der ID finden
  authStore.openPanel(NavigationNodeFormPanel, { node: findNode(navStore.tree, node.id) }, '460px')
}

function findNode(nodes, id) {
  for (const n of nodes || []) {
    if (n.id === id) return n
    const hit = findNode(n.children, id)
    if (hit) return hit
  }
  return null
}

async function removeNode(node) {
  if (!confirm(`Knoten "${node.label_de}" und alle Unterknoten löschen?`)) return
  await navStore.deleteNode(node.id)
}

// PimTree move: (draggedId, targetId) → dragged wird Kind von target
async function onMove(draggedId, targetId) {
  await navStore.moveNode(draggedId, { parent_node_id: targetId, sort_order: 0 })
}

onMounted(async () => {
  const navs = await navStore.fetchNavigations()
  const primary = navs.find((n) => n.is_primary) || navs[0]
  if (primary) await navStore.fetchTree(primary.id)
})
</script>

<template>
  <div class="space-y-4" data-testid="navigation-view">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <Network class="w-5 h-5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Navigation / Sitemap</h2>
        <select
          v-if="navStore.navigations.length"
          class="pim-input text-xs"
          :value="navStore.currentId"
          @change="switchNavigation($event.target.value)"
        >
          <option v-for="n in navStore.navigations" :key="n.id" :value="n.id">
            {{ n.name_de }}{{ n.is_primary ? ' ★' : '' }}
          </option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <!-- Zugriff: öffentlich vs. nur mit Login -->
        <div v-if="currentNav" class="flex items-center gap-1 rounded-md border border-[var(--color-border)] px-2 py-1" :title="currentNav.access_mode === 'login' ? 'Nur mit Login abrufbar' : 'Öffentlich abrufbar'">
          <component :is="currentNav.access_mode === 'login' ? Lock : Globe2" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <select class="bg-transparent text-xs outline-none" :value="currentNav.access_mode || 'public'" @change="setAccessMode">
            <option value="public">Öffentlich</option>
            <option value="login">Nur mit Login</option>
          </select>
        </div>
        <button class="pim-btn pim-btn-secondary text-xs" :disabled="!navStore.currentId" @click="openTheme">
          <Palette class="w-3.5 h-3.5" :stroke-width="2" /> Design
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" :disabled="!navStore.currentId" @click="openPreview">
          <Globe class="w-3.5 h-3.5" :stroke-width="2" /> Vorschau
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="newNavigation">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Navigation
        </button>
        <button class="pim-btn pim-btn-primary text-xs" :disabled="!navStore.currentId" @click="addRoot">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Knoten
        </button>
      </div>
    </div>

    <!-- Baum -->
    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-2 min-h-64">
      <div v-if="navStore.loading" class="space-y-2 p-2">
        <div v-for="i in 5" :key="i" class="pim-skeleton h-7 rounded" />
      </div>
      <PimTree
        v-else-if="displayTree.length"
        :nodes="displayTree"
        :selected-id="navStore.selectedNode?.id"
        :expanded-ids="navStore.expandedIds"
        :highlighted-ids="emptyset"
        :draggable="true"
        @select="editNode"
        @toggle="navStore.toggleExpand"
        @create="addChild"
        @delete="removeNode"
        @move="onMove"
      />
      <p v-else class="text-xs text-[var(--color-text-tertiary)] text-center py-8">
        Noch keine Knoten. Lege oben mit „Knoten" den ersten Menüpunkt an.
      </p>
    </div>

    <p class="text-[11px] text-[var(--color-text-tertiary)]">
      Ziehe Knoten, um sie umzuhängen. Ein Klick öffnet den Knoten zum Bearbeiten.
      Kategorie-Mount-Points expandieren den Produktbaum automatisch.
    </p>
  </div>
</template>
