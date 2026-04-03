<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Bookmark, Plus, X } from 'lucide-vue-next'
import * as icons from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import userPreferencesApi from '@/api/userPreferences'

const router = useRouter()

// Kategorien mit Farben
const CATEGORIES = {
  daily: { label: 'Tagesgeschäft', color: '#3b82f6', bg: '#dbeafe' },
  publish: { label: 'Veröffentlichen', color: '#10b981', bg: '#d1fae5' },
  config: { label: 'Konfiguration', color: '#8b5cf6', bg: '#ede9fe' },
  connect: { label: 'Verbinden', color: '#f59e0b', bg: '#fef3c7' },
}

// Alle verfügbaren Menüpunkte mit Kategorie
const availableLinks = [
  { id: 'products', label: 'Produkte', to: '/products', icon: 'Package', cat: 'daily' },
  { id: 'hierarchies', label: 'Hierarchien', to: '/hierarchies', icon: 'GitBranch', cat: 'daily' },
  { id: 'watchlist', label: 'Merkliste', to: '/watchlist', icon: 'Star', cat: 'daily' },
  { id: 'media', label: 'Medien', to: '/media', icon: 'Image', cat: 'daily' },
  { id: 'search', label: 'Profisuche', to: '/search', icon: 'Search', cat: 'daily' },
  { id: 'quick-search', label: 'Schnellsuche', to: '/quick-search', icon: 'Zap', cat: 'daily' },
  { id: 'workflow', label: 'Workflow', to: '/workflow', icon: 'ClipboardList', cat: 'daily' },
  { id: 'calendar', label: 'Kalender', to: '/calendar', icon: 'CalendarDays', cat: 'daily' },
  { id: 'reports', label: 'Berichte', to: '/reports', icon: 'FileBarChart', cat: 'publish' },
  { id: 'imports', label: 'Import', to: '/imports', icon: 'Upload', cat: 'publish' },
  { id: 'exports', label: 'Export', to: '/exports', icon: 'Download', cat: 'publish' },
  { id: 'export-jobs', label: 'Export-Jobs', to: '/export-jobs', icon: 'Clock', cat: 'publish' },
  { id: 'pdf-templates', label: 'PDF-Vorlagen', to: '/pdf-templates', icon: 'FileText', cat: 'publish' },
  { id: 'catalog-templates', label: 'Katalog-Vorlagen', to: '/catalog-templates', icon: 'LayoutTemplate', cat: 'publish' },
  { id: 'portal-config', label: 'Portale', to: '/portal-config', icon: 'Globe', cat: 'publish' },
  { id: 'translation-jobs', label: 'Übersetzungen', to: '/translation-jobs', icon: 'Languages', cat: 'publish' },
  { id: 'teams', label: 'Teams', to: '/teams', icon: 'Users', cat: 'config' },
  { id: 'projects', label: 'Projekte', to: '/projects', icon: 'FolderTree', cat: 'config' },
  { id: 'manufacturers', label: 'Hersteller', to: '/manufacturers', icon: 'Factory', cat: 'config' },
  { id: 'product-types', label: 'Produkttypen', to: '/product-types', icon: 'Layers', cat: 'config' },
  { id: 'attributes', label: 'Attribute', to: '/attributes', icon: 'Sliders', cat: 'config' },
  { id: 'project-dashboard', label: 'Projekte', to: '/project-dashboard', icon: 'LayoutDashboard', cat: 'config' },
  { id: 'connectors-shopware', label: 'Shopware', to: '/connectors/shopware', icon: 'ShoppingCart', cat: 'connect' },
  { id: 'connectors-shopify', label: 'Shopify', to: '/connectors/shopify', icon: 'ShoppingBag', cat: 'connect' },
]

const selectedLinks = ref([])
const showPicker = ref(false)
const loading = ref(false)

function getIcon(iconName) {
  return icons[iconName] || icons.Link
}

function getCatColor(link) {
  const found = availableLinks.find(al => al.id === link.id)
  const cat = CATEGORIES[found?.cat || 'daily']
  return cat || CATEGORIES.daily
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await userPreferencesApi.get('quick_links')
    const payload = data.data || data
    if (payload?.links) {
      selectedLinks.value = payload.links
    }
  } catch { /* Keine Config vorhanden */ }
  loading.value = false
})

async function saveLinks() {
  try {
    await userPreferencesApi.update('quick_links', { links: selectedLinks.value })
  } catch { /* ignore */ }
}

function addLink(link) {
  if (!selectedLinks.value.find(l => l.id === link.id)) {
    selectedLinks.value.push({ id: link.id, label: link.label, to: link.to, icon: link.icon })
    saveLinks()
  }
}

function removeLink(linkId) {
  selectedLinks.value = selectedLinks.value.filter(l => l.id !== linkId)
  saveLinks()
}

const unselectedLinks = computed(() =>
  availableLinks.filter(al => !selectedLinks.value.find(sl => sl.id === al.id))
)

