<script setup>
import LanguageBadges from './LanguageBadges.vue'

defineProps({
  product: { type: Object, required: true },
})
defineEmits(['click'])
</script>

<template>
  <div class="pdc-card" @click="$emit('click', product)">
    <!-- Bild -->
    <div class="pdc-card__image">
      <img v-if="product.image_url" :src="product.image_url" :alt="product.name" loading="lazy" />
      <div v-else class="pdc-card__no-image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
      </div>
    </div>

    <!-- Inhalt -->
    <div class="pdc-card__body">
      <div class="pdc-card__badges">
        <span class="pdc-badge pdc-badge--sku">{{ product.sku }}</span>
        <span v-if="product.product_type" class="pdc-badge pdc-badge--type">{{ product.product_type }}</span>
      </div>
      <h3 class="pdc-card__name">{{ product.name }}</h3>
      <p class="pdc-card__meta">
        Dokumentation verfuegbar in {{ product.language_count || 0 }} Sprachen
        · {{ product.document_count || 0 }} Dokumente
      </p>
      <LanguageBadges v-if="product.available_languages?.length" :languages="product.available_languages" :compact="true" />
    </div>

    <!-- Pfeil -->
    <div class="pdc-card__action">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>
    </div>
  </div>
</template>

<style scoped>
.pdc-card {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px;
  border: 1px solid var(--dp-border, #bae6fd);
  border-radius: var(--dp-card-radius, 12px);
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pdc-card:hover {
  border-color: var(--dp-primary, #0891b2);
  box-shadow: 0 4px 12px rgba(8, 145, 178, 0.08);
}
.pdc-card__image {
  width: 80px;
  height: 80px;
  flex-shrink: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
}
.pdc-card__image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.pdc-card__no-image svg {
  width: 32px;
  height: 32px;
  color: #cbd5e1;
}
.pdc-card__body {
  flex: 1;
  min-width: 0;
}
.pdc-card__badges {
  display: flex;
  gap: 6px;
  margin-bottom: 4px;
}
.pdc-badge {
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}
.pdc-badge--sku {
  background: var(--dp-primary, #0891b2);
  color: #fff;
}
.pdc-badge--type {
  background: var(--dp-surface, #f0f9ff);
  color: var(--dp-dark, #0c4a6e);
  border: 1px solid var(--dp-border, #bae6fd);
}
.pdc-card__name {
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--dp-dark, #0c4a6e);
  margin-bottom: 2px;
}
.pdc-card__meta {
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 6px;
}
.pdc-card__action {
  flex-shrink: 0;
}
.pdc-card__action svg {
  width: 20px;
  height: 20px;
  color: var(--dp-primary, #0891b2);
  opacity: 0.4;
}
.pdc-card:hover .pdc-card__action svg {
  opacity: 1;
}

@media (max-width: 640px) {
  .pdc-card { flex-direction: column; align-items: flex-start; }
  .pdc-card__action { display: none; }
}
</style>
