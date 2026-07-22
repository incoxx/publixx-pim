<script setup>
import { useAuthStore } from '@/stores/auth'
import { useTabStore } from '@/stores/tabs'
import { useLocaleStore } from '@/stores/locale'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Compass, Eye, Globe, LogOut, Menu, MessageCircle, PanelsTopLeft, Pin, PinOff, Sparkles, User } from 'lucide-vue-next'
import { useCopilotStore } from '@/stores/copilot'
import { useMessengerStore } from '@/stores/messenger'
import SemanticSearchBox from '@/components/layout/SemanticSearchBox.vue'

const authStore = useAuthStore()
const copilotStore = useCopilotStore()
const messengerStore = useMessengerStore()
const tabStore = useTabStore()
const localeStore = useLocaleStore()
const { t, locale: i18nLocale } = useI18n()
const route = useRoute()
const router = useRouter()

const pageTitle = computed(() => route.meta.title || '')
const isPinned = computed(() => tabStore.isRoutePinned(route))
const canPin = computed(() => route.name && route.name !== 'login' && route.name !== 'dashboard')

const localeOpen = ref(false)
const userOpen = ref(false)

function switchLocale(code) {
  localeStore.setUiLocale(code)
  i18nLocale.value = code
  localeOpen.value = false
}

function closeDropdowns(e) {
  // Dropdowns schließen wenn außerhalb geklickt/getippt
  if (!e.target.closest('[data-dropdown]')) {
    localeOpen.value = false
    userOpen.value = false
  }
}

onMounted(() => document.addEventListener('pointerdown', closeDropdowns))
onUnmounted(() => document.removeEventListener('pointerdown', closeDropdowns))

// Ansichtsmodus umschalten: Cockpit (Fokus) ⇄ klassisches Menü-GUI.
// Im Cockpit-Modus ist die Sidebar ausgeblendet; der Cockpit-Button dient daher
// auch als verlässlicher "Zurück zum Cockpit"-Einstieg von jeder Maske aus.
function setMode(mode) {
  if (authStore.effectiveViewMode !== mode) {
    authStore.setViewMode(mode)
  }
  if (mode === 'cockpit') {
    // Immer zum Cockpit navigieren — auch wenn der Modus bereits aktiv ist.
    if (route.name !== 'cockpit') router.push('/cockpit')
  } else if (route.name === 'cockpit') {
    // Beim Wechsel ins Menü die Cockpit-Seite verlassen.
    router.push('/dashboard')
  }
}

// Öffentliche Katalog-Vorschau in neuem Tab öffnen (Berechtigung preview.view).
function openCatalogPreview() {
  const url = router.resolve({ name: 'catalog' }).href
  window.open(url, '_blank')
}
</script>

