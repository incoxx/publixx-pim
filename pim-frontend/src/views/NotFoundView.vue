<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-24">
    <p class="text-6xl font-bold text-[var(--color-border-strong)] mb-4">404</p>
    <p class="text-sm text-[var(--color-text-secondary)] mb-6">Seite nicht gefunden</p>
    <button class="pim-btn pim-btn-primary" @click="router.push('/products')">Zur Startseite</button>
  </div>
</template>
