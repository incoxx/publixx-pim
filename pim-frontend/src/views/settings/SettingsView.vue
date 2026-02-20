<script setup>
import { ref, onMounted } from 'vue'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'
import { Globe, Palette, Server, RotateCcw, CheckCircle, XCircle, Loader2, GitBranch } from 'lucide-vue-next'
import adminApi from '@/api/admin'

const localeStore = useLocaleStore()
const authStore = useAuthStore()

// Deployment State
const serverStatus = ref(null)
const deploying = ref(false)
const deployResult = ref(null)
const deployError = ref(null)
const rollbackHash = ref('')
const rollingBack = ref(false)
const showConfirmDeploy = ref(false)

const isAdmin = authStore.hasPermission('*') || authStore.userRole === 'Admin'

async function loadStatus() {
  if (!isAdmin) return
  try {
    const { data } = await adminApi.getDeployStatus()
    serverStatus.value = data
  } catch {
    serverStatus.value = null
  }
}

async function triggerDeploy() {
  showConfirmDeploy.value = false
  deploying.value = true
  deployResult.value = null
  deployError.value = null
  try {
    const { data } = await adminApi.deploy()
    deployResult.value = data
    rollbackHash.value = data.backup_hash || ''
    await loadStatus()
  } catch (e) {
    deployError.value = e.response?.data?.detail || e.message || 'Deployment fehlgeschlagen'
  } finally {
    deploying.value = false
  }
}

async function triggerRollback() {
  if (!rollbackHash.value) return
  rollingBack.value = true
  deployResult.value = null
  deployError.value = null
  try {
    const { data } = await adminApi.rollback(rollbackHash.value)
    deployResult.value = data
    await loadStatus()
  } catch (e) {
    deployError.value = e.response?.data?.detail || e.message || 'Rollback fehlgeschlagen'
  } finally {
    rollingBack.value = false
  }
}

