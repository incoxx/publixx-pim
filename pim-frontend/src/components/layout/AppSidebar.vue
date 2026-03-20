<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import {
  Search, Package, GitBranch, Sliders, Database, Layers, FolderTree,
  Upload, Download, Image, Tags, DollarSign, Users, Settings, Shield,
  HelpCircle, PanelLeftClose, PanelLeft, Star, LayoutGrid, Ruler,
  FileJson, FileCode, PlayCircle, FileBarChart, FileText, BookOpen, Link2, Zap, Languages, LayoutTemplate,
  ChevronDown, ChevronRight, GripVertical, Factory, CalendarDays, ScrollText, Globe, Send,
  LayoutDashboard, ClipboardList, Code, ExternalLink, Plug,
} from 'lucide-vue-next'
import AnyPimLogo from '@/components/shared/AnyPimLogo.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()

// ─── Menu sections ──────────────────────────────────────
const sections = computed(() => {
  const all = [
    {
      key: 'daily',
      label: null, // no header for the top section
      items: [
        { icon: LayoutDashboard, label: () => 'Dashboard', to: '/dashboard' },
        { icon: Search, label: () => t('nav.search'), to: '/search' },
        { icon: Package, label: () => t('nav.products'), to: '/products' },
        { icon: GitBranch, label: () => t('nav.hierarchies'), to: '/hierarchies' },
        { icon: Star, label: () => 'Merkliste', to: '/watchlist' },
        { icon: ClipboardList, label: () => 'Workflow', to: '/workflow' },
        { icon: CalendarDays, label: () => 'Planungskalender', to: '/calendar' },
        { divider: true },
        { icon: Image, label: () => t('nav.media'), to: '/media' },
      ],
    },
    {
      key: 'publish',
      label: 'Publish',
      items: [
        { icon: FileBarChart, label: () => 'Berichte', to: '/reports', module: 'reports' },
        { icon: FileText, label: () => 'PDF-Vorlagen', to: '/pdf-templates', module: 'pdf_templates' },
        { icon: LayoutTemplate, label: () => 'Katalog-Vorlagen', to: '/catalog-templates' },
      ],
    },
    {
      key: 'plugins',
      label: 'Plugins',
      items: [
        { icon: Settings, label: () => 'Plugin-Einstellungen', to: '/plugin-settings', permission: 'users.view' },
        { icon: Languages, label: () => 'Übersetzung (DeepL)', to: '/connectors/deepl', module: 'connectors' },
        {
          key: 'grp-connectors',
          icon: Plug,
          label: () => 'Connectoren',
          module: 'connectors',
          children: [
            { icon: Send, label: () => 'Canva', to: '/connectors/canva', module: 'connectors' },
            { icon: Package, label: () => 'Shopware', to: '/connectors/shopware', module: 'connectors' },
            { icon: Image, label: () => 'Cloudinary', to: '/connectors/cloudinary', module: 'connectors' },
            { icon: Zap, label: () => 'Claude AI', to: '/connectors/claude-ai', module: 'connectors' },
          ],
        },
      ],
    },
    {
      key: 'project',
      label: 'Projektmanagement',
      items: [
        { icon: LayoutDashboard, label: () => 'Projekt-Dashboard', to: '/project-dashboard' },
        {
          key: 'grp-workflows',
          icon: GitBranch,
          label: () => 'Workflows',
          children: [
            { icon: GitBranch, label: () => 'Workflows', to: '/workflows' },
            { icon: Zap, label: () => 'Workflow-Status', to: '/workflow-statuses' },
          ],
        },
        {
          key: 'grp-organisation',
          icon: Users,
          label: () => 'Organisation',
          children: [
            { icon: Users, label: () => 'Teams', to: '/teams' },
            { icon: FolderTree, label: () => 'Projekte', to: '/projects' },
          ],
        },
      ],
    },
    {
      key: 'config',
      label: 'Konfiguration',
      items: [
        { icon: Tags, label: () => t('nav.mediaUsageTypes'), to: '/media-usage-types' },
        { icon: Languages, label: () => t('nav.translations'), to: '/translations' },
        {
          key: 'grp-produktstruktur',
          icon: Layers,
          label: () => 'Produktstruktur',
          children: [
            { icon: Factory, label: () => t('nav.manufacturers'), to: '/manufacturers' },
            { icon: Layers, label: () => t('nav.productTypes'), to: '/product-types' },
            { icon: Link2, label: () => t('nav.relationTypes'), to: '/relation-types' },
          ],
        },
        {
          key: 'grp-attribute',
          icon: Sliders,
          label: () => 'Attribute',
          children: [
            { icon: LayoutGrid, label: () => t('nav.attributeViews'), to: '/attribute-views' },
            { icon: FolderTree, label: () => t('nav.attributeTypes'), to: '/attribute-types' },
            { icon: Sliders, label: () => t('nav.attributes'), to: '/attributes' },
            { icon: Database, label: () => t('nav.valueLists'), to: '/value-lists' },
            { icon: BookOpen, label: () => t('nav.dictionary'), to: '/dictionary' },
          ],
        },
        {
          key: 'grp-preise',
          icon: DollarSign,
          label: () => 'Preise & Einheiten',
          children: [
            { icon: Ruler, label: () => 'Einheiten', to: '/units' },
            { icon: DollarSign, label: () => t('nav.prices'), to: '/prices' },
            { icon: Globe, label: () => t('nav.priceRegions'), to: '/price-regions' },
          ],
        },
      ],
    },
    {
      key: 'admin',
      label: 'Administration',
      items: [
        {
          key: 'grp-datenaustausch',
          icon: Upload,
          label: () => 'Datenaustausch',
          children: [
            { icon: Upload, label: () => t('nav.imports'), to: '/imports' },
            { icon: Download, label: () => t('nav.exports'), to: '/exports' },
            { icon: FileJson, label: () => 'JSON Export/Import', to: '/json-export-import' },
            { icon: FileCode, label: () => 'BMEcat Import/Export', to: '/bmecat-import-export', module: 'bmecat' },
            { icon: PlayCircle, label: () => 'Export-Jobs', to: '/export-jobs', module: 'advanced_export' },
            { icon: ExternalLink, label: () => 'Katalog-Demo', to: '/catalog-embed', external: true },
          ],
        },
        {
          key: 'grp-benutzer',
          icon: Users,
          label: () => 'Benutzer & Rollen',
          children: [
            { icon: Settings, label: () => t('nav.settings'), to: '/settings', permission: 'users.view' },
            { icon: Users, label: () => t('nav.users'), to: '/users', permission: 'users.view' },
            { icon: ScrollText, label: () => 'Benutzer-Audit', to: '/users/audit', permission: 'users.view' },
            { icon: Shield, label: () => 'Rollen', to: '/roles', permission: 'roles.view' },
            { icon: Link2, label: () => 'Zugangslinks', to: '/access-links', permission: 'access-links.manage' },
          ],
        },
        {
          key: 'grp-system',
          icon: Database,
          label: () => 'System',
          children: [
            { icon: Code, label: () => 'API-Designer', to: '/api-designer', module: 'api_designer' },
            { icon: Zap, label: () => 'API Tester', to: '/api-tester', permission: 'users.view' },
            { icon: Database, label: () => 'Datenbank', to: '/db', permission: 'users.view' },
            { icon: ScrollText, label: () => 'Journal', to: '/journal', permission: 'users.view' },
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
            && (!child.module || !licenseStore.loaded || licenseStore.isModuleActive(child.module))
          )
          return filtered.length > 0 ? { ...item, children: filtered } : null
        }
        if (item.divider) return item
        if ((!item.permission || authStore.hasPermission(item.permission))
          && (!item.module || !licenseStore.loaded || licenseStore.isModuleActive(item.module))) {
          return item
        }
        return null
      })
      .filter(Boolean),
  }))
})

