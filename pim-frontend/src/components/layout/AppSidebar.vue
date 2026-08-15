<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import { useConnectorsStore } from '@/stores/connectors'
import {
  Search, Package, GitBranch, Sliders, Database, Layers, FolderTree,
  Upload, Download, Image, Images, Tags, DollarSign, Users, Settings, Shield, ShieldAlert,
  HelpCircle, PanelLeftClose, PanelLeft, Star, LayoutGrid, Ruler,
  FileJson, FileCode, PlayCircle, FileBarChart, FileText, BookOpen, Link2, Zap, Languages, LayoutTemplate,
  ChevronDown, ChevronRight, ChevronsDownUp, GripVertical, Factory, CalendarDays, ScrollText, Globe,
  LayoutDashboard, ClipboardList, Code, ExternalLink, Plug, FlaskConical, ArrowRightLeft,
  FileSpreadsheet, FileStack, Network, Boxes,
  ShoppingBag, ShoppingCart, MapPin, CreditCard,
  ArrowDownUp,
  Key,
  Bug,
  Bot,
  Clapperboard,
  Gauge,
  Wand2,
  Terminal,
  Folders,
} from 'lucide-vue-next'
import AnyPimLogo from '@/components/shared/AnyPimLogo.vue'
import { useAppearanceStore, SECTION_ICON_COLORS } from '@/stores/appearance'

const appearanceStore = useAppearanceStore()

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const connectorsStore = useConnectorsStore()

// Farbige Icon-Farbe für eine Sektion (nur wenn aktiviert)
function sectionIconColor(sectionKey) {
  if (!appearanceStore.sidebarColoredIcons) return null
  return SECTION_ICON_COLORS[sectionKey] || null
}

// Module is accessible if licensed OR if any related plugin has API keys configured
function isModuleAccessible(module) {
  if (!module) return true
  if (!licenseStore.loaded) return false // hide until license is resolved
  if (licenseStore.isModuleActive(module)) return true
  // 'connectors' module: show if any connector has keys configured
  if (module === 'connectors' && connectorsStore.configuredPluginsLoaded) {
    return connectorsStore.configuredPlugins.length > 0
  }
  return false
}

