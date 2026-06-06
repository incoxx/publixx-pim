<script setup>
import { useAuthStore } from '@/stores/auth'
import { useTabStore } from '@/stores/tabs'
import { useLocaleStore } from '@/stores/locale'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { Command, Globe, LogOut, Menu, Pin, PinOff, Sparkles, User } from 'lucide-vue-next'
import { useCopilotStore } from '@/stores/copilot'

const authStore = useAuthStore()
const copilotStore = useCopilotStore()
const tabStore = useTabStore()
const localeStore = useLocaleStore()
const { t, locale: i18nLocale } = useI18n()
const route = useRoute()

const pageTitle = computed(() => route.meta.title || '')
const isPinned = computed(() => tabStore.isRoutePinned(route))
const canPin = computed(() => route.name && route.name !== 'login' && route.name !== 'dashboard')

function switchLocale(code) {
  localeStore.setUiLocale(code)
  i18nLocale.value = code
}
</script>

<template>
  <header
    class="sticky top-0 z-20 flex items-center justify-between h-14 px-3 sm:px-6 bg-[var(--pim-toolbar-bg)]/90 backdrop-blur-md border-b border-[var(--pim-toolbar-border)]"
    :style="{ color: 'var(--pim-toolbar-text)' }"
  >
    <!-- Left: Hamburger (mobile) + Title -->
    <div class="flex items-center gap-3">
      <button
        class="md:hidden p-1.5 -ml-1 rounded-md transition-colors hover:bg-white/10"
        style="color: inherit"
        @click="authStore.toggleMobileSidebar()"
      >
        <Menu class="w-5 h-5" :stroke-width="1.75" />
      </button>
      <h1 class="font-semibold" style="color: inherit" :style="{ fontSize: 'var(--pim-toolbar-font-size)' }">{{ pageTitle }}</h1>
      <button
        v-if="canPin"
        class="p-1 rounded transition-colors hover:bg-white/10"
        :style="{ color: isPinned ? 'var(--pim-sidebar-active-text)' : 'inherit', opacity: isPinned ? 1 : 0.5 }"
        :title="isPinned ? 'Tab entfernen' : 'Als Tab anheften'"
        @click="tabStore.pinCurrentRoute(route)"
      >
        <Pin v-if="!isPinned" class="w-3.5 h-3.5" :stroke-width="1.75" />
        <PinOff v-else class="w-3.5 h-3.5" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Right: Actions -->
    <div class="flex items-center gap-2">
      <!-- Copilot toggle -->
      <button
        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border transition-colors hover:bg-white/10"
        :style="{ color: 'inherit', borderColor: 'var(--pim-toolbar-border)' }"
        :class="{ 'bg-white/10': copilotStore.open }"
        title="anyPIM Copilot"
        @click="copilotStore.toggle()"
      >
        <Sparkles class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Copilot</span>
      </button>

      <!-- Command palette trigger -->
      <button
        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border transition-colors hover:bg-white/10"
        :style="{ color: 'inherit', borderColor: 'var(--pim-toolbar-border)' }"
        @click="authStore.toggleCommandPalette()"
      >
        <Command class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">Suche</span>
        <span class="pim-kbd text-[10px] hidden sm:inline-flex">⌘K</span>
      </button>

      <!-- Locale switcher -->
      <div class="relative group">
        <button class="flex items-center gap-1 px-2 py-1.5 text-xs rounded-md transition-colors hover:bg-white/10" style="color: inherit">
          <Globe class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="uppercase">{{ localeStore.currentLocale }}</span>
        </button>
        <div class="absolute right-0 top-full mt-1 w-32 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 hidden group-hover:block">
          <button
            v-for="loc in localeStore.availableLocales"
            :key="loc.code"
            :class="[
              'w-full px-3 py-1.5 text-left text-xs transition-colors',
              localeStore.currentLocale === loc.code
                ? 'bg-[var(--color-bg)] text-[var(--color-accent)] font-medium'
                : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-bg)]'
            ]"
            @click="switchLocale(loc.code)"
          >
            {{ loc.flag }} {{ loc.label }}
          </button>
        </div>
      </div>

      <!-- User menu -->
      <div class="relative group">
        <button class="flex items-center gap-1.5 px-2 py-1.5 text-xs rounded-md transition-colors hover:bg-white/10" style="color: inherit">
          <User class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">{{ authStore.userName }}</span>
        </button>
        <div class="absolute right-0 top-full mt-1 w-48 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg py-1 hidden group-hover:block">
          <div class="px-3 py-2 border-b border-[var(--color-border)]">
            <p class="text-xs font-medium text-[var(--color-text-primary)]">{{ authStore.userName }}</p>
            <p class="text-[11px] text-[var(--color-text-tertiary)]">{{ authStore.userRole }}</p>
          </div>
          <button
            class="w-full px-3 py-1.5 text-left text-xs text-[var(--color-error)] hover:bg-[var(--color-error-light)] flex items-center gap-2 transition-colors"
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