function isSectionCollapsed(key) {
  // All sections and sub-groups default to collapsed on start
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
      'fixed top-0 left-0 h-screen bg-[var(--color-surface)] border-r border-[var(--color-border)] z-40 flex flex-col transition-[width,transform] duration-200',
      // Mobile: hidden by default, slide in when open
      authStore.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
    ]"
    :style="{ width: 'var(--sidebar-w)', '--sidebar-w': sidebarStyle.width }"
    class="max-md:!w-[280px]"
  >
    <!-- Logo -->
    <div class="flex items-center px-3 h-14 border-b border-[var(--color-border)] shrink-0">
      <AnyPimLogo :showText="!authStore.sidebarCollapsed" size="sm" />
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-1 overflow-y-auto overflow-x-hidden">
      <template v-for="(section, si) in sections" :key="section.key">
        <!-- Skip 'help' section here — rendered at bottom -->
        <template v-if="section.key !== 'help'">
          <!-- Section separator (between sections) -->
          <div v-if="si > 0" class="my-1.5 mx-3 border-t border-[var(--color-border)]" />

          <!-- Section header (collapsible) -->
          <button
            v-if="section.label && !authStore.sidebarCollapsed"
            class="w-full flex items-center gap-1 px-3 py-1.5 text-[10px] uppercase tracking-wider font-semibold text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors cursor-pointer select-none"
            @click="authStore.toggleSidebarSection(section.key)"
          >
            <ChevronDown v-if="!isSectionCollapsed(section.key)" class="w-3 h-3 shrink-0" :stroke-width="2" />
            <ChevronRight v-else class="w-3 h-3 shrink-0" :stroke-width="2" />
            <span>{{ section.label }}</span>
            <span
              v-if="isSectionCollapsed(section.key) && sectionHasActiveRoute(section)"
              class="w-1.5 h-1.5 rounded-full bg-[var(--color-accent)] ml-auto"
            />
          </button>

          <!-- Collapsed indicator for icon-only mode -->
          <div v-if="section.label && authStore.sidebarCollapsed" class="my-0.5 mx-2 border-t border-[var(--color-border)]" />

          <!-- Section items -->
          <div v-show="!section.label || !isSectionCollapsed(section.key) || authStore.sidebarCollapsed">
            <template v-for="(item, j) in section.items" :key="item.key || j">
              <!-- Divider -->
              <div v-if="item.divider && !authStore.sidebarCollapsed" class="my-1 mx-3 border-t border-[var(--color-border)] opacity-40" />

              <!-- Sub-group (has children) -->
              <template v-else-if="item.children">
                <!-- Group header button (expanded sidebar) -->
                <button
                  v-if="!authStore.sidebarCollapsed"
                  :class="[
                    'w-full flex items-center gap-3 px-3 py-[7px] mx-1 rounded-md text-[13px] transition-colors duration-100 cursor-pointer',
                    groupHasActiveRoute(item) && !isSectionCollapsed(item.key)
                      ? 'text-[var(--color-text-primary)]'
                      : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'
                  ]"
                  @click="authStore.toggleSidebarSection(item.key)"
                >
                  <component :is="item.icon" class="w-[18px] h-[18px] shrink-0" :stroke-width="1.75" />
                  <span class="truncate flex-1 text-left">{{ item.label() }}</span>
                  <ChevronDown
                    v-if="!isSectionCollapsed(item.key)"
                    class="w-3.5 h-3.5 shrink-0 text-[var(--color-text-tertiary)]"
                    :stroke-width="2"
                  />
                  <ChevronRight
                    v-else
                    class="w-3.5 h-3.5 shrink-0 text-[var(--color-text-tertiary)]"
                    :stroke-width="2"
                  />
                  <!-- Active dot when group is collapsed -->
                  <span
                    v-if="isSectionCollapsed(item.key) && groupHasActiveRoute(item)"
                    class="w-1.5 h-1.5 rounded-full bg-[var(--color-accent)] ml-0.5"
                  />
                </button>

                <!-- Group icon in collapsed sidebar mode -->
                <button
                  v-if="authStore.sidebarCollapsed"
                  :class="[
                    'w-full flex items-center justify-center px-3 py-[7px] mx-1.5 rounded-md text-[13px] transition-colors duration-100 cursor-pointer',
                    groupHasActiveRoute(item)
                      ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] font-medium'
                      : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'
                  ]"
                  :title="item.label()"
                  @click="navigate(item.children[0])"
                >
                  <component :is="item.icon" class="w-[18px] h-[18px] shrink-0" :stroke-width="1.75" />
                </button>

                <!-- Group children (indented) -->
                <div v-show="!isSectionCollapsed(item.key) && !authStore.sidebarCollapsed">
                  <button
                    v-for="(child, ci) in item.children"
                    :key="ci"
                    :class="[
                      'w-full flex items-center gap-2.5 pl-9 pr-3 py-[6px] mx-1 rounded-md text-[13px] transition-colors duration-100 cursor-pointer',
                      isActive(typeof child.to === 'function' ? child.to() : child.to)
                        ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] font-medium'
                        : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'
                    ]"
                    @click="navigate(child)"
                  >
                    <component :is="child.icon" class="w-4 h-4 shrink-0" :stroke-width="1.75" />
                    <span class="truncate">{{ child.label() }}</span>
                  </button>
                </div>
              </template>

              <!-- Leaf item (no children) -->
              <button
                v-else-if="!item.divider"
                :class="[
                  'w-full flex items-center gap-3 px-3 py-[7px] mx-1 rounded-md text-[13px] transition-colors duration-100 cursor-pointer',
                  authStore.sidebarCollapsed ? 'justify-center mx-1.5' : '',
                  isActive(typeof item.to === 'function' ? item.to() : item.to)
                    ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] font-medium'
                    : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'
                ]"
                @click="navigate(item)"
                :title="authStore.sidebarCollapsed ? item.label() : undefined"
              >
                <component :is="item.icon" class="w-[18px] h-[18px] shrink-0" :stroke-width="1.75" />
                <span v-if="!authStore.sidebarCollapsed" class="truncate">{{ item.label() }}</span>
              </button>
            </template>
          </div>
        </template>
      </template>
    </nav>

    <!-- Help (pinned at bottom, above collapse toggle) -->
    <div class="shrink-0 px-0 pb-0">
      <div class="mx-3 border-t border-[var(--color-border)]" />
      <template v-for="item in sections.find(s => s.key === 'help')?.items || []" :key="item.to">
        <button
          :class="[
            'w-full flex items-center gap-3 px-3 py-[7px] mx-1 my-1 rounded-md text-[13px] transition-colors duration-100 cursor-pointer',
            authStore.sidebarCollapsed ? 'justify-center mx-1.5' : '',
            isActive(typeof item.to === 'function' ? item.to() : item.to)
              ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] font-medium'
              : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'
          ]"
          @click="navigate(item)"
          :title="authStore.sidebarCollapsed ? item.label() : undefined"
        >
          <component :is="item.icon" class="w-[18px] h-[18px] shrink-0" :stroke-width="1.75" />
          <span v-if="!authStore.sidebarCollapsed">{{ item.label() }}</span>
        </button>
      </template>
    </div>

    <!-- Footer: Collapse toggle (hidden on mobile) -->
    <div class="border-t border-[var(--color-border)] p-2 shrink-0 hidden md:block">
      <button
        class="w-full flex items-center justify-center gap-2 py-1.5 rounded-md text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] transition-colors"
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
        :class="isResizing ? 'bg-[var(--color-accent)]' : 'bg-transparent group-hover:bg-[var(--color-accent)]/40'"
      />
    </div>
  </aside>
</template>
