<script setup>
import { useLocaleStore } from '@/stores/locale'
import { Globe, Palette } from 'lucide-vue-next'
const localeStore = useLocaleStore()
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Einstellungen</h2>
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Globe class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">Sprache</h3></div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">UI-Sprache</label>
        <select class="pim-input max-w-xs" :value="localeStore.currentLocale" @change="localeStore.setUiLocale($event.target.value)">
          <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Aktive Datensprachen</label>
        <div class="flex gap-2">
          <label v-for="loc in localeStore.availableLocales" :key="loc.code" class="flex items-center gap-1.5 text-xs cursor-pointer">
            <input type="checkbox" :checked="localeStore.activeDataLocales.includes(loc.code)" @change="localeStore.toggleDataLocale(loc.code)" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            {{ loc.label }}
          </label>
        </div>
      </div>
    </div>
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Palette class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">Darstellung</h3></div>
      <p class="text-xs text-[var(--color-text-tertiary)]">Weitere Einstellungen werden hier konfiguriert.</p>
    </div>
  </div>
</template>
