<script setup>
import { computed, onMounted } from 'vue'
import { ShoppingCart, ChevronRight } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import CatalogCartDrawer from '@/components/catalog/CatalogCartDrawer.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const cartStore = useCartStore()
const v = computed(() => props.section.values || {})

onMounted(async () => {
  if (v.value.cart_type) {
    await cartStore.fetchCart(v.value.cart_type)
  }
})

const totalFormatted = computed(() => {
  const amount = cartStore.totalAmount
  if (amount == null) return null
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: cartStore.currency }).format(amount)
})
</script>

<template>
  <!-- Warenkorb-Widget: initialisiert den Cart-Store und rendert den Drawer -->
  <div>
    <div
      class="flex items-center justify-between rounded-xl border px-4 py-3 cursor-pointer hover:shadow-sm transition-shadow"
      style="background: var(--site-surface, var(--color-surface)); border-color: var(--site-border, var(--color-border))"
      @click="cartStore.drawerOpen = true"
    >
      <div class="flex items-center gap-3">
        <div class="relative">
          <ShoppingCart class="w-5 h-5" style="color: var(--site-primary, var(--color-accent))" />
          <span
            v-if="cartStore.itemCount > 0"
            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full text-[10px] font-bold flex items-center justify-center text-white"
            style="background: var(--site-primary, var(--color-accent))"
          >{{ cartStore.itemCount }}</span>
        </div>
        <div>
          <p class="text-sm font-semibold" style="color: var(--color-text-primary)">
            {{ v.header_text || cartStore.cartType?.name_de || 'Warenkorb' }}
          </p>
          <p class="text-xs" style="color: var(--color-text-tertiary)">
            <template v-if="cartStore.isEmpty">Noch keine Artikel hinzugefügt</template>
            <template v-else>
              {{ cartStore.itemCount }} {{ cartStore.itemCount === 1 ? 'Artikel' : 'Artikel' }}
              <span v-if="totalFormatted"> · {{ totalFormatted }}</span>
            </template>
          </p>
        </div>
      </div>
      <ChevronRight class="w-4 h-4" style="color: var(--color-text-tertiary)" />
    </div>

    <!-- Drawer teleportiert sich zu <body> — nur eine Instanz pro Seite nötig -->
    <CatalogCartDrawer />
  </div>
</template>
