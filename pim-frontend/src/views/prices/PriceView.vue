<script setup>
import { DollarSign } from 'lucide-vue-next'
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Preise</h2>
    <div class="pim-card p-12 text-center">
      <DollarSign class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-secondary)]">Preisverwaltung</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Preise werden pro Produkt in der Produktdetailansicht verwaltet.</p>
    </div>
  </div>
</template>
