<script setup>
import { computed } from 'vue'
import { ShoppingCart, ChevronRight } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'

const props = defineProps({
  section: { type: Object, required: true },
})

const cartStore = useCartStore()
const v = computed(() => props.section.values || {})

// Aktiver Warenkorb für diese Section (nur relevant wenn dieser Typ gerade geladen ist)
const isActiveType = computed(() => cartStore.activeCartType === v.value.cart_type)
const itemCount = computed(() => isActiveType.value ? cartStore.itemCount : 0)

const totalFormatted = computed(() => {
  if (!isActiveType.value) return null
  const amount = cartStore.totalAmount
  if (amount == null) return null
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: cartStore.currency }).format(amount)
})

async function openCart() {
  // Lädt den korrekten Warenkorbtyp und öffnet den globalen Drawer
  if (v.value.cart_type) {
    await cartStore.fetchCart(v.value.cart_type)
  }
  cartStore.drawerOpen = true
}
</script>

<template>
  <!-- Warenkorb-Widget: Klick lädt den passenden Cart-Typ und öffnet den globalen Drawer -->
  <div
    class="flex items-center justify-between rounded-xl border px-4 py-3 cursor-pointer hover:shadow-sm transition-shadow"
    style="background: var(--site-surface, var(--color-surface)); border-color: var(--site-border, var(--color-border))"
    @click="openCart"
  >
    <div class="flex items-center gap-3">
      <div class="relative">
        <ShoppingCart class="w-5 h-5" style="color: var(--site-primary, var(--color-accent))" />
        <span
          v-if="itemCount > 0"
          class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full text-[10px] font-bold flex items-center justify-center text-white"
          style="background: var(--site-primary, var(--color-accent))"
        >{{ itemCount }}</span>
      </div>
      <div>
        <p class="text-sm font-semibold" style="color: var(--color-text-primary)">
          {{ v.header_text || 'Warenkorb' }}
        </p>
        <p class="text-xs" style="color: var(--color-text-tertiary)">
          <template v-if="itemCount === 0">Noch keine Artikel hinzugefügt</template>
          <template v-else>
            {{ itemCount }} Artikel
            <span v-if="totalFormatted"> · {{ totalFormatted }}</span>
          </template>
        </p>
      </div>
    </div>
    <ChevronRight class="w-4 h-4" style="color: var(--color-text-tertiary)" />
  </div>
</template>
