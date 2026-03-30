<script setup>
import { onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDocumentPortalStore } from '@/stores/documentPortal'
import DocumentList from '@/components/documentPortal/DocumentList.vue'
import LanguageBadges from '@/components/documentPortal/LanguageBadges.vue'
import RegulatoryNotice from '@/components/documentPortal/RegulatoryNotice.vue'

const route = useRoute()
const router = useRouter()
const store = useDocumentPortalStore()

onMounted(() => {
  if (!store.selectedCountry) {
    router.replace('/documentportal')
    return
  }
  loadDocuments()
})

watch(() => route.params.id, loadDocuments)

async function loadDocuments() {
  const id = route.params.id
  if (id) {
    await store.fetchProductDocuments(id)
  }
}

function onLanguageSelect(lang) {
  store.setLanguage(lang)
  const id = route.params.id
  if (id) {
    store.fetchProductDocuments(id)
  }
}

function goBack() {
  router.push('/documentportal/search')
}
</script>

<template>
  <div class="dp-result">
    <!-- Zurueck -->
    <div class="dp-result__nav">
      <button class="dp-result__back" @click="goBack">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg>
        Zurueck zur Suche
      </button>
    </div>

    <!-- Loading -->
    <div v-if="store.documentsLoading" class="dp-result__loading">
      <div class="dp-skeleton" style="height: 200px" />
      <div class="dp-skeleton" style="height: 120px" />
    </div>

    <!-- Produktkarte + Dokumente -->
    <template v-else-if="store.productDocuments">
      <div class="dp-result__product">
        <!-- Produktbild + Info -->
        <div class="dp-result__header">
          <div class="dp-result__image">
            <img v-if="store.productDocuments.product.image_url" :src="store.productDocuments.product.image_url" :alt="store.productDocuments.product.name" />
            <div v-else class="dp-result__no-image">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
            </div>
          </div>
          <div class="dp-result__info">
            <div class="dp-result__badges">
              <span class="dp-badge dp-badge--sku">{{ store.productDocuments.product.sku }}</span>
              <span v-if="store.productDocuments.product.product_type" class="dp-badge dp-badge--type">{{ store.productDocuments.product.product_type }}</span>
            </div>
            <h1 class="dp-result__name">{{ store.productDocuments.product.name }}</h1>
            <p class="dp-result__meta">
              Dokumentation verfuegbar in {{ store.productDocuments.language_count }} Sprachen
            </p>
          </div>
        </div>

        <!-- Primaeres Dokument -->
        <div v-if="store.productDocuments.primary_document" class="dp-result__primary">
          <div class="dp-result__primary-header">
            <span class="dp-result__primary-bar"></span>
            <span class="dp-result__primary-label">
              {{ store.productDocuments.primary_document.document_type || 'Dokument' }}
              — {{ store.selectedLanguage?.toUpperCase() }}
            </span>
            <span class="dp-result__primary-badge">Aktuelle Laenderversion</span>
          </div>

          <a
            :href="store.productDocuments.primary_document.download_url"
            target="_blank"
            class="dp-result__primary-card"
          >
            <div class="dp-result__primary-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><polyline points="14 2 14 8 20 8" /></svg>
              <span>PDF</span>
            </div>
            <div class="dp-result__primary-info">
              <strong>{{ store.productDocuments.primary_document.file_name }}</strong>
              <span>{{ store.productDocuments.primary_document.title }}</span>
            </div>
            <button class="dp-result__download-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
              Herunterladen
            </button>
          </a>
        </div>

        <!-- Weitere Sprachversionen -->
        <div v-if="store.productDocuments.available_languages.length > 1" class="dp-result__languages">
          <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:middle;margin-right:6px"><circle cx="12" cy="12" r="10" /><line x1="2" y1="12" x2="22" y2="12" /><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" /></svg>
            Weitere Sprachversionen
          </h3>
          <LanguageBadges
            :languages="store.productDocuments.available_languages"
            :active-language="store.selectedLanguage"
            @select="onLanguageSelect"
          />
        </div>

        <!-- Alle Dokumente nach Typ -->
        <DocumentList :documents-by-type="store.productDocuments.documents_by_type" />

        <!-- Regulatorischer Hinweis -->
        <RegulatoryNotice :country="store.selectedCountry" />
      </div>
    </template>

    <!-- Kein Ergebnis -->
    <div v-else class="dp-result__empty">
      <p>Produkt nicht gefunden oder keine Dokumente verfuegbar.</p>
      <button class="dp-result__back-btn" @click="goBack">Zurueck zur Suche</button>
    </div>
  </div>
