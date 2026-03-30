<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDocumentPortalStore } from '@/stores/documentPortal'
import ProductDocumentCard from '@/components/documentPortal/ProductDocumentCard.vue'

const store = useDocumentPortalStore()
const router = useRouter()
const searchInput = ref('')
const inputEl = ref(null)

onMounted(() => {
  if (!store.selectedCountry) {
    router.replace('/documentportal')
    return
  }
  if (store.searchQuery) {
    searchInput.value = store.searchQuery
  }
  inputEl.value?.focus()
})

async function onSearch() {
  const q = searchInput.value.trim()
  if (q.length < 2) return
  await store.search(q)
}

function onProductClick(product) {
  router.push(`/documentportal/product/${product.id}`)
}
</script>

<template>
  <div class="dp-search">
    <!-- Suchbereich -->
    <section class="dp-search__hero">
      <h2>Dokumentation finden</h2>
      <p>Geben Sie die Produktnummer ein, um die zugehoerige Dokumentation abzurufen.</p>

      <form class="dp-search__form" @submit.prevent="onSearch">
        <div class="dp-search__input-wrap">
          <svg class="dp-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
          <input
            ref="inputEl"
            v-model="searchInput"
            type="text"
            placeholder="z.B. 10134-000"
            class="dp-search__input"
            @keyup.enter="onSearch"
          />
        </div>
        <button type="submit" class="dp-search__btn" :disabled="searchInput.trim().length < 2">
          Suchen
        </button>
      </form>

      <p class="dp-search__hint">
        Tipp: Versuchen Sie <code @click="searchInput = '10134-000'; onSearch()">10134-000</code>
        oder <code @click="searchInput = '10135-000'; onSearch()">10135-000</code>
      </p>
    </section>

    <!-- Ergebnisse -->
    <section v-if="store.loading || store.searchResults.length > 0 || (store.searchQuery && !store.loading)" class="dp-search__results">
      <div v-if="store.loading" class="dp-search__loading">
        <div v-for="i in 3" :key="i" class="dp-skeleton" />
      </div>

      <div v-else-if="store.searchResults.length > 0" class="dp-search__list">
        <ProductDocumentCard
          v-for="product in store.searchResults"
          :key="product.id"
          :product="product"
          @click="onProductClick(product)"
        />
      </div>

      <p v-else-if="store.searchQuery" class="dp-search__empty">
        Keine Produkte gefunden fuer <strong>{{ store.searchQuery }}</strong>
      </p>
    </section>
  </div>
</template>

<style scoped>
.dp-search__hero {
  background: linear-gradient(135deg, var(--dp-dark, #0c4a6e), #164e63);
  color: #fff;
  padding: 48px 32px;
  text-align: center;
}
.dp-search__hero h2 {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 8px;
}
.dp-search__hero > p {
  color: rgba(255,255,255,0.8);
  margin-bottom: 24px;
}
.dp-search__form {
  max-width: 560px;
  margin: 0 auto;
  display: flex;
  gap: 8px;
}
.dp-search__input-wrap {
  flex: 1;
  position: relative;
}
.dp-search__icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: #94a3b8;
}
.dp-search__input {
  width: 100%;
  padding: 14px 14px 14px 44px;
  border: none;
  border-radius: var(--dp-card-radius, 12px);
  font-size: 1rem;
  outline: none;
}
.dp-search__input:focus {
  box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.3);
}
.dp-search__btn {
  padding: 14px 28px;
  background: var(--dp-download-bg, var(--dp-primary, #0891b2));
  color: var(--dp-download-text, #fff);
  border: none;
  border-radius: var(--dp-card-radius, 12px);
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}
.dp-search__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.dp-search__btn:hover:not(:disabled) {
  filter: brightness(1.1);
}
.dp-search__hint {
  margin-top: 12px;
  font-size: 0.8125rem;
  color: rgba(255,255,255,0.5);
}
.dp-search__hint code {
  background: rgba(255,255,255,0.15);
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
}
.dp-search__hint code:hover {
  background: rgba(255,255,255,0.25);
}

.dp-search__results {
  max-width: var(--dp-max-width, 1200px);
  margin: 0 auto;
  padding: 32px;
}
.dp-search__list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.dp-search__empty {
  text-align: center;
  color: #64748b;
  padding: 48px;
}
.dp-search__loading {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.dp-skeleton {
  height: 100px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: var(--dp-card-radius, 12px);
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (max-width: 768px) {
  .dp-search__hero { padding: 32px 16px; }
  .dp-search__hero h2 { font-size: 1.25rem; }
  .dp-search__form { flex-direction: column; }
  .dp-search__results { padding: 16px; }
}
</style>
