<script setup>
import DocumentRow from './DocumentRow.vue'

defineProps({
  documentsByType: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div v-for="(docs, docType) in documentsByType" :key="docType" class="dl-section">
    <div class="dl-section__header">
      <span class="dl-section__bar"></span>
      <span class="dl-section__label">{{ docType }}</span>
    </div>
    <div class="dl-section__list">
      <DocumentRow v-for="doc in docs" :key="doc.id" :document="doc" />
    </div>
  </div>
</template>

<style scoped>
.dl-section {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
}
.dl-section:last-child {
  border-bottom: none;
}
.dl-section__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.dl-section__bar {
  width: 4px;
  height: 18px;
  background: var(--dp-accent, #06b6d4);
  border-radius: 2px;
}
.dl-section__label {
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--dp-dark, #0c4a6e);
}
.dl-section__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
</style>