<template>
  <header
    class="sticky top-0 z-20 flex items-center justify-between h-14 px-3 sm:px-6 bg-[var(--pim-toolbar-bg)]/90 backdrop-blur-md border-b border-[var(--pim-toolbar-border)]"
    :style="{ color: 'var(--pim-toolbar-text)' }"
  >
    <!-- Left: Hamburger (mobile) + Title -->
    <div class="flex items-center gap-3 min-w-0">
      <button
        class="md:hidden p-2 -ml-1 rounded-md transition-colors hover:bg-white/10 touch-manipulation"
        style="color: inherit; min-width: 44px; min-height: 44px; display: flex; align-items: center; justify-content: center;"
        @click="authStore.toggleMobileSidebar()"
      >
        <Menu class="w-5 h-5" :stroke-width="1.75" />
      </button>
      <h1 class="font-semibold truncate" style="color: inherit" :style="{ fontSize: 'var(--pim-toolbar-font-size)' }">{{ pageTitle }}</h1>
      <button
        v-if="canPin"
        class="p-1 rounded transition-colors hover:bg-white/10 shrink-0"
        :style="{ color: isPinned ? 'var(--pim-sidebar-active-text)' : 'inherit', opacity: isPinned ? 1 : 0.5 }"
        :title="isPinned ? 'Tab entfernen' : 'Als Tab anheften'"
        @click="tabStore.pinCurrentRoute(route)"
      >
        <Pin v-if="!isPinned" class="w-3.5 h-3.5" :stroke-width="1.75" />
        <PinOff v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
      </button>

      <!-- Ansichts-Umschalter: Cockpit (Fokus) ⇄ klassisches Menü -->
      <div class="hidden sm:flex ml-1 items-center rounded-lg border p-0.5 shrink-0" :style="{ borderColor: 'var(--pim-toolbar-border)' }">
        <button
          class="flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md transition-colors"
          :class="authStore.effectiveViewMode === 'cockpit' ? 'bg-white/15 font-medium' : 'hover:bg-white/10 opacity-70'"
          style="color: inherit"
          title="Cockpit / Fokus-Modus"
          @click="setMode('cockpit')"
        >
          <PanelsTopLeft class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Cockpit</span>
        </button>
        <button
          class="flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md transition-colors"
          :class="authStore.effectiveViewMode === 'gui' ? 'bg-white/15 font-medium' : 'hover:bg-white/10 opacity-70'"
          style="color: inherit"
          title="Klassisches Menü"
          @click="setMode('gui')"
        >
          <Menu class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Menü</span>
        </button>
      </div>

      <!-- Katalog-Vorschau (öffnet öffentlichen Katalog in neuem Tab) -->
      <button
        v-if="authStore.hasPermission('preview.view')"
        class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-lg border transition-colors hover:bg-white/10 shrink-0"
        :style="{ borderColor: 'var(--pim-toolbar-border)', color: 'inherit' }"
        title="Katalog-Vorschau öffnen"
        @click="openCatalogPreview"
      >
        <Eye class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Preview</span>
      </button>
    </div>

    <!-- Right: Actions -->
    <div class="flex items-center gap-1 sm:gap-2 shrink-0">
      <!-- Semantische Schnellsuche (nur wenn Meilisearch aktiv) -->
      <div class="hidden sm:block">
        <SemanticSearchBox />
      </div>

      <!-- Copilot toggle -->
      <button
        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border transition-colors hover:bg-white/10 touch-manipulation"
        :style="{ color: 'inherit', borderColor: 'var(--pim-toolbar-border)', minHeight: '44px' }"
        :class="{ 'bg-white/10': copilotStore.open }"
        title="anyPIM Copilot"
        @click="copilotStore.toggle()"
      >
        <Sparkles class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Copilot</span>
      </button>

      <!-- Befehls-/Navigationspalette ("Wo ist was?") -->
      <button
        class="hidden sm:flex items-center px-2 py-1.5 text-xs rounded-md border transition-colors hover:bg-white/10"
        :style="{ color: 'inherit', borderColor: 'var(--pim-toolbar-border)' }"
        title="Wo ist was? — Menüpunkte und Aktionen finden (⌘K)"
        @click="authStore.toggleCommandPalette()"
      >
        <Compass class="w-3.5 h-3.5" :stroke-width="1.75" />
      </button>

      <!-- Messenger: Nachrichten-Icon mit Unread-Badge -->
      <button
        class="btn btn-ghost btn-sm btn-circle indicator touch-manipulation"
        :style="{ color: 'inherit' }"
        title="Nachrichten"
        @click="router.push({ name: 'messenger' })"
      >
        <MessageCircle class="w-4 h-4" :stroke-width="1.75" />
        <span
          v-if="messengerStore.unreadCount > 0"
          class="badge badge-sm badge-secondary indicator-item font-mono text-[10px] px-1"
        >
          {{ messengerStore.unreadCount }}
        </span>
      </button>

      <!-- Locale switcher -->
      <div class="relative" data-dropdown>
        <button
          class="flex items-center gap-1 px-2 py-1.5 text-xs rounded-md transition-colors hover:bg-white/10 touch-manipulation"
          :style="{ color: 'inherit', minHeight: '44px', minWidth: '44px' }"
          :aria-expanded="localeOpen"
          @click.stop="localeOpen = !localeOpen; userOpen = false"
        >
          <Globe class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="uppercase">{{ localeStore.currentLocale }}</span>
        </button>
        <div
          v-if="localeOpen"
          class="absolute right-0 top-full mt-1 w-32 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 z-50"
        >
          <button
            v-for="loc in localeStore.availableLocales"
            :key="loc.code"
            :class="[
              'w-full px-3 py-2.5 text-left text-xs transition-colors touch-manipulation',
              localeStore.currentLocale === loc.code
                ? 'bg-[var(--color-bg)] text-[var(--color-accent)] font-medium'
                : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'
            ]"
            style="min-height: 44px"
            @click="switchLocale(loc.code)"
          >
            {{ loc.flag }} {{ loc.label }}
          </button>
        </div>
      </div>

      <!-- User menu -->
      <div class="relative" data-dropdown>
        <button
          class="flex items-center gap-1.5 px-2 py-1.5 text-xs rounded-md transition-colors hover:bg-white/10 touch-manipulation"
          :style="{ color: 'inherit', minHeight: '44px' }"
          :aria-expanded="userOpen"
          @click.stop="userOpen = !userOpen; localeOpen = false"
        >
          <User class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">{{ authStore.userName }}</span>
        </button>
        <div
          v-if="userOpen"
          class="absolute right-0 top-full mt-1 w-48 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 z-50"
        >
          <div class="px-3 py-2 border-b border-[var(--color-border)]">
            <p class="text-xs font-medium text-[var(--color-text-primary)]">{{ authStore.userName }}</p>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">{{ authStore.userRole }}</p>
          </div>
          <button
            class="w-full px-3 py-2.5 text-left text-xs text-[var(--color-error)] hover:bg-[var(--color-error-light)] flex items-center gap-2 transition-colors touch-manipulation"
            style="min-height: 44px"
            @click="authStore.logout()"
          >
            <LogOut class="w-3.5 h-3.5" :stroke-width="1.75" />
            Abmelden
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