// ─── Menu sections ──────────────────────────────────────
const sections = computed(() => {
  const all = [
    {
      key: 'daily',
      label: t('Daily Business'),
      items: [
        { icon: LayoutDashboard, label: () => t('Dashboard'), to: '/dashboard', permission: 'dashboard.view', testid: 'nav-dashboard' },
        { icon: Zap, label: () => t('nav.quickSearch'), to: '/quick-search', permission: 'search.view', testid: 'nav-quick-search' },
        { icon: Search, label: () => t('nav.search'), to: '/search', permission: 'search.view', testid: 'nav-search' },
        { icon: Package, label: () => t('nav.products'), to: '/products', permission: 'products.view', testid: 'nav-products' },
        { icon: GitBranch, label: () => t('nav.hierarchies'), to: '/hierarchies', permission: 'hierarchies.view', testid: 'nav-hierarchies' },
        { icon: Star, label: () => t('Merkliste'), to: '/watchlist', permission: 'watchlist.view', testid: 'nav-watchlist' },
        { icon: Folders, label: () => t('Collections'), to: '/collections', module: 'collections', permission: 'collections.view', testid: 'nav-collections' },
        { icon: ClipboardList, label: () => t('Workflow'), to: '/workflow', module: 'workflow', permission: 'workflow.view', testid: 'nav-workflow' },
        { icon: CalendarDays, label: () => t('Planungskalender'), to: '/calendar', permission: 'calendar.view', testid: 'nav-calendar' },
        { divider: true },
        { icon: Image, label: () => t('nav.media'), to: '/media', permission: 'media.view', testid: 'nav-media' },
        { icon: Images, label: () => t('Motive'), to: '/media-motifs', permission: 'media.view', testid: 'nav-media-motifs' },
      ],
    },
    {
      // Eigener Bereich; komplett ausgeblendet, wenn das Content-Modul nicht lizenziert ist
      key: 'content',
      label: t('Content'),
      items: [
        { icon: FileStack, label: () => t('Content'), to: '/content', module: 'content', permission: 'content.view', testid: 'nav-content' },
        { icon: Network, label: () => t('Sitemap'), to: '/navigation', module: 'content', permission: 'navigation.view', testid: 'nav-navigation' },
        { icon: Globe, label: () => t('Website-Vorschau'), to: '/website-preview', module: 'content', permission: 'content.view', testid: 'nav-website-preview' },
        {
          key: 'grp-content-config',
          icon: Settings,
          label: () => t('Konfiguration'),
          module: 'content',
          children: [
            { icon: LayoutTemplate, label: () => t('Seitentypen'), to: '/content-types', module: 'content', permission: 'content-types.view', testid: 'nav-content-types' },
            { icon: Boxes, label: () => t('Sektionstypen'), to: '/section-types', module: 'content', permission: 'section-types.view', testid: 'nav-section-types' },
            { icon: LayoutGrid, label: () => t('Produkt-Widgets'), to: '/product-widgets', module: 'content', permission: 'product-widgets.view', testid: 'nav-product-widgets' },
            { icon: ArrowRightLeft, label: () => t('Export / Import'), to: '/content-config', module: 'content', permission: 'content-types.view', testid: 'nav-content-config' },
            { icon: Gauge, label: () => t('Cache'), to: '/content-cache', module: 'content', permission: 'content-types.view', testid: 'nav-content-cache' },
          ],
        },
        {
          // Eigene Gruppe je CMS-Integration; Hauptmenüpunkt nur sichtbar, wenn
          // mindestens ein Modul innerhalb dieser Gruppe lizenziert ist (Cascading-Filter)
          key: 'grp-content-integration',
          icon: Plug,
          label: () => t('Integration'),
          children: [
            { icon: ExternalLink, label: () => t('Typo 3'), to: '/content/integration/typo3', module: 'typo3', permission: 'content.view', testid: 'nav-content-integration-typo3' },
          ],
        },
      ],
    },
    {
      // Eigener Bereich; komplett ausgeblendet, wenn das E-Commerce-Modul nicht lizenziert ist
      key: 'ecommerce',
      label: t('E-Commerce'),
      items: [
        { icon: ShoppingCart, label: () => t('Warenkörbe'), to: '/ecommerce/cart-types', module: 'ecommerce', permission: 'ecommerce-cart-types.view', testid: 'nav-ecommerce-cart-types' },
        { icon: MapPin, label: () => t('Adressarten'), to: '/ecommerce/address-types', module: 'ecommerce', permission: 'ecommerce-address-types.view', testid: 'nav-ecommerce-address-types' },
        { icon: CreditCard, label: () => t('Zahlungsarten'), to: '/ecommerce/payment-types', module: 'ecommerce', permission: 'ecommerce-payment-types.view', testid: 'nav-ecommerce-payment-types' },
        { icon: ClipboardList, label: () => t('Bestellungen'), to: '/ecommerce/orders', module: 'ecommerce', permission: 'ecommerce-orders.view', testid: 'nav-ecommerce-orders' },
      ],
    },
    {
      key: 'publish',
      label: t('Publish'),
      items: [
        { icon: FileBarChart, label: () => t('Berichte'), to: '/reports', module: 'reports', permission: 'reports.view' },
        { icon: FileText, label: () => t('PDF-Vorlagen'), to: '/pdf-templates', module: 'pdf_templates', permission: 'pdf-templates.view' },
        { icon: LayoutTemplate, label: () => t('Katalog-Vorlagen'), to: '/catalog-templates', module: 'catalog_templates', permission: 'catalog-templates.view' },
        { icon: ExternalLink, label: () => t('Katalog-Demo'), to: '/catalog-embed', module: 'catalog_templates', permission: 'catalog-templates.view', external: true },
        { icon: Clapperboard, label: () => t('Social-Video'), to: '/social-video', permission: 'products.view', testid: 'nav-social-video' },
      ],
    },
    {
      key: 'plugins',
      label: t('Plugins'),
      items: [
        { icon: Settings, label: () => t('Plugin-Einstellungen'), to: '/plugin-settings', permission: 'users.view' },
        {
          key: 'grp-connectors',
          icon: Plug,
          label: () => t('Connectoren'),
          module: 'connectors', permission: 'connectors.view',
          children: [
            { icon: Package, label: () => t('Shopware'), to: '/connectors/shopware', module: 'connectors', permission: 'connectors.view' },
            { icon: ShoppingBag, label: () => t('Shopify'), to: '/connectors/shopify', module: 'connectors', permission: 'connectors.view' },
            { icon: Zap, label: () => t('Claude AI'), to: '/connectors/claude-ai', module: 'connectors', permission: 'connectors.view' },
            { icon: ArrowDownUp, label: () => t('anyPIM Sync'), to: '/connectors/anypim', module: 'connectors', permission: 'connectors.view' },
            { icon: Key, label: () => t('API-Keys'), to: '/connectors/api-keys', module: 'connectors', permission: 'connectors.view' },
          ],
        },
      ],
    },
    {
      key: 'translations',
      label: t('Übersetzungen'),
      items: [
        { icon: Globe, label: () => t('Sprachen'), to: '/languages' },
        { icon: ClipboardList, label: () => t('Übersetzungsjobs'), to: '/translation-jobs', permission: 'translations.view' },
        { icon: Languages, label: () => t('Translation Memory'), to: '/translations', permission: 'translations.view' },
        { icon: Settings, label: () => t('DeepL Einstellungen'), to: '/connectors/deepl', module: 'connectors', permission: 'connectors.view' },
      ],
    },
    {
      key: 'project',
      label: t('Projektmanagement'),
      items: [
        { icon: LayoutDashboard, label: () => t('Projekt-Dashboard'), to: '/project-dashboard', permission: 'dashboard.view' },
        {
          key: 'grp-workflows',
          icon: GitBranch,
          label: () => t('Workflows'),
          module: 'workflow',
          children: [
            { icon: GitBranch, label: () => t('Workflows'), to: '/workflows', permission: 'workflows.view' },
            { icon: Zap, label: () => t('Workflow-Status'), to: '/workflow-statuses', permission: 'workflow-statuses.view' },
          ],
        },
        {
          key: 'grp-organisation',
          icon: Users,
          label: () => t('Organisation'),
          children: [
            { icon: Users, label: () => t('Teams'), to: '/teams', permission: 'teams.view' },
            { icon: FolderTree, label: () => t('Projekte'), to: '/projects', permission: 'projects.view' },
          ],
        },
      ],
    },
    {
      key: 'config',
      label: t('Konfiguration'),
      items: [
        {
          key: 'grp-produktstruktur',
          icon: Layers,
          label: () => t('Produktstruktur'),
          children: [
            { icon: Factory, label: () => t('nav.manufacturers'), to: '/manufacturers', permission: 'manufacturers.view' },
            { icon: Layers, label: () => t('nav.productTypes'), to: '/product-types', permission: 'product-types.view' },
            { icon: Shield, label: () => t('Referenz-Profile'), to: '/reference-profiles', permission: 'reference-profiles.view' },
            { icon: FileBarChart, label: () => t('Konformitäts-Report'), to: '/conformance-report', permission: 'conformance.view' },
            { icon: Link2, label: () => t('nav.relationTypes'), to: '/relation-types', permission: 'relation-types.view' },
          ],
        },
        {
          key: 'grp-attribute',
          icon: Sliders,
          label: () => t('Attribute'),
          children: [
            { icon: LayoutGrid, label: () => t('nav.attributeViews'), to: '/attribute-views', permission: 'attribute-views.view' },
            { icon: FolderTree, label: () => t('nav.attributeTypes'), to: '/attribute-types', permission: 'attribute-types.view' },
            { icon: Sliders, label: () => t('nav.attributes'), to: '/attributes', permission: 'attributes.view' },
            { icon: Database, label: () => t('nav.valueLists'), to: '/value-lists', permission: 'value-lists.view' },
            { icon: Wand2, label: () => t('nav.formattingRules'), to: '/formatting-rules', permission: 'attribute-formatting-rules.view' },
            { icon: BookOpen, label: () => t('nav.dictionary'), to: '/dictionary', permission: 'dictionary.view' },
          ],
        },
        {
          key: 'grp-preise',
          icon: DollarSign,
          label: () => t('Preise & Einheiten'),
          children: [
            { icon: Ruler, label: () => t('Einheiten'), to: '/units', permission: 'units.view' },
            { icon: ArrowRightLeft, label: () => t('Vergleichsoperatoren'), to: '/comparison-operators', permission: 'units.view' },
            { icon: DollarSign, label: () => t('nav.prices'), to: '/prices', permission: 'prices.view' },
            { icon: Globe, label: () => t('nav.priceRegions'), to: '/price-regions', permission: 'price-regions.view' },
          ],
        },
        {
          key: 'grp-medien',
          icon: Image,
          label: () => t('Medien'),
          children: [
            { icon: Tags, label: () => t('nav.mediaUsageTypes'), to: '/media-usage-types', permission: 'media-usage-types.view' },
            { icon: Languages, label: () => t('Medien-Sprachen'), to: '/media-languages', permission: 'media-languages.view' },
            { icon: MapPin, label: () => t('Medien-Länder'), to: '/media-countries', permission: 'media-countries.view' },
          ],
        },
        {
          key: 'grp-collections-config',
          icon: Folders,
          label: () => t('Collections'),
          module: 'collections',
          children: [
            { icon: Folders, label: () => t('Collection-Typen'), to: '/collection-types', module: 'collections', permission: 'collection-types.view' },
          ],
        },
      ],
    },
    {
      key: 'admin',
      label: t('Administration'),
      items: [
        {
          key: 'grp-datenaustausch',
          icon: Upload,
          label: () => t('Datenaustausch'),
          children: [
            { icon: Upload, label: () => t('nav.imports'), to: '/imports', permission: 'imports.view' },
            { icon: Download, label: () => t('nav.exports'), to: '/exports', permission: 'export.view' },
            { icon: FileJson, label: () => t('JSON Export/Import'), to: '/json-export-import', permission: 'json-export-import.view' },
            { icon: FileSpreadsheet, label: () => t('Sheet Designer'), to: '/excel-designer', module: 'excel_designer', permission: 'excel-templates.view' },
            { icon: FileCode, label: () => t('BMEcat Import/Export'), to: '/bmecat-import-export', module: 'bmecat', permission: 'bmecat.view' },
            { icon: PlayCircle, label: () => t('Export-Jobs'), to: '/export-jobs', module: 'advanced_export', permission: 'export-jobs.view' },
            { icon: ArrowRightLeft, label: () => t('Attribut-Mapping'), to: '/attribute-mappings', permission: 'attribute-mappings.view' },
          ],
        },
        { icon: Settings, label: () => t('nav.settings'), to: '/settings', permission: 'settings.view' },
        {
          key: 'grp-benutzer',
          icon: Users,
          label: () => t('Benutzer & Rollen'),
          children: [
            { icon: Users, label: () => t('nav.users'), to: '/users', permission: 'users.view' },
            { icon: ScrollText, label: () => t('Benutzer-Audit'), to: '/users/audit', permission: 'users.view' },
            { icon: Shield, label: () => t('Rollen'), to: '/roles', permission: 'roles.view' },
            { icon: LayoutDashboard, label: () => t('Cockpit-Layouts'), to: '/cockpit-editor', permission: 'cockpit-layouts.view' },
            { icon: Link2, label: () => t('Zugangslinks'), to: '/access-links', permission: 'access-links.manage' },
          ],
        },
        {
          key: 'grp-system',
          icon: Database,
          label: () => t('System'),
          children: [
            { icon: FlaskConical, label: () => t('Test anyPIM'), to: '/test-runner', permission: 'users.view' },
            { icon: Code, label: () => t('API-Designer'), to: '/api-designer', module: 'api_designer', permission: 'api-templates.view' },
            { icon: Bot, label: () => t('MCP Playground'), to: '/mcp-playground', module: 'api_designer', permission: 'api-templates.view' },
            { icon: Zap, label: () => t('API Tester'), to: '/api-tester', permission: 'users.view' },
            { icon: Database, label: () => t('Datenbank'), to: '/db', permission: 'users.view' },
            { icon: Shield, label: () => t('Datenkonsistenz'), to: '/db-consistency', permission: 'users.view' },
            { icon: ShieldAlert, label: () => t('Guard'), to: '/security-guard', adminOnly: true },
            { icon: FileText, label: () => t('Log Viewer'), to: '/logs', permission: 'users.view' },
            { icon: Terminal, label: () => t('Artisan-Cockpit'), to: '/artisan-cockpit', permission: 'users.view' },
            { icon: Bug, label: () => t('Fehlerliste'), to: '/errors', permission: 'users.view' },
            { icon: ScrollText, label: () => t('Journal'), to: '/journal', permission: 'journal.view' },
          ],
        },
      ],
    },
    {
      key: 'help',
      label: null, // no header, pinned at bottom
      items: [
        { icon: HelpCircle, label: () => t('nav.help'), to: '/help' },
      ],
    },
  ]

  // Filter by permissions and module license (supports nested children)
  return all.map(section => ({
    ...section,
    items: section.items
      .map(item => {
        if (item.children) {
          const filtered = item.children.filter(child =>
            (!child.permission || authStore.hasPermission(child.permission))
            && (!child.adminOnly || authStore.isAdmin)
            && isModuleAccessible(child.module)
          )
          return filtered.length > 0 ? { ...item, children: filtered } : null
        }
        if (item.divider) return item
        if ((!item.permission || authStore.hasPermission(item.permission))
          && (!item.adminOnly || authStore.isAdmin)
          && isModuleAccessible(item.module)) {
          return item
        }
        return null
      })
      .filter(Boolean),
  })).filter(section => section.items.length > 0)
})

