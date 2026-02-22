<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  Search, Package, GitBranch, Sliders, Database, Layers, FolderTree,
  Upload, Download, Image, Tags, DollarSign, Users, Settings,
  PanelLeftClose, PanelLeft,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()

const navItems = computed(() => [
  { icon: Search, label: t('nav.search'), to: '/search' },
  { icon: Package, label: t('nav.products'), to: '/products' },
  { icon: GitBranch, label: t('nav.hierarchies'), to: '/hierarchies' },
  { icon: Sliders, label: t('nav.attributes'), to: '/attributes' },
  { icon: Layers, label: t('nav.productTypes'), to: '/product-types' },
  { icon: FolderTree, label: t('nav.attributeTypes'), to: '/attribute-types' },
  { icon: Database, label: t('nav.valueLists'), to: '/value-lists' },
  { divider: true },
  { icon: Upload, label: t('nav.imports'), to: '/imports' },
  { icon: Download, label: t('nav.exports'), to: '/exports' },
  { icon: Image, label: t('nav.media'), to: '/media' },
  { icon: Tags, label: t('nav.mediaUsageTypes'), to: '/media-usage-types' },
  { icon: DollarSign, label: t('nav.prices'), to: '/prices' },
  { divider: true },
  { icon: Users, label: t('nav.users'), to: '/users' },
  { icon: Settings, label: t('nav.settings'), to: '/settings' },
])

function isActive(to) {
  return route.path === to || route.path.startsWith(to + '/')
}
</script>

<template>
  <aside
    :class="[
      'fixed top-0 left-0 h-screen bg-[var(--color-surface)] border-r border-[var(--color-border)] z-40 flex flex-col transition-all duration-200',
      authStore.sidebarCollapsed ? 'w-[56px]' : 'w-[240px]'
    ]"
  >
    <!-- Logo -->
    <div class="flex items-center gap-2 px-4 h-14 border-b border-[var(--color-border)] shrink-0">
      <div class="w-7 h-7 rounded-md bg-[var(--color-primary)] flex items-center justify-center shrink-0">
        <span class="text-white font-bold text-xs">P</span>
      </div>
      <span
        v-if="!authStore.sidebarCollapsed"
        class="font-semibold text-sm text-[var(--color-primary)] tracking-tight"
      >
        Publixx PIM
      </span>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-2 overflow-y-auto">
      <template v-for="(item, i) in navItems" :key="i">
        <div v-if="item.divider" class="my-2 mx-3 border-t border-[var(--color-border)]" />
        <button
          v-else
          :class="[
            'w-full flex items-center gap-3 px-3 py-[7px] mx-1 rounded-md text-[13px] transition-colors duration-100 cursor-pointer',
            authStore.sidebarCollapsed ? 'justify-center mx-1.5' : '',
            isActive(item.to)
              ? 'bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] font-medium'
              : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] hover:text-[var(--color-text-primary)]'
          ]"
          @click="router.push(item.to)"
          :title="authStore.sidebarCollapsed ? item.label : undefined"
        >
          <component :is="item.icon" class="w-[18px] h-[18px] shrink-0" :stroke-width="1.75" />
          <span v-if="!authStore.sidebarCollapsed">{{ item.label }}</span>
        </button>
      </template>
    </nav>

    <!-- Footer: Collapse toggle -->
    <div class="border-t border-[var(--color-border)] p-2 shrink-0">
      <button
        class="w-full flex items-center justify-center gap-2 py-1.5 rounded-md text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)] transition-colors"
        @click="authStore.toggleSidebar()"
        :title="authStore.sidebarCollapsed ? 'Sidebar öffnen' : 'Sidebar schließen'"
      >
        <PanelLeftClose v-if="!authStore.sidebarCollapsed" class="w-4 h-4" :stroke-width="1.75" />
        <PanelLeft v-else class="w-4 h-4" :stroke-width="1.75" />
      </button>
    </div>
  </aside>
</template>
