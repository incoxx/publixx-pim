<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'
import { Globe, Palette, AlertTriangle } from 'lucide-vue-next'
import adminApi from '@/api/admin'

const { t } = useI18n()
const localeStore = useLocaleStore()
const authStore = useAuthStore()

const confirmText = ref('')
const resetting = ref(false)
const showConfirm = ref(false)
const resultMessage = ref('')
const resultError = ref(false)

function openConfirmDialog() {
  confirmText.value = ''
  resultMessage.value = ''
  resultError.value = false
  showConfirm.value = true
}

function cancelReset() {
  showConfirm.value = false
  confirmText.value = ''
}

async function executeReset() {
  if (confirmText.value !== 'RESET') return

  resetting.value = true
  resultMessage.value = ''
  resultError.value = false

  try {
    await adminApi.resetData('RESET')
    resultMessage.value = t('settings.resetSuccess')
    resultError.value = false
    showConfirm.value = false
    confirmText.value = ''
  } catch (err) {
    resultMessage.value = err.response?.data?.detail || t('settings.resetError')
    resultError.value = true
  } finally {
    resetting.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('settings.title') }}</h2>
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Globe class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">{{ t('settings.language') }}</h3></div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ t('settings.uiLanguage') }}</label>
        <select class="pim-input max-w-xs" :value="localeStore.currentLocale" @change="localeStore.setUiLocale($event.target.value)">
          <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ t('settings.dataLanguages') }}</label>
        <div class="flex gap-2">
          <label v-for="loc in localeStore.availableLocales" :key="loc.code" class="flex items-center gap-1.5 text-xs cursor-pointer">
            <input type="checkbox" :checked="localeStore.activeDataLocales.includes(loc.code)" @change="localeStore.toggleDataLocale(loc.code)" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            {{ loc.label }}
          </label>
        </div>
      </div>
    </div>
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Palette class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">{{ t('settings.appearance') }}</h3></div>
      <p class="text-xs text-[var(--color-text-tertiary)]">{{ t('settings.appearancePlaceholder') }}</p>
    </div>

    <!-- Admin only: Reset Data Model -->
    <div v-if="authStore.userRole === 'Admin'" class="pim-card border border-red-300 dark:border-red-800 p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <AlertTriangle class="w-5 h-5 text-red-500" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-red-600 dark:text-red-400">{{ t('settings.dangerZone') }}</h3>
      </div>

      <div>
        <h4 class="text-sm font-medium text-[var(--color-text-primary)] mb-1">{{ t('settings.resetTitle') }}</h4>
        <p class="text-xs text-[var(--color-text-tertiary)] mb-3">{{ t('settings.resetDescription') }}</p>

        <!-- Result message -->
        <div v-if="resultMessage" class="mb-3 text-xs px-3 py-2 rounded" :class="resultError ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'">
          {{ resultMessage }}
        </div>

        <!-- Confirmation dialog -->
        <div v-if="showConfirm" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 space-y-3">
          <p class="text-xs text-red-700 dark:text-red-300 font-medium">{{ t('settings.resetConfirmPrompt') }}</p>
          <input
            v-model="confirmText"
            type="text"
            class="pim-input max-w-xs text-sm"
            placeholder="RESET"
            :disabled="resetting"
          />
          <div class="flex gap-2">
            <button
              class="px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              :disabled="confirmText !== 'RESET' || resetting"
              @click="executeReset"
            >
              <span v-if="resetting">{{ t('common.loading') }}</span>
              <span v-else>{{ t('settings.resetExecute') }}</span>
            </button>
            <button
              class="px-3 py-1.5 text-xs font-medium rounded-md text-[var(--color-text-secondary)] bg-[var(--color-bg-secondary)] hover:bg-[var(--color-bg-tertiary)] transition-colors"
              :disabled="resetting"
              @click="cancelReset"
            >
              {{ t('common.cancel') }}
            </button>
          </div>
        </div>

        <!-- Initial button -->
        <button
          v-else
          class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 border border-red-300 hover:bg-red-50 dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/20 transition-colors"
          @click="openConfirmDialog"
        >
          {{ t('settings.resetButton') }}
        </button>
      </div>
    </div>
  </div>
</template>