const hasAnySectionOpen = computed(() => {
  return sections.value.some(section => {
    if (!section.label || section.key === 'help') return false
    if (!isSectionCollapsed(section.key)) return true
    // Check sub-groups
    return section.items.some(item => item.children && !isSectionCollapsed(item.key))
  })
})

function isSectionCollapsed(key) {
  // 'daily' defaults to open, all others default to collapsed
  if (key === 'daily' && authStore.sidebarCollapsedSections[key] === undefined) return false
  return authStore.sidebarCollapsedSections[key] !== false
}

function sectionHasActiveRoute(section) {
  return section.items.some(item => {
    if (item.children) return item.children.some(c => isActive(c.to))
    return !item.divider && isActive(typeof item.to === 'function' ? item.to() : item.to)
  })
}

function groupHasActiveRoute(item) {
  return item.children?.some(c => isActive(c.to))
}

function isActive(to) {
  return route.path === to || route.path.startsWith(to + '/')
}

function navigate(item) {
  const url = typeof item.to === 'function' ? item.to() : item.to
  if (item.external) {
    const base = (import.meta.env.VITE_BASE_PATH || '/').replace(/\/+$/, '')
    window.open(base + url, '_blank')
  } else {
    router.push(url)
  }
  // Close mobile sidebar on navigation
  authStore.closeMobileSidebar()
}

