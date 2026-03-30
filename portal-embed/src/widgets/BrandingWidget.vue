<script setup>
import { useStore } from '../store.js'
const { state, getters } = useStore()
</script>

<template>
  <div v-if="state.loading" class="pe-loading">
    <div class="pe-skeleton" style="height:16px;width:200px;margin-bottom:12px"></div>
    <div class="pe-skeleton" style="height:36px;width:80%;margin-bottom:12px"></div>
    <div class="pe-skeleton" style="height:60px;margin-bottom:24px"></div>
  </div>
  <div class="pe-branding" v-else-if="getters.branding.value">
    <p v-if="getters.branding.value.subtitle" class="pe-branding__subtitle">{{ getters.branding.value.subtitle }}</p>
    <h1 v-if="getters.branding.value.title" class="pe-branding__title">{{ getters.branding.value.title }}</h1>
    <p v-if="getters.branding.value.hero_text" class="pe-branding__desc">{{ getters.branding.value.hero_text }}</p>

    <ul v-if="getters.branding.value.features?.length" class="pe-branding__features">
      <li v-for="(feature, i) in getters.branding.value.features" :key="i">
        <span class="pe-branding__check">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </span>
        {{ feature }}
      </li>
    </ul>
  </div>
</template>
