<script setup>
import { ref } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()

const email = ref('')
const password = ref('')

async function onSubmit(e) {
  e.preventDefault()
  if (state.authLoading) return
  await actions.login(email.value.trim(), password.value)
  if (state.authenticated) {
    password.value = ''
  }
}
</script>

<template>
  <div v-if="state.requiresLogin" class="pxc-login">
    <div class="pxc-login__backdrop"></div>
    <form class="pxc-login__card" @submit="onSubmit">
      <img
        v-if="state.settings.logo_url"
        :src="state.settings.logo_url"
        :alt="state.settings.catalog_title || 'Logo'"
        class="pxc-login__logo"
      />
      <h2 class="pxc-login__title">{{ state.settings.catalog_title || 'Katalog' }}</h2>
      <p class="pxc-login__subtitle">Bitte melden Sie sich an, um den Katalog anzuzeigen.</p>

      <label class="pxc-login__label" for="pxc-login-email">E-Mail</label>
      <input
        id="pxc-login-email"
        v-model="email"
        type="email"
        class="pxc-login__input"
        autocomplete="email"
        required
        :disabled="state.authLoading"
      />

      <label class="pxc-login__label" for="pxc-login-password">Passwort</label>
      <input
        id="pxc-login-password"
        v-model="password"
        type="password"
        class="pxc-login__input"
        autocomplete="current-password"
        required
        :disabled="state.authLoading"
      />

      <p v-if="state.authError" class="pxc-login__error">{{ state.authError }}</p>

      <button type="submit" class="pxc-btn pxc-btn--primary pxc-login__submit" :disabled="state.authLoading">
        <span v-if="state.authLoading" class="pxc-login__loader" v-html="icons.loader"></span>
        <span>{{ state.authLoading ? 'Anmelden…' : 'Anmelden' }}</span>
      </button>
    </form>
  </div>
</template>