// Picker nach Kategorie gruppiert
const groupedUnselected = computed(() => {
  const groups = {}
  for (const link of unselectedLinks.value) {
    const cat = link.cat || 'daily'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(link)
  }
  return groups
})
</script>

<template>
  <DashboardWidgetWrapper title="Schnellzugriff" :icon="Bookmark">
    <div class="p-3">
      <!-- Icon Grid -->
      <div v-if="selectedLinks.length" class="grid grid-cols-4 gap-1">
        <button
          v-for="link in selectedLinks"
          :key="link.id"
          class="group relative flex flex-col items-center gap-1.5 p-2.5 rounded-xl transition-all hover:scale-105 hover:shadow-md"
          :style="{ '--link-color': getCatColor(link).color, '--link-bg': getCatColor(link).bg }"
          @click="router.push(link.to)"
        >
          <!-- Icon-Bubble -->
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center transition-colors"
            style="background: var(--link-bg)"
          >
            <component
              :is="getIcon(link.icon)"
              class="w-[18px] h-[18px]"
              style="color: var(--link-color)"
              :stroke-width="1.75"
            />
          </div>
          <!-- Label -->
          <span class="text-[10px] leading-tight text-center text-[var(--color-text-secondary)] group-hover:text-[var(--color-text-primary)] transition-colors line-clamp-1 w-full">
            {{ link.label }}
          </span>
          <!-- Entfernen (Hover) -->
          <button
            class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-[var(--color-surface)] border border-[var(--color-border)] flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-red-50 hover:border-red-200 transition-all"
            @click.stop="removeLink(link.id)"
            title="Entfernen"
          >
            <X class="w-2.5 h-2.5 text-[var(--color-text-tertiary)] hover:text-red-500" :stroke-width="2.5" />
          </button>
        </button>

        <!-- Hinzufügen-Button -->
        <button
          class="flex flex-col items-center gap-1.5 p-2.5 rounded-xl transition-all hover:scale-105 group"
          @click="showPicker = !showPicker"
        >
          <div class="w-9 h-9 rounded-xl border-2 border-dashed border-[var(--color-border)] group-hover:border-[var(--color-accent)] flex items-center justify-center transition-colors">
            <Plus class="w-4 h-4 text-[var(--color-text-tertiary)] group-hover:text-[var(--color-accent)]" :stroke-width="2" />
          </div>
          <span class="text-[10px] text-[var(--color-text-tertiary)] group-hover:text-[var(--color-accent)]">Mehr</span>
        </button>
      </div>

      <!-- Leer-Zustand -->
      <div v-else class="text-center py-6">
        <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-[var(--color-bg)] flex items-center justify-center">
          <Bookmark class="w-6 h-6 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        </div>
        <p class="text-xs text-[var(--color-text-tertiary)] mb-2">Keine Schnellzugriffe eingerichtet</p>
        <button
          class="text-xs text-[var(--color-accent)] hover:underline font-medium"
          @click="showPicker = true"
        >Menüpunkte auswählen</button>
      </div>

      <!-- Picker-Overlay (kategorisiert) -->
      <div v-if="showPicker" class="mt-3 border border-[var(--color-border)] rounded-xl bg-[var(--color-bg)] overflow-hidden">
        <div class="flex items-center justify-between px-3 py-2 border-b border-[var(--color-border)]">
          <p class="text-[10px] text-[var(--color-text-tertiary)] uppercase tracking-wider font-medium">Hinzufügen</p>
          <button class="p-0.5 rounded hover:bg-[var(--color-surface)]" @click="showPicker = false">
            <X class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          </button>
        </div>
        <div class="max-h-56 overflow-y-auto p-2 space-y-2">
          <div v-for="(links, catKey) in groupedUnselected" :key="catKey">
            <p class="text-[9px] font-medium uppercase tracking-wider px-1 mb-1" :style="{ color: CATEGORIES[catKey]?.color }">
              {{ CATEGORIES[catKey]?.label }}
            </p>
            <div class="grid grid-cols-3 gap-1">
              <button
                v-for="link in links"
                :key="link.id"
                class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg hover:bg-[var(--color-surface)] transition-colors text-left"
                @click="addLink(link)"
              >
                <div
                  class="w-5 h-5 rounded flex items-center justify-center shrink-0"
                  :style="{ background: CATEGORIES[catKey]?.bg }"
                >
                  <component :is="getIcon(link.icon)" class="w-3 h-3" :style="{ color: CATEGORIES[catKey]?.color }" :stroke-width="2" />
                </div>
                <span class="text-[10px] text-[var(--color-text-primary)] truncate">{{ link.label }}</span>
              </button>
            </div>
          </div>
          <p v-if="unselectedLinks.length === 0" class="text-center text-xs text-[var(--color-text-tertiary)] py-3">
            Alle Menüpunkte hinzugefügt
          </p>
        </div>
      </div>
    </div>
  </DashboardWidgetWrapper>
</template>
