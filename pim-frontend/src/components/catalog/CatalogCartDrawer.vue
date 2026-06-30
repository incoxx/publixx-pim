<script setup>
import { ref, computed, watch } from 'vue'
import { ShoppingCart, Trash2, X, Package, Plus, Minus, ChevronRight } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'

const cartStore = useCartStore()

// Writable computed auf den globalen Store-State (ersetzt provide/inject)
const cartDrawerOpen = computed({
  get: () => cartStore.drawerOpen,
  set: (val) => { cartStore.drawerOpen = val },
})

const STEPS = ['cart', 'address', 'payment', 'confirm']
const step = ref('cart')

// Address form — built from cartType.address_types if available; fallback to simple fields
const billingAddress = ref({})
const shippingAddress = ref({})
const useSameAddress = ref(true)
const selectedPaymentTypeId = ref(null)
const customerNotes = ref('')
const submitting = ref(false)
const submitError = ref(null)
const orderSuccess = ref(null)

const cartType = computed(() => cartStore.cartType)
const items = computed(() => cartStore.items)
const showPrices = computed(() => cartType.value?.show_prices)
const showQuantity = computed(() => cartType.value?.show_quantity)
const showTotal = computed(() => cartType.value?.show_total)
const allowCheckout = computed(() => cartType.value?.allow_checkout)

const totalFormatted = computed(() => {
  const amount = cartStore.totalAmount
  if (amount == null) return null
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: cartStore.currency }).format(amount)
})

function formatPrice(price, currency) {
  if (price == null) return null
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: currency || 'EUR' }).format(price)
}

function close() {
  cartDrawerOpen.value = false
  step.value = 'cart'
  orderSuccess.value = null
  submitError.value = null
}

function reset() {
  step.value = 'cart'
  billingAddress.value = {}
  shippingAddress.value = {}
  useSameAddress.value = true
  selectedPaymentTypeId.value = null
  customerNotes.value = ''
  orderSuccess.value = null
  submitError.value = null
}

watch(() => cartDrawerOpen.value, (open) => {
  if (open && cartStore.activeCartType) {
    cartStore.fetchCart(cartStore.activeCartType)
  }
})

async function changeQuantity(item, delta) {
  const newQty = Number(item.quantity) + delta
  if (newQty <= 0) {
    await cartStore.removeItem(item.product_id)
  } else {
    await cartStore.updateItem(item.product_id, newQty, item.note)
  }
}

async function removeItem(item) {
  await cartStore.removeItem(item.product_id)
}

function goToAddress() {
  if (cartStore.isEmpty) return
  step.value = 'address'
}

function goToPayment() {
  step.value = 'payment'
}