</template>

<style scoped>
.dp-result {
  max-width: var(--dp-max-width, 1200px);
  margin: 0 auto;
  padding: 24px 32px 48px;
}
.dp-result__nav {
  margin-bottom: 24px;
}
.dp-result__back {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--dp-primary, #0891b2);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
  padding: 0;
}
.dp-result__back svg {
  width: 16px;
  height: 16px;
}

.dp-result__product {
  background: #fff;
  border: 1px solid var(--dp-border, #bae6fd);
  border-radius: var(--dp-card-radius, 12px);
  overflow: hidden;
}

/* Header */
.dp-result__header {
  display: flex;
  gap: 24px;
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
}
.dp-result__image {
  width: 100px;
  height: 100px;
  flex-shrink: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dp-result__image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.dp-result__no-image svg {
  width: 40px;
  height: 40px;
  color: #cbd5e1;
}
.dp-result__badges {
  display: flex;
  gap: 6px;
  margin-bottom: 6px;
}
.dp-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}
.dp-badge--sku {
  background: var(--dp-primary, #0891b2);
  color: #fff;
}
.dp-badge--type {
  background: var(--dp-surface, #f0f9ff);
  color: var(--dp-dark, #0c4a6e);
  border: 1px solid var(--dp-border, #bae6fd);
}
.dp-result__name {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--dp-dark, #0c4a6e);
  margin-bottom: 4px;
}
.dp-result__meta {
  font-size: 0.875rem;
  color: #64748b;
}

/* Primaeres Dokument */
.dp-result__primary {
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
}
.dp-result__primary-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.dp-result__primary-bar {
  width: 4px;
  height: 20px;
  background: var(--dp-primary, #0891b2);
  border-radius: 2px;
}
.dp-result__primary-label {
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--dp-dark, #0c4a6e);
}
.dp-result__primary-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  background: var(--dp-dark, #0c4a6e);
  color: #fff;
}
.dp-result__primary-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border: 1px solid var(--dp-border, #bae6fd);
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s;
}
.dp-result__primary-card:hover {
  border-color: var(--dp-primary, #0891b2);
}
.dp-result__primary-icon {
  width: 48px;
  height: 56px;
  background: #fef2f2;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.dp-result__primary-icon svg {
  width: 24px;
  height: 24px;
  color: #ef4444;
}
.dp-result__primary-icon span {
  font-size: 0.5625rem;
  font-weight: 700;
  color: #ef4444;
  text-transform: uppercase;
}
.dp-result__primary-info {
  flex: 1;
  min-width: 0;
}
.dp-result__primary-info strong {
  display: block;
  font-size: 0.875rem;
  color: var(--dp-dark, #0c4a6e);
  margin-bottom: 2px;
}
.dp-result__primary-info span {
  font-size: 0.8125rem;
  color: #64748b;
}
.dp-result__download-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  background: var(--dp-download-bg, var(--dp-primary, #0891b2));
  color: var(--dp-download-text, #fff);
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  white-space: nowrap;
}
.dp-result__download-btn svg {
  width: 16px;
  height: 16px;
}
.dp-result__download-btn:hover {
  filter: brightness(1.1);
}

/* Sprachen */
.dp-result__languages {
  padding: 24px;
  border-bottom: 1px solid #f1f5f9;
}
.dp-result__languages h3 {
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--dp-dark, #0c4a6e);
  margin-bottom: 12px;
}

/* Skeleton */
.dp-result__loading {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.dp-skeleton {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: var(--dp-card-radius, 12px);
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.dp-result__empty {
  text-align: center;
  padding: 64px;
  color: #64748b;
}
.dp-result__back-btn {
  margin-top: 16px;
  padding: 10px 24px;
  background: var(--dp-primary, #0891b2);
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}

@media (max-width: 768px) {
  .dp-result { padding: 16px; }
  .dp-result__header { flex-direction: column; gap: 12px; }
  .dp-result__primary-card { flex-direction: column; align-items: flex-start; }
  .dp-result__download-btn { width: 100%; justify-content: center; }
}
</style>
