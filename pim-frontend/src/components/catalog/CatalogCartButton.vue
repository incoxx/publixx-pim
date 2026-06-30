<script setup>
import { computed, inject } from 'vue'
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
const cartDrawerOpen = inject('cartDrawerOpen', null)

const activeCartType = computed(() => props.cartTypeName ?? cartStore.activeCartType)
const inCart = computed(() => cartStore.isInCart(props.productId))
const loading = computed(() => cartStore.loading)

async function handleClick(e) {
  e.stopPropagation()
  if (!activeCartType.value || loading.value) return

  if (!cartStore.activeCartType) {
    await cartStore.fetchCart(activeCartType.value)
  }

  if (inCart.value) {
    if (cartDrawerOpen) cartDrawerOpen.value = true
    return
  }

  await cartStore.addItem(props.productId, props.quantity)
  emit('added', props.productId)
  if (cartDrawerOpen) cartDrawerOpen.value = true
}
</script>

<template>
  <button
    class="btn btn-circle text-white"
    :class="{
      'btn-xs': size === 'xs',
      'btn-sm': size === 'sm',
      'btn-md': size === 'md',
      'btn-success': inCart,
      'btn-primary': !inCart,
    }"
    :disabled="loading || !activeCartType"
    :title="inCart ? 'Im Warenkorb' : 'In den Warenkorb'"
    @click="handleClick"
  >
    <Loader v-if="loading" class="w-4 h-4 animate-spin" />
    <Check v-else-if="inCart" class="w-4 h-4" />
    <ShoppingCart v-else class="w-4 h-4" />
  </button>
</template>