async function submitOrder() {
  submitting.value = true
  submitError.value = null
  try {
    const formData = {
      billing_address: billingAddress.value,
      shipping_address: useSameAddress.value ? billingAddress.value : shippingAddress.value,
      payment_type_id: selectedPaymentTypeId.value,
      notes: customerNotes.value || undefined,
    }
    const result = await cartStore.submitCart(formData)
    orderSuccess.value = result
    step.value = 'confirm'
  } catch (e) {
    submitError.value = e.response?.data?.message ?? 'Fehler beim Absenden. Bitte erneut versuchen.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer-overlay">
      <div
        v-if="cartDrawerOpen"
        class="fixed inset-0 z-50 flex justify-end"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <!-- Drawer -->
        <div class="relative bg-base-100 w-[85vw] max-w-md min-h-full border-l border-base-300 flex flex-col shadow-2xl z-10">

          <!-- Header -->
          <div class="p-4 border-b border-base-300 flex items-center justify-between shrink-0">
            <h2 class="font-semibold text-sm flex items-center gap-2">
              <ShoppingCart class="w-4 h-4 text-primary" />
              {{ cartType?.name_de ?? 'Warenkorb' }}
              <span v-if="cartStore.itemCount > 0" class="badge badge-sm badge-ghost">
                {{ cartStore.itemCount }}
              </span>
            </h2>
            <button class="btn btn-ghost btn-sm btn-circle" @click="close">
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Step: Cart -->
          <template v-if="step === 'cart'">
            <div class="flex-1 overflow-y-auto">
              <div v-if="cartStore.isEmpty" class="flex flex-col items-center justify-center py-16 px-6 text-center">
                <ShoppingCart class="w-10 h-10 text-base-content/15 mb-4" />
                <p class="text-sm font-medium text-base-content/50 mb-1">Warenkorb ist leer</p>
                <p class="text-xs text-base-content/30">Produkte hinzufügen und hier bestellen.</p>
              </div>

              <div v-else class="p-2 space-y-2">
                <div
                  v-for="item in items"
                  :key="item.product_id"
                  class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200 transition-colors"
                >
                  <div class="w-12 h-12 flex-none rounded-lg bg-base-200 overflow-hidden">
                    <img
                      v-if="item.image_url"
                      :src="item.image_url"
                      :alt="item.name"
                      class="object-contain w-full h-full p-1"
                    />
                    <div v-else class="flex items-center justify-center w-full h-full">
                      <Package class="w-5 h-5 text-base-content/15" />
                    </div>
                  </div>

                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium line-clamp-1">{{ item.name }}</p>
                    <p class="text-[10px] text-base-content/50 font-mono">{{ item.sku }}</p>
                    <p v-if="showPrices && item.unit_price != null" class="text-xs font-bold text-primary">
                      {{ formatPrice(item.unit_price, item.currency) }}
                    </p>
                  </div>

                  <!-- Quantity controls -->
                  <div v-if="showQuantity" class="flex items-center gap-1">
                    <button class="btn btn-xs btn-ghost btn-circle" @click="changeQuantity(item, -1)">
                      <Minus class="w-3 h-3" />
                    </button>
                    <span class="text-xs w-6 text-center">{{ item.quantity }}</span>
                    <button class="btn btn-xs btn-ghost btn-circle" @click="changeQuantity(item, 1)">
                      <Plus class="w-3 h-3" />
                    </button>
                  </div>

                  <div v-else class="text-xs text-base-content/50">{{ item.quantity }}×</div>

                  <button
                    class="btn btn-ghost btn-xs btn-circle flex-none text-base-content/30 hover:text-error"
                    @click="removeItem(item)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>

            <div v-if="!cartStore.isEmpty" class="p-3 border-t border-base-300 space-y-2 shrink-0">
              <div v-if="showTotal && totalFormatted" class="flex justify-between text-sm font-semibold px-1">
                <span>Gesamt</span>
                <span class="text-primary">{{ totalFormatted }}</span>
              </div>

              <button
                v-if="allowCheckout"
                class="btn btn-primary btn-sm w-full gap-1 text-white"
                @click="goToAddress"
              >
                Zur Bestellung
                <ChevronRight class="w-4 h-4" />
              </button>

              <button class="btn btn-outline btn-error btn-sm w-full gap-1" @click="cartStore.clearCart()">
                <Trash2 class="w-3.5 h-3.5" />
                Leeren
              </button>
            </div>
          </template>

          <!-- Step: Address -->
          <template v-else-if="step === 'address'">
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
              <h3 class="font-medium text-sm">Rechnungsadresse</h3>

              <div class="grid grid-cols-2 gap-3">
                <div class="form-control col-span-2">
                  <label class="label label-text text-xs">Firma</label>
                  <input v-model="billingAddress.company" class="input input-bordered input-sm" />
                </div>
                <div class="form-control">
                  <label class="label label-text text-xs">Vorname *</label>
                  <input v-model="billingAddress.first_name" class="input input-bordered input-sm" required />
                </div>
                <div class="form-control">
                  <label class="label label-text text-xs">Nachname *</label>
                  <input v-model="billingAddress.last_name" class="input input-bordered input-sm" required />
                </div>
                <div class="form-control col-span-2">
                  <label class="label label-text text-xs">Straße + Nr. *</label>
                  <input v-model="billingAddress.street" class="input input-bordered input-sm" required />
                </div>
                <div class="form-control w-28">
                  <label class="label label-text text-xs">PLZ *</label>
                  <input v-model="billingAddress.zip" class="input input-bordered input-sm" required />
                </div>
                <div class="form-control flex-1">
                  <label class="label label-text text-xs">Ort *</label>
                  <input v-model="billingAddress.city" class="input input-bordered input-sm" required />
                </div>
                <div class="form-control col-span-2">
                  <label class="label label-text text-xs">E-Mail *</label>
                  <input v-model="billingAddress.email" type="email" class="input input-bordered input-sm" required />
                </div>
                <div class="form-control col-span-2">
                  <label class="label label-text text-xs">Telefon</label>
                  <input v-model="billingAddress.phone" type="tel" class="input input-bordered input-sm" />
                </div>
              </div>

              <div class="form-control flex-row items-center gap-2 pt-1">
                <input type="checkbox" v-model="useSameAddress" class="checkbox checkbox-sm checkbox-primary" />
                <span class="text-sm">Lieferadresse = Rechnungsadresse</span>
              </div>

              <template v-if="!useSameAddress">
                <h3 class="font-medium text-sm pt-2">Lieferadresse</h3>
                <div class="grid grid-cols-2 gap-3">
                  <div class="form-control col-span-2">
                    <label class="label label-text text-xs">Firma</label>
                    <input v-model="shippingAddress.company" class="input input-bordered input-sm" />
                  </div>
                  <div class="form-control">
                    <label class="label label-text text-xs">Vorname *</label>
                    <input v-model="shippingAddress.first_name" class="input input-bordered input-sm" />
                  </div>
                  <div class="form-control">
                    <label class="label label-text text-xs">Nachname *</label>
                    <input v-model="shippingAddress.last_name" class="input input-bordered input-sm" />
                  </div>
                  <div class="form-control col-span-2">
                    <label class="label label-text text-xs">Straße + Nr. *</label>
                    <input v-model="shippingAddress.street" class="input input-bordered input-sm" />
                  </div>
                  <div class="form-control w-28">
                    <label class="label label-text text-xs">PLZ *</label>
                    <input v-model="shippingAddress.zip" class="input input-bordered input-sm" />
                  </div>
                  <div class="form-control flex-1">
                    <label class="label label-text text-xs">Ort *</label>
                    <input v-model="shippingAddress.city" class="input input-bordered input-sm" />
                  </div>
                </div>
              </template>

              <div class="form-control">
                <label class="label label-text text-xs">Anmerkungen</label>
                <textarea v-model="customerNotes" class="textarea textarea-bordered textarea-sm" rows="2" placeholder="Hinweise zur Bestellung…"></textarea>
              </div>
            </div>

            <div class="p-3 border-t border-base-300 flex gap-2 shrink-0">
              <button class="btn btn-sm btn-ghost" @click="step = 'cart'">Zurück</button>
              <button class="btn btn-sm btn-primary text-white flex-1" @click="goToPayment">
                Weiter
                <ChevronRight class="w-4 h-4" />
              </button>
            </div>
          </template>

          <!-- Step: Payment -->
          <template v-else-if="step === 'payment'">
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
              <h3 class="font-medium text-sm">Zahlungsart wählen</h3>

              <div v-if="cartType?.payment_types?.length" class="space-y-2">
                <label
                  v-for="pt in cartType.payment_types"
                  :key="pt.id"
                  class="flex items-start gap-3 p-3 border border-base-300 rounded-lg cursor-pointer hover:border-primary"
                  :class="{ 'border-primary bg-primary/5': selectedPaymentTypeId === pt.id }"
                >
                  <input type="radio" v-model="selectedPaymentTypeId" :value="pt.id" class="radio radio-primary radio-sm mt-0.5" />
                  <div>
                    <div class="text-sm font-medium">{{ pt.name_de }}</div>
                    <div v-if="pt.description_de" class="text-xs opacity-60">{{ pt.description_de }}</div>
                  </div>
                </label>
              </div>

              <div v-else class="text-sm opacity-50">Keine Zahlungsarten konfiguriert.</div>
            </div>

            <div class="p-3 border-t border-base-300 flex gap-2 shrink-0">
              <button class="btn btn-sm btn-ghost" @click="step = 'address'">Zurück</button>
              <button
                class="btn btn-sm btn-primary text-white flex-1"
                :disabled="submitting"
                @click="submitOrder"
              >
                {{ submitting ? 'Wird gesendet…' : 'Jetzt bestellen' }}
              </button>
            </div>

            <div v-if="submitError" class="px-4 pb-3 shrink-0">
              <div class="alert alert-error text-sm">{{ submitError }}</div>
            </div>
          </template>

          <!-- Step: Confirm -->
          <template v-else-if="step === 'confirm'">
            <div class="flex-1 flex flex-col items-center justify-center px-8 text-center gap-4">
              <div class="w-16 h-16 rounded-full bg-success/20 flex items-center justify-center">
                <ShoppingCart class="w-8 h-8 text-success" />
              </div>
              <h3 class="text-lg font-semibold">Vielen Dank!</h3>
              <p class="text-sm opacity-70">
                Ihre Bestellung <strong class="font-mono">{{ orderSuccess?.order_number }}</strong> wurde erfolgreich übermittelt.
              </p>
              <p class="text-xs opacity-50">Sie erhalten in Kürze eine Bestätigung.</p>
            </div>
            <div class="p-4 border-t border-base-300 shrink-0">
              <button class="btn btn-primary btn-sm w-full text-white" @click="() => { reset(); close() }">Schließen</button>
            </div>
          </template>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-overlay-enter-active,
.drawer-overlay-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-overlay-enter-from,
.drawer-overlay-leave-to {
  opacity: 0;
}
.drawer-overlay-enter-active .relative,
.drawer-overlay-leave-active .relative {
  transition: transform 0.25s ease;
}
.drawer-overlay-enter-from .relative,
.drawer-overlay-leave-to .relative {
  transform: translateX(100%);
}
</style>