// ─── Auto-expand group when active route is inside a collapsed group ──
watch(() => route.path, () => {
  for (const section of sections.value) {
    for (const item of section.items) {
      if (item.children && groupHasActiveRoute(item) && isSectionCollapsed(item.key)) {
        authStore.toggleSidebarSection(item.key)
      }
    }
  }
}, { immediate: true })

// ─── Resize drag ────────────────────────────────────────
const isResizing = ref(false)
const MIN_WIDTH = 180
const MAX_WIDTH = 400

function startResize(e) {
  if (authStore.sidebarCollapsed) return
  e.preventDefault()
  isResizing.value = true
  const startX = e.clientX
  const startWidth = authStore.sidebarWidth

  function onMove(ev) {
    const newWidth = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, startWidth + (ev.clientX - startX)))
    authStore.setSidebarWidth(newWidth)
  }

  function onUp() {
    isResizing.value = false
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }

  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}

const sidebarStyle = computed(() => ({
  width: authStore.sidebarCollapsed ? '56px' : authStore.sidebarWidth + 'px',
}))
</script>

<template>
  <!-- Mobile backdrop -->
  <transition name="fade">
    <div
      v-if="authStore.sidebarMobileOpen"
      class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 md:hidden"
      @click="authStore.closeMobileSidebar()"
    />
  </transition>

  <aside
    :class="[
      'fixed top-0 left-0 h-screen bg-[var(--pim-sidebar-bg)] border-r border-[var(--pim-sidebar-border)] z-40 flex flex-col transition-[width,transform] duration-200',
      // Mobile: hidden by default, slide in when open
      authStore.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
    ]"
    :style="{ width: 'var(--sidebar-w)', '--sidebar-w': sidebarStyle.width }"
    class="max-md:!w-[280px]"
  >
    <!-- Logo -->
    <div class="flex items-center px-3 h-14 border-b border-[var(--pim-sidebar-border)] shrink-0">
      <AnyPimLogo
        :showText="!authStore.sidebarCollapsed"
        size="sm"
        :style="{ '--anypim-logo-text': 'var(--pim-sidebar-text)', '--anypim-logo-subtext': 'var(--pim-sidebar-icon)' }"
      />
      <button
        v-if="!authStore.sidebarCollapsed && hasAnySectionOpen"
        class="ml-auto p-1 rounded text-[var(--pim-sidebar-icon)] hover:text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)] transition-colors cursor-pointer"
        :title="t('Alle Menüs schließen')"
        @click="authStore.collapseAllSections()"
      >
        <ChevronsDownUp class="w-4 h-4" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-1 overflow-y-auto overflow-x-hidden">
      <template v-for="(section, si) in sections" :key="section.key">
        <!-- Skip 'help' section here — rendered at bottom -->
        <template v-if="section.key !== 'help'">
          <!-- Section separator (between sections) -->
          <div v-if="si > 0" class="my-1.5 mx-3 border-t border-[var(--pim-sidebar-border)]" />

          <!-- Section header (collapsible) -->
          <button
            v-if="section.label && !authStore.sidebarCollapsed"
            class="w-full flex items-center gap-1 px-3 py-1.5 text-[10px] uppercase tracking-wider font-semibold text-[var(--pim-sidebar-icon)] hover:text-[var(--pim-sidebar-text)] transition-colors cursor-pointer select-none"
            @click="authStore.toggleSidebarSection(section.key)"
          >
            <ChevronDown v-if="!isSectionCollapsed(section.key)" class="w-3 h-3 shrink-0" :stroke-width="2" />
            <ChevronRight v-else class="w-3 h-3 shrink-0" :stroke-width="2" />
            <span>{{ section.label }}</span>
            <span
              v-if="isSectionCollapsed(section.key) && sectionHasActiveRoute(section)"
              class="w-1.5 h-1.5 rounded-full bg-[var(--pim-sidebar-active-text)] ml-auto"
            />
          </button>

          <!-- Collapsed indicator for icon-only mode -->
          <div v-if="section.label && authStore.sidebarCollapsed" class="my-0.5 mx-2 border-t border-[var(--pim-sidebar-border)]" />

          <!-- Section items -->
          <div v-show="!section.label || !isSectionCollapsed(section.key) || authStore.sidebarCollapsed">
            <template v-for="(item, j) in section.items" :key="item.key || j">
              <!-- Divider -->
              <div v-if="item.divider && !authStore.sidebarCollapsed" class="my-1 mx-3 border-t border-[var(--pim-sidebar-border)] opacity-40" />

              <!-- Sub-group (has children) -->
              <template v-else-if="item.children">
                <!-- Group header button (expanded sidebar) -->
                <button
                  v-if="!authStore.sidebarCollapsed"
                  :class="[
                    'w-full flex items-center gap-3 px-3 py-[7px] mx-1 rounded-md transition-colors duration-100 cursor-pointer',
                    groupHasActiveRoute(item) && !isSectionCollapsed(item.key)
                      ? 'text-[var(--pim-sidebar-text)]'
                      : 'text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)]'
                  ]"
                  :style="{ fontSize: 'var(--pim-sidebar-font-size)' }"
                  @click="authStore.toggleSidebarSection(item.key)"
                >
                  <component
                    :is="item.icon"
                    class="w-[18px] h-[18px] shrink-0"
                    :stroke-width="1.75"
                    :style="sectionIconColor(section.key) ? { color: sectionIconColor(section.key) } : { color: 'var(--pim-sidebar-icon)' }"
                  />
                  <span class="truncate flex-1 text-left">{{ item.label() }}</span>
                  <ChevronDown
                    v-if="!isSectionCollapsed(item.key)"
                    class="w-3.5 h-3.5 shrink-0 text-[var(--pim-sidebar-icon)]"
                    :stroke-width="2"
                  />
                  <ChevronRight
                    v-else
                    class="w-3.5 h-3.5 shrink-0 text-[var(--pim-sidebar-icon)]"
                    :stroke-width="2"
                  />
                  <!-- Active dot when group is collapsed -->
                  <span
                    v-if="isSectionCollapsed(item.key) && groupHasActiveRoute(item)"
                    class="w-1.5 h-1.5 rounded-full bg-[var(--pim-sidebar-active-text)] ml-0.5"
                  />
                </button>

                <!-- Group icon in collapsed sidebar mode -->
                <button
                  v-if="authStore.sidebarCollapsed"
                  :class="[
                    'w-full flex items-center justify-center px-3 py-[7px] mx-1.5 rounded-md transition-colors duration-100 cursor-pointer',
                    groupHasActiveRoute(item)
                      ? 'bg-[var(--pim-sidebar-active-bg)] text-[var(--pim-sidebar-active-text)] font-medium'
                      : 'text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)]'
                  ]"
                  :style="{ fontSize: 'var(--pim-sidebar-font-size)' }"
                  :title="item.label()"
                  @click="navigate(item.children[0])"
                >
                  <component
                    :is="item.icon"
                    class="w-[18px] h-[18px] shrink-0"
                    :stroke-width="1.75"
                    :style="!groupHasActiveRoute(item) && sectionIconColor(section.key) ? { color: sectionIconColor(section.key) } : {}"
                  />
                </button>

                <!-- Group children (indented) -->
                <div v-show="!isSectionCollapsed(item.key) && !authStore.sidebarCollapsed">
                  <button
                    v-for="(child, ci) in item.children"
                    :key="ci"
                    :class="[
                      'w-full flex items-center gap-2.5 pl-9 pr-3 py-[6px] mx-1 rounded-md transition-colors duration-100 cursor-pointer',
                      isActive(typeof child.to === 'function' ? child.to() : child.to)
                        ? 'bg-[var(--pim-sidebar-active-bg)] text-[var(--pim-sidebar-active-text)] font-medium'
                        : 'text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)]'
                    ]"
                    :style="{ fontSize: 'var(--pim-sidebar-font-size)' }"
                    @click="navigate(child)"
                  >
                    <component
                      :is="child.icon"
                      class="w-4 h-4 shrink-0"
                      :stroke-width="1.75"
                      :style="!isActive(typeof child.to === 'function' ? child.to() : child.to) && sectionIconColor(section.key) ? { color: sectionIconColor(section.key) } : {}"
                    />
                    <span class="truncate">{{ child.label() }}</span>
                  </button>
                </div>
              </template>

              <!-- Leaf item (no children) -->
              <button
                v-else-if="!item.divider"
                :class="[
                  'w-full flex items-center gap-3 px-3 py-[7px] mx-1 rounded-md transition-colors duration-100 cursor-pointer',
                  authStore.sidebarCollapsed ? 'justify-center mx-1.5' : '',
                  isActive(typeof item.to === 'function' ? item.to() : item.to)
                    ? 'bg-[var(--pim-sidebar-active-bg)] text-[var(--pim-sidebar-active-text)] font-medium'
                    : 'text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)]'
                ]"
                :style="{ fontSize: 'var(--pim-sidebar-font-size)' }"
                @click="navigate(item)"
                :title="authStore.sidebarCollapsed ? item.label() : undefined"
                :data-testid="item.testid || undefined"
              >
                <component
                  :is="item.icon"
                  class="w-[18px] h-[18px] shrink-0"
                  :stroke-width="1.75"
                  :style="!isActive(typeof item.to === 'function' ? item.to() : item.to) && sectionIconColor(section.key) ? { color: sectionIconColor(section.key) } : {}"
                />
                <span v-if="!authStore.sidebarCollapsed" class="truncate">{{ item.label() }}</span>
              </button>
            </template>
          </div>
        </template>
      </template>
    </nav>

    <!-- Help (pinned at bottom, above collapse toggle) -->
    <div class="shrink-0 px-0 pb-0">
      <div class="mx-3 border-t border-[var(--pim-sidebar-border)]" />
      <template v-for="item in sections.find(s => s.key === 'help')?.items || []" :key="item.to">
        <button
          :class="[
            'w-full flex items-center gap-3 px-3 py-[7px] mx-1 my-1 rounded-md transition-colors duration-100 cursor-pointer',
            authStore.sidebarCollapsed ? 'justify-center mx-1.5' : '',
            isActive(typeof item.to === 'function' ? item.to() : item.to)
              ? 'bg-[var(--pim-sidebar-active-bg)] text-[var(--pim-sidebar-active-text)] font-medium'
              : 'text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)]'
          ]"
          :style="{ fontSize: 'var(--pim-sidebar-font-size)' }"
          @click="navigate(item)"
          :title="authStore.sidebarCollapsed ? item.label() : undefined"
        >
          <component
            :is="item.icon"
            class="w-[18px] h-[18px] shrink-0"
            :style="sectionIconColor('help') ? { color: sectionIconColor('help') } : { color: 'var(--pim-sidebar-icon)' }"
            :stroke-width="1.75"
          />
          <span v-if="!authStore.sidebarCollapsed">{{ item.label() }}</span>
        </button>
      </template>
    </div>

    <!-- Footer: Collapse toggle (hidden on mobile) -->
    <div class="border-t border-[var(--pim-sidebar-border)] p-2 shrink-0 hidden md:block">
      <button
        class="w-full flex items-center justify-center gap-2 py-1.5 rounded-md text-[var(--pim-sidebar-icon)] hover:text-[var(--pim-sidebar-text)] hover:bg-[var(--pim-sidebar-hover-bg)] transition-colors"
        @click="authStore.toggleSidebar()"
        :title="authStore.sidebarCollapsed ? 'Sidebar öffnen' : 'Sidebar schließen'"
      >
        <PanelLeftClose v-if="!authStore.sidebarCollapsed" class="w-4 h-4" :stroke-width="1.75" />
        <PanelLeft v-else class="w-4 h-4" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Resize handle (hidden on mobile) -->
    <div
      v-if="!authStore.sidebarCollapsed"
      class="absolute top-0 right-0 w-[5px] h-full cursor-col-resize group z-50 items-center justify-center hidden md:flex"
      @mousedown="startResize"
    >
      <div
        class="w-[2px] h-full transition-colors"
        :class="isResizing ? 'bg-[var(--pim-sidebar-active-text)]' : 'bg-transparent group-hover:bg-[var(--pim-sidebar-active-text)]/40'"
      />
    </div>
  </aside>
</template>

<style scoped>
/* Scrollbar-Styling fuer dunkle Themes — ohne dies ist der Scrollbar unsichtbar */
nav::-webkit-scrollbar {
  width: 6px;
}
nav::-webkit-scrollbar-track {
  background: transparent;
}
nav::-webkit-scrollbar-thumb {
  background: var(--pim-sidebar-border);
  border-radius: 3px;
}
nav::-webkit-scrollbar-thumb:hover {
  background: var(--pim-sidebar-icon);
}
/* Firefox */
nav {
  scrollbar-width: thin;
  scrollbar-color: var(--pim-sidebar-border) transparent;
}
</style>
