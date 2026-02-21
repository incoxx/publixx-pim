<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <footer class="bg-base-100 border-t border-base-300 px-6 py-4 text-center">
    <p class="text-xs text-base-content/40">{{ t('assetCatalog.poweredBy') }}</p>
  </footer>
</template>
