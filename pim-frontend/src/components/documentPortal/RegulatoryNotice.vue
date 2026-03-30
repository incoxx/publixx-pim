<script setup>
defineProps({
  country: { type: Object, default: null },
})
</script>

<template>
  <div v-if="country" class="rn-root">
    <svg class="rn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></svg>
    <p>
      Die angezeigte Dokumentation entspricht der fuer
      <strong>{{ country.name }}</strong>
      gueltigen Version.
      Fuer andere Laender waehlen Sie bitte die entsprechende Laenderseite.
    </p>
  </div>
</template>

<style scoped>
.rn-root {
  margin: 20px 24px;
  padding: 16px 20px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
  font-size: 0.8125rem;
  color: #92400e;
  line-height: 1.5;
}
.rn-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  margin-top: 1px;
  color: #d97706;
}
.rn-root p {
  margin: 0;
}
.rn-root strong {
  font-weight: 700;
}
</style>
