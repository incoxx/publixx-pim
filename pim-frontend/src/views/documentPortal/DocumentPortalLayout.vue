<script setup>
import { ref, computed, onMounted } from 'vue'
import { useDocumentPortalStore } from '@/stores/documentPortal'
import { useThemeApplicator } from '@/composables/useThemeApplicator'

const store = useDocumentPortalStore()
const themeRoot = ref(null)
const themeSettingsRef = computed(() => store.settings)

useThemeApplicator(themeRoot, themeSettingsRef)

onMounted(async () => {
  await store.fetchSettings()
})
</script>

<template>
  <div ref="themeRoot" class="dp-root" :style="{ fontSize: store.settings.font_body_size || '0.875rem' }">
    <!-- Header -->
    <header class="dp-header">
      <div class="dp-header__inner">
        <a href="/documentportal" class="dp-header__logo" @click.prevent="$router.push('/documentportal')">
          <img v-if="store.settings.logo_url" :src="store.settings.logo_url" alt="Logo" class="dp-header__logo-img" />
          <div v-else class="dp-header__logo-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            </svg>
          </div>
          <span class="dp-header__logo-text">{{ store.settings.catalog_title || 'Dokumentenportal' }}</span>
        </a>

        <div class="dp-header__right">
          <span v-if="store.selectedCountry" class="dp-header__country" @click="$router.push('/documentportal')">
            {{ store.selectedCountry.name }}
            <button class="dp-header__country-change" title="Land wechseln">&times;</button>
          </span>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="dp-main">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="dp-footer">
      <span>{{ store.settings.footer_text || '\u00A9 ' + new Date().getFullYear() }}</span>
      <span v-if="store.settings.impressum_url"> · <a :href="store.settings.impressum_url">Impressum</a></span>
      <span v-if="store.settings.kontakt_url"> · <a :href="store.settings.kontakt_url">Kontakt</a></span>
    </footer>
  </div>
</template>

<style scoped>
.dp-root {
  --dp-primary: var(--color-primary, #0891b2);
  --dp-accent: var(--color-accent, #06b6d4);
  --dp-dark: #0c4a6e;
  --dp-surface: #f0f9ff;
  --dp-border: #bae6fd;
  --dp-card-radius: 12px;
  --dp-max-width: 1200px;
  --dp-download-bg: var(--dp-primary);
  --dp-download-text: #fff;

  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: var(--catalog-font, system-ui, -apple-system, sans-serif);
  color: var(--dp-dark);
  background: #fff;
}

.dp-header {
  background: var(--dp-dark);
  color: #fff;
  padding: 12px 24px;
}
.dp-header__inner {
  max-width: var(--dp-max-width);
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.dp-header__logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: #fff;
}
.dp-header__logo-img {
  height: 32px;
  width: auto;
}
.dp-header__logo-icon {
  width: 36px;
  height: 36px;
  background: var(--dp-primary);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dp-header__logo-icon svg {
  width: 20px;
  height: 20px;
}
.dp-header__logo-text {
  font-size: 1.125rem;
  font-weight: 700;
}
.dp-header__country {
  font-size: 0.875rem;
  background: rgba(255,255,255,0.1);
  padding: 4px 12px;
  border-radius: 6px;
  cursor: pointer;
}
.dp-header__country-change {
  background: none;
  border: none;
  color: rgba(255,255,255,0.6);
  font-size: 1rem;
  cursor: pointer;
  margin-left: 4px;
}

.dp-main {
  flex: 1;
}

.dp-footer {
  background: var(--dp-dark);
  color: rgba(255,255,255,0.6);
  text-align: center;
  padding: 16px 24px;
  font-size: 0.8125rem;
}
.dp-footer a {
  color: rgba(255,255,255,0.8);
  text-decoration: none;
}
.dp-footer a:hover {
  text-decoration: underline;
}
</style>
