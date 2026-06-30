<script setup>
import { computed, ref } from 'vue'
import { ShoppingCart, Check, Loader } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'

const props = defineProps({
  productId: { type: String, required: true },
  quantity: { type: Number, default: 1 },
  cartTypeName: { type: String, default: null },
  size: { type: String, default: 'sm' }, // xs | sm | md
})

const emit = defineEmits(['added'])

const cartStore = useCartStore()

// Lokaler Busy-State: NICHT an den globalen cartStore.loading koppeln, sonst
// deaktiviert jede andere Cart-Operation (z.B. das Vorab-Laden des Warenkorbs)
// sämtliche Buttons gleichzeitig.
const busy = ref(false)

const activeCartType = computed(() => props.cartTypeName ?? cartStore.activeCartType)
const inCart = computed(() => cartStore.isInCart(props.productId))

async function handleClick(e) {
  e.stopPropagation()
  e.preventDefault()
  if (!activeCartType.value || busy.value) return

  busy.value = true
  try {
    // Sicherstellen, dass der richtige Warenkorbtyp aktiv ist
    if (cartStore.activeCartType !== activeCartType.value) {
      await cartStore.fetchCart(activeCartType.value)
    }

    if (inCart.value) {
      cartStore.drawerOpen = true
      return
    }

    await cartStore.addItem(props.productId, props.quantity)
    emit('added', props.productId)
    cartStore.drawerOpen = true
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button
    type="button"
    class="btn btn-circle text-white"
    :class="{
      'btn-xs': size === 'xs',
      'btn-sm': size === 'sm',
      'btn-md': size === 'md',
      'btn-success': inCart,
      'btn-primary': !inCart,
    }"
    :disabled="busy || !activeCartType"
    :title="inCart ? 'Im Warenkorb' : 'In den Warenkorb'"
    @click="handleClick"
  >
    <Loader v-if="busy" class="w-4 h-4 animate-spin" />
    <Check v-else-if="inCart" class="w-4 h-4" />
    <ShoppingCart v-else class="w-4 h-4" />
  </button>
</template>
