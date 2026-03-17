<script setup>
/**
 * Standalone wishlist button with optional count badge.
 * Place anywhere via data-catalog="wishlist-button"
 * Opens the wishlist drawer (from WishlistWidget).
 */
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, getters } = useStore()

function openWishlist() {
  // Dispatch a custom event that the WishlistWidget listens for
  window.dispatchEvent(new CustomEvent('pxc:open-wishlist'))
}
</script>

<template>
  <button class="pxc-wishlist-btn" @click="openWishlist">
    <span v-html="icons.heart"></span>
    <span>Merkliste</span>
    <span v-if="getters.wishlistCount.value > 0" class="pxc-wishlist-btn__badge">
      {{ getters.wishlistCount.value }}
    </span>
  </button>
</template>
