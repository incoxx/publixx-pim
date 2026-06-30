import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { cart as cartApi } from '@/api/cart'

export const useCartStore = defineStore('cart', () => {
  // Aktueller Warenkorb (inkl. Items und Konfiguration)
  const cartData = ref(null)
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref(null)

  // Aktiver Warenkorbtyp (technical_name)
  const activeCartType = ref(null)

  const isEmpty = computed(() => !cartData.value || cartData.value.item_count === 0)
  const itemCount = computed(() => cartData.value?.item_count ?? 0)
  const totalAmount = computed(() => cartData.value?.total_amount ?? null)
  const currency = computed(() => cartData.value?.currency ?? 'EUR')
  const cartType = computed(() => cartData.value?.cart_type ?? null)
  const items = computed(() => cartData.value?.items ?? [])

  async function fetchCart(cartTypeName) {
    if (!cartTypeName) return
    activeCartType.value = cartTypeName
    loading.value = true
    error.value = null
    try {
      const res = await cartApi.show(cartTypeName)
      cartData.value = res.data.data
    } catch (e) {
      error.value = e
    } finally {
      loading.value = false
    }
  }

  async function addItem(productId, quantity = 1, note = null) {
    if (!activeCartType.value) return
    loading.value = true
    try {
      const res = await cartApi.addItem(activeCartType.value, productId, quantity, note)
      cartData.value = res.data.data
    } finally {
      loading.value = false
    }
  }

  async function updateItem(productId, quantity, note) {
    if (!activeCartType.value) return
    loading.value = true
    try {
      const res = await cartApi.updateItem(activeCartType.value, productId, quantity, note)
      cartData.value = res.data.data
    } finally {
      loading.value = false
    }
  }

  async function removeItem(productId) {
    if (!activeCartType.value) return
    loading.value = true
    try {
      const res = await cartApi.removeItem(activeCartType.value, productId)
      cartData.value = res.data.data
    } finally {
      loading.value = false
    }
  }

  async function clearCart() {
    if (!activeCartType.value) return
    loading.value = true
    try {
      const res = await cartApi.clear(activeCartType.value)
      cartData.value = res.data.data
    } finally {
      loading.value = false
    }
  }

  async function submitCart(formData) {
    if (!activeCartType.value) return null
    submitting.value = true
    error.value = null
    try {
      const res = await cartApi.submit(activeCartType.value, formData)
      // Nach erfolgreicher Bestellung den lokalen Cart leeren
      cartData.value = null
      return res.data.data
    } catch (e) {
      error.value = e
      throw e
    } finally {
      submitting.value = false
    }
  }

  async function mergeAfterLogin() {
    try {
      await cartApi.merge()
      if (activeCartType.value) {
        await fetchCart(activeCartType.value)
      }
    } catch {
      // Merge-Fehler sind nicht kritisch
    }
  }

  // Prüft ob ein bestimmtes Produkt bereits im Warenkorb ist
  function isInCart(productId) {
    return items.value.some((i) => i.product_id === productId)
  }

  return {
    cartData,
    loading,
    submitting,
    error,
    activeCartType,
    isEmpty,
    itemCount,
    totalAmount,
    currency,
    cartType,
    items,
    fetchCart,
    addItem,
    updateItem,
    removeItem,
    clearCart,
    submitCart,
    mergeAfterLogin,
    isInCart,
  }
})