onMounted(loadStatus)
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Einstellungen</h2>

    <!-- Sprache -->
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Globe class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">Sprache</h3></div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">UI-Sprache</label>
        <select class="pim-input max-w-xs" :value="localeStore.currentLocale" @change="localeStore.setUiLocale($event.target.value)">
          <option v-for="loc in localeStore.availableLocales" :key="loc.code" :value="loc.code">{{ loc.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Aktive Datensprachen</label>
        <div class="flex gap-2">
          <label v-for="loc in localeStore.availableLocales" :key="loc.code" class="flex items-center gap-1.5 text-xs cursor-pointer">
            <input type="checkbox" :checked="localeStore.activeDataLocales.includes(loc.code)" @change="localeStore.toggleDataLocale(loc.code)" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
            {{ loc.label }}
          </label>
        </div>
      </div>
    </div>

    <!-- Darstellung -->
    <div class="pim-card p-6 space-y-4">
      <div class="flex items-center gap-3 mb-2"><Palette class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" /><h3 class="text-sm font-semibold">Darstellung</h3></div>
      <p class="text-xs text-[var(--color-text-tertiary)]">Weitere Einstellungen werden hier konfiguriert.</p>
    </div>

    <!-- Deployment (nur Admin) -->
    <div v-if="isAdmin" class="pim-card p-6 space-y-5">
      <div class="flex items-center gap-3 mb-2">
        <Server class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold">Server-Deployment</h3>
      </div>

      <!-- Server-Status -->
      <div v-if="serverStatus" class="bg-[var(--color-bg-secondary)] rounded-lg p-4 space-y-2">
        <div class="flex items-center gap-2 text-xs text-[var(--color-text-secondary)]">
          <GitBranch class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="font-mono font-medium">{{ serverStatus.branch }}</span>
          <span class="text-[var(--color-text-tertiary)]">@</span>
          <span class="font-mono text-[var(--color-accent)]">{{ serverStatus.commit }}</span>
        </div>
        <p class="text-xs text-[var(--color-text-secondary)]">{{ serverStatus.message }}</p>
        <p class="text-[11px] text-[var(--color-text-tertiary)]">
          {{ serverStatus.date }} &middot; Laravel {{ serverStatus.laravel_version }} &middot; PHP {{ serverStatus.php_version }}
        </p>
      </div>

      <!-- Deploy-Button -->
      <div class="flex items-center gap-3">
        <button
          v-if="!showConfirmDeploy"
          @click="showConfirmDeploy = true"
          :disabled="deploying"
          class="pim-btn-primary flex items-center gap-2 text-sm"
        >
          <Loader2 v-if="deploying" class="w-4 h-4 animate-spin" />
          <Server v-else class="w-4 h-4" :stroke-width="1.75" />
          Main Branch laden & deployen
        </button>

        <!-- Confirm -->
        <template v-if="showConfirmDeploy && !deploying">
          <span class="text-xs text-[var(--color-text-secondary)]">Sicher? Der Server wird aktualisiert.</span>
          <button @click="triggerDeploy" class="pim-btn-danger text-sm px-4 py-1.5">Ja, deployen</button>
          <button @click="showConfirmDeploy = false" class="pim-btn-secondary text-sm px-4 py-1.5">Abbrechen</button>
        </template>
      </div>

      <!-- Deploying Spinner -->
      <div v-if="deploying" class="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
        <Loader2 class="w-5 h-5 animate-spin text-[var(--color-accent)]" />
        <span>Deployment läuft... Git Pull, Composer, Migrationen, Cache...</span>
      </div>

      <!-- Deploy Error -->
      <div v-if="deployError" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 text-sm font-medium">
          <XCircle class="w-4 h-4" />
          Deployment-Fehler
        </div>
        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ deployError }}</p>
      </div>

      <!-- Deploy Result -->
      <div v-if="deployResult" class="space-y-3">
        <div
          class="rounded-lg p-4 border"
          :class="deployResult.success
            ? 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800'
            : 'bg-yellow-50 dark:bg-yellow-950/30 border-yellow-200 dark:border-yellow-800'"
        >
          <div class="flex items-center gap-2 text-sm font-medium" :class="deployResult.success ? 'text-green-700 dark:text-green-400' : 'text-yellow-700 dark:text-yellow-400'">
            <CheckCircle v-if="deployResult.success" class="w-4 h-4" />
            <XCircle v-else class="w-4 h-4" />
            {{ deployResult.success ? 'Deployment erfolgreich' : 'Deployment mit Warnungen' }}
          </div>
          <p class="text-xs mt-1" :class="deployResult.success ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'">
            Commit: {{ deployResult.commit }} &middot; Dauer: {{ deployResult.duration_seconds }}s &middot; Von: {{ deployResult.deployed_by }}
          </p>
        </div>

        <!-- Step Details -->
        <details class="text-xs">
          <summary class="cursor-pointer text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
            Deployment-Schritte anzeigen ({{ deployResult.steps?.length || 0 }})
          </summary>
          <div class="mt-2 space-y-1 font-mono bg-[var(--color-bg-secondary)] rounded-lg p-3 max-h-64 overflow-y-auto">
            <div v-for="step in deployResult.steps" :key="step.step" class="flex items-start gap-2">
              <CheckCircle v-if="step.success" class="w-3.5 h-3.5 text-green-500 mt-0.5 shrink-0" />
              <XCircle v-else class="w-3.5 h-3.5 text-red-500 mt-0.5 shrink-0" />
              <div>
                <span class="font-medium">{{ step.step }}</span>
                <span v-if="step.output" class="text-[var(--color-text-tertiary)] ml-2">{{ step.output.substring(0, 200) }}</span>
              </div>
            </div>
          </div>
        </details>

        <!-- Rollback -->
        <div v-if="deployResult.backup_hash" class="flex items-center gap-3 pt-2 border-t border-[var(--color-border)]">
          <RotateCcw class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          <span class="text-xs text-[var(--color-text-secondary)]">
            Rollback auf <span class="font-mono">{{ deployResult.backup_hash?.substring(0, 7) }}</span>
          </span>
          <button
            @click="triggerRollback"
            :disabled="rollingBack"
            class="pim-btn-secondary text-xs px-3 py-1"
          >
            <Loader2 v-if="rollingBack" class="w-3 h-3 animate-spin" />
            <template v-else>Rollback</template>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
