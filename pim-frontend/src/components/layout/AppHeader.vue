<script setup>
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { Command, Globe, LogOut, User } from 'lucide-vue-next'

const authStore = useAuthStore()
const localeStore = useLocaleStore()
const { t, locale: i18nLocale } = useI18n()
const route = useRoute()

const pageTitle = computed(() => route.meta.title || '')

function switchLocale(code) {
  localeStore.setUiLocale(code)
  i18nLocale.value = code
}
</script>

<template>
  <header class="sticky top-0 z-20 flex items-center justify-between h-14 px-6 bg-[var(--color-surface)]/80 backdrop-blur-md border-b border-[var(--color-border)]">
    <!-- Left: Title -->
    <div class="flex items-center gap-3">
      <h1 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ pageTitle }}</h1>
    </div>

    <!-- Right: Actions -->
    <div class="flex items-center gap-2">
      <!-- Command palette trigger -->
      <button
        class="pim-btn pim-btn-secondary text-xs gap-1.5"
        @click="authStore.toggleCommandPalette()"
      >
        <Command class="w-3.5 h-3.5" :stroke-width="1.75" />
        <span class="hidden sm:inline">{{ t('nav.search') }}</span>
        <span class="pim-kbd text-[10px]">⌘K</span>
      </button>

      <!-- Locale switcher -->
      <div class="relative group">
        <button class="pim-btn pim-btn-ghost text-xs gap-1">
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
        <button class="pim-btn pim-btn-ghost text-xs gap-1.5">
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
