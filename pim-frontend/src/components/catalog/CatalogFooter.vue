<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <footer class="footer footer-center p-4 bg-base-100 border-t border-base-300 text-base-content/50 text-xs">
    <p>{{ t('catalog.poweredBy') }}</p>
  </footer>
</template>
