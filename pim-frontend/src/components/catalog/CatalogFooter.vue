<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '@/stores/catalog'

const { t } = useI18n()
const store = useCatalogStore()

const hasImpressum = computed(() => store.themeSettings.impressum_url || store.themeSettings.impressum_text)
const hasKontakt = computed(() => store.themeSettings.kontakt_url || store.themeSettings.kontakt_text)

function impressumHref() {
  if (store.themeSettings.impressum_text) return '/preview/impressum'
  return store.themeSettings.impressum_url
}

function kontaktHref() {
  if (store.themeSettings.kontakt_text) return '/preview/kontakt'
  return store.themeSettings.kontakt_url
}

function isExternal(url) {
  return url && (url.startsWith('http://') || url.startsWith('https://'))
}
</script>

<template>
  <footer class="footer footer-center p-4 bg-base-100 border-t border-base-300 text-base-content/50 text-xs">
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
      <p>{{ store.themeSettings.footer_text || t('catalog.poweredBy') }}</p>
      <div v-if="hasImpressum || hasKontakt" class="flex gap-3">
        <a
          v-if="hasImpressum"
          :href="impressumHref()"
          :target="isExternal(impressumHref()) ? '_blank' : undefined"
          :rel="isExternal(impressumHref()) ? 'noopener noreferrer' : undefined"
          class="hover:text-base-content/70 hover:underline transition-colors"
        >
          Impressum
        </a>
        <a
          v-if="hasKontakt"
          :href="kontaktHref()"
          :target="isExternal(kontaktHref()) ? '_blank' : undefined"
          :rel="isExternal(kontaktHref()) ? 'noopener noreferrer' : undefined"
          class="hover:text-base-content/70 hover:underline transition-colors"
        >
          Kontakt
        </a>
      </div>
    </div>
  </footer>
</template>
