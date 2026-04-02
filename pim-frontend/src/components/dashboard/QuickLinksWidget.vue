<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Bookmark, Plus, X, GripVertical } from 'lucide-vue-next'
import * as icons from 'lucide-vue-next'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import userPreferencesApi from '@/api/userPreferences'

const router = useRouter()

// Alle verfügbaren Menüpunkte
const availableLinks = [
  { id: 'products', label: 'Produkte', to: '/products', icon: 'Package' },
  { id: 'hierarchies', label: 'Hierarchien', to: '/hierarchies', icon: 'GitBranch' },
  { id: 'watchlist', label: 'Merkliste', to: '/watchlist', icon: 'Star' },
  { id: 'media', label: 'Medien', to: '/media', icon: 'Image' },
  { id: 'search', label: 'Suche', to: '/search', icon: 'Search' },
  { id: 'quick-search', label: 'Schnellsuche', to: '/quick-search', icon: 'Zap' },
  { id: 'workflow', label: 'Workflow', to: '/workflow', icon: 'ClipboardList' },
  { id: 'reports', label: 'Berichte', to: '/reports', icon: 'FileBarChart' },
  { id: 'imports', label: 'Importieren', to: '/imports', icon: 'Upload' },
  { id: 'exports', label: 'Exportieren', to: '/exports', icon: 'Download' },
  { id: 'export-jobs', label: 'Export-Jobs', to: '/export-jobs', icon: 'Clock' },
  { id: 'pdf-templates', label: 'PDF-Vorlagen', to: '/pdf-templates', icon: 'FileText' },
  { id: 'catalog-templates', label: 'Katalog-Vorlagen', to: '/catalog-templates', icon: 'LayoutTemplate' },
  { id: 'portal-config', label: 'Portale', to: '/portal-config', icon: 'Globe' },
  { id: 'translation-jobs', label: 'Übersetzungsjobs', to: '/translation-jobs', icon: 'Languages' },
  { id: 'teams', label: 'Teams', to: '/teams', icon: 'Users' },
  { id: 'projects', label: 'Projekte', to: '/projects', icon: 'FolderTree' },
  { id: 'manufacturers', label: 'Hersteller', to: '/manufacturers', icon: 'Factory' },
  { id: 'product-types', label: 'Produkttypen', to: '/product-types', icon: 'Layers' },
  { id: 'attributes', label: 'Attribute', to: '/attributes', icon: 'Sliders' },
  { id: 'calendar', label: 'Planungskalender', to: '/calendar', icon: 'CalendarDays' },
  { id: 'project-dashboard', label: 'Projekt-Dashboard', to: '/project-dashboard', icon: 'LayoutDashboard' },
  { id: 'connectors-shopware', label: 'Shopware', to: '/connectors/shopware', icon: 'Package' },
  { id: 'connectors-shopify', label: 'Shopify', to: '/connectors/shopify', icon: 'ShoppingBag' },
]

const selectedLinks = ref([])
const showPicker = ref(false)
const loading = ref(false)

function getIcon(iconName) {
  return icons[iconName] || icons.Link
}

// Favoriten laden
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
</script>

<template>
  <DashboardWidgetWrapper title="Schnellzugriff" :icon="Bookmark">
    <div class="p-4">
      <!-- Ausgewählte Links -->
      <div v-if="selectedLinks.length" class="flex flex-wrap gap-2">
        <button
          v-for="link in selectedLinks"
          :key="link.id"
          class="group flex items-center gap-1.5 px-3 py-2 rounded-lg border border-[var(--color-border)] hover:border-[var(--color-accent)] hover:bg-[var(--color-accent)]/5 transition-colors text-xs"
          @click="router.push(link.to)"
        >
          <component :is="getIcon(link.icon)" class="w-3.5 h-3.5 text-[var(--color-accent)]" :stroke-width="2" />
          <span class="text-[var(--color-text-primary)]">{{ link.label }}</span>
          <button
            class="ml-0.5 p-0.5 rounded opacity-0 group-hover:opacity-100 hover:bg-[var(--color-bg)] transition-all"
            @click.stop="removeLink(link.id)"
            title="Entfernen"
          >
            <X class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          </button>
        </button>

        <!-- Hinzufügen-Button (inline) -->
        <button
          class="flex items-center gap-1 px-3 py-2 rounded-lg border border-dashed border-[var(--color-border)] hover:border-[var(--color-accent)] transition-colors text-xs text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)]"
          @click="showPicker = !showPicker"
        >
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        </button>
      </div>

      <!-- Leer-Zustand -->
      <div v-else class="text-center py-4">
        <p class="text-xs text-[var(--color-text-tertiary)] mb-2">Noch keine Favoriten</p>
        <button
          class="text-xs text-[var(--color-accent)] hover:underline"
          @click="showPicker = true"
        >Menüpunkte hinzufügen</button>
      </div>

      <!-- Picker-Dropdown -->
      <div v-if="showPicker" class="mt-3 border border-[var(--color-border)] rounded-lg bg-[var(--color-bg)] p-2 max-h-48 overflow-y-auto">
        <p class="text-[10px] text-[var(--color-text-tertiary)] uppercase tracking-wider font-medium px-2 pb-1.5 border-b border-[var(--color-border)] mb-1.5">
          Menüpunkt hinzufügen
        </p>
        <div v-if="unselectedLinks.length === 0" class="text-center text-xs text-[var(--color-text-tertiary)] py-2">
          Alle Menüpunkte wurden hinzugefügt
        </div>
        <button
          v-for="link in unselectedLinks"
          :key="link.id"
          class="flex items-center gap-2 w-full px-2 py-1.5 rounded hover:bg-[var(--color-surface)] transition-colors text-left"
          @click="addLink(link); showPicker = false"
        >
          <component :is="getIcon(link.icon)" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <span class="text-xs text-[var(--color-text-primary)]">{{ link.label }}</span>
        </button>
      </div>
    </div>
  </DashboardWidgetWrapper>
</template>
