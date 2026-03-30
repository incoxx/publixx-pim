<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentPortalStore } from '@/stores/documentPortal'
import CountrySelector from '@/components/documentPortal/CountrySelector.vue'

const store = useDocumentPortalStore()
const router = useRouter()

onMounted(() => {
  store.fetchCountries()
})

function onCountrySelected(country) {
  store.selectCountry(country)
  router.push('/documentportal/search')
}
</script>

<template>
  <div class="dp-landing">
    <!-- Hero -->
    <section class="dp-hero">
      <div class="dp-hero__inner">
        <!-- Branding -->
        <div class="dp-hero__brand">
          <p class="dp-hero__subtitle">Digitale Produktdokumentation</p>
          <h1 class="dp-hero__title">Produktdokumentation digital abrufen</h1>
          <p class="dp-hero__desc">
            Greifen Sie auf umfassende Produktdokumentation, Sicherheitsinformationen
            und Bedienungsanleitungen zu.
          </p>

          <ul class="dp-checks">
            <li>
              <span class="dp-checks__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              Sofortiger digitaler Zugriff
            </li>
            <li>
              <span class="dp-checks__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              Mehrere Sprachen verfuegbar
            </li>
            <li>
              <span class="dp-checks__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              </span>
              Immer aktueller Inhalt
            </li>
          </ul>
        </div>

        <!-- Country Selection -->
        <div class="dp-hero__select">
          <CountrySelector
            :countries-by-region="store.countriesByRegion"
            :selected-country="store.selectedCountry"
            @select="onCountrySelected"
          />
        </div>
      </div>
    </section>

    <!-- Feature Bar -->
    <div class="dp-feature-bar">
      <div class="dp-feature-bar__inner">
        <div class="dp-feature-bar__item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="2" y1="12" x2="22" y2="12" /><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" /></svg>
          <span>Mehrsprachig</span>
        </div>
        <div class="dp-feature-bar__item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
          <span>PDF-Download</span>
        </div>
        <div class="dp-feature-bar__item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
          <span>Sicherheitsinformationen</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dp-hero {
  background: linear-gradient(135deg, var(--dp-surface, #f0f9ff) 0%, #fff 100%);
  border-bottom: 1px solid var(--dp-border, #bae6fd);
}
.dp-hero__inner {
  max-width: var(--dp-max-width, 1200px);
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 480px;
}
.dp-hero__brand {
  padding: 48px 48px 48px 32px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  border-right: 1px solid var(--dp-border, #bae6fd);
}
.dp-hero__subtitle {
  color: var(--dp-primary, #0891b2);
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 12px;
}
.dp-hero__title {
  color: var(--dp-dark, #0c4a6e);
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 12px;
}
.dp-hero__desc {
  color: #64748b;
  margin-bottom: 32px;
  max-width: 420px;
}
.dp-checks {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.dp-checks li {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.9375rem;
}
.dp-checks__icon {
  width: 24px;
  height: 24px;
  background: var(--dp-primary, #0891b2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.dp-checks__icon svg {
  width: 14px;
  height: 14px;
}
.dp-hero__select {
  padding: 48px 32px 48px 48px;
  display: flex;
  flex-direction: column;
}

/* Feature Bar */
.dp-feature-bar {
  background: linear-gradient(to right, var(--dp-surface, #f0f9ff), #fff);
  border-bottom: 1px solid var(--dp-border, #bae6fd);
  padding: 20px 32px;
}
.dp-feature-bar__inner {
  max-width: var(--dp-max-width, 1200px);
  margin: 0 auto;
  display: flex;
  justify-content: center;
  gap: 48px;
  flex-wrap: wrap;
}
.dp-feature-bar__item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--dp-dark, #0c4a6e);
}
.dp-feature-bar__item svg {
  width: 20px;
  height: 20px;
  color: var(--dp-primary, #0891b2);
}

@media (max-width: 1024px) {
  .dp-hero__inner { grid-template-columns: 1fr; min-height: auto; }
  .dp-hero__brand { border-right: none; border-bottom: 1px solid var(--dp-border); padding: 32px 24px; }
  .dp-hero__select { padding: 32px 24px; }
}
@media (max-width: 768px) {
  .dp-hero__title { font-size: 1.5rem; }
  .dp-hero__brand { padding: 24px 16px; }
  .dp-hero__select { padding: 24px 16px; }
  .dp-feature-bar__inner { gap: 24px; }
}
</style>
