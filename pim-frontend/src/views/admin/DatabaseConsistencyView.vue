<script setup>
import { ref, computed, onMounted } from 'vue'
import adminApi from '@/api/admin'
import {
  Database, RefreshCw, CheckCircle2, AlertTriangle, Trash2, Shield, Wrench,
} from 'lucide-vue-next'

const checks = ref([])
const totalIssues = ref(0)
const loading = ref(false)
const fixing = ref(null) // key of the check currently being fixed
const lastFixResult = ref(null)
const error = ref('')

const hasIssues = computed(() => totalIssues.value > 0)

onMounted(runCheck)

async function runCheck() {
  loading.value = true
  error.value = ''
  lastFixResult.value = null
  try {
    const { data } = await adminApi.checkConsistency()
    const payload = data.data || data
    checks.value = payload.checks
    totalIssues.value = payload.total_issues
  } catch (e) {
    error.value = e.response?.data?.detail || 'Fehler beim Prüfen der Datenbank-Konsistenz.'
  } finally {
    loading.value = false
  }
}

async function fixIssue(key) {
  fixing.value = key
  error.value = ''
  lastFixResult.value = null
  try {
    const { data } = await adminApi.fixConsistencyIssue(key)
    const payload = data.data || data
    lastFixResult.value = {
      key,
      deleted: payload.deleted,
    }
    // Re-run checks to update counts
    await runCheck()
  } catch (e) {
    error.value = e.response?.data?.detail || 'Fehler beim Bereinigen.'
  } finally {
    fixing.value = null
  }
}

async function fixAll() {
  const issues = checks.value.filter(c => c.status === 'issue')
  for (const check of issues) {
    await fixIssue(check.key)
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl">

    <!-- Header -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500/10 via-indigo-500/5 to-violet-500/10 border border-blue-500/20 p-6">
      <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
      <div class="relative">
        <div class="flex items-center gap-3 mb-2">
          <div class="p-2.5 rounded-xl bg-blue-500/15">
            <Database class="w-6 h-6 text-blue-500" />
          </div>
          <div>
            <h1 class="text-xl font-bold text-[var(--color-text-primary)]">Datenbank-Konsistenz</h1>
            <p class="text-xs text-[var(--color-text-secondary)]">Verwaiste Datensätze finden und bereinigen</p>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <Shield class="w-4 h-4 text-blue-500 shrink-0" />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Prüfungen</div>
              <div class="text-sm font-semibold text-[var(--color-text-primary)]">{{ checks.length }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <component
              :is="hasIssues ? AlertTriangle : CheckCircle2"
              class="w-4 h-4 shrink-0"
              :class="hasIssues ? 'text-amber-500' : 'text-emerald-500'"
            />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Probleme</div>
              <div class="text-sm font-semibold" :class="hasIssues ? 'text-amber-500' : 'text-emerald-500'">
                {{ totalIssues }}
              </div>
            </div>
          </div>
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <Wrench class="w-4 h-4 text-violet-500 shrink-0" />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Bereinigung</div>
              <div class="text-sm font-semibold text-[var(--color-text-primary)]">Automatisch</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Controls -->
    <div class="flex flex-wrap items-center gap-3">
      <button
        class="pim-btn pim-btn-primary text-xs flex items-center gap-2"
        :disabled="loading"
        @click="runCheck"
      >
        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" />
        {{ loading ? 'Prüfe...' : 'Konsistenz prüfen' }}
      </button>

      <button
        v-if="hasIssues && !loading"
        class="pim-btn text-xs flex items-center gap-2 border border-amber-500/30 bg-amber-500/10 text-amber-600 hover:bg-amber-500/20"
        :disabled="!!fixing"
        @click="fixAll"
      >
        <Wrench class="w-3.5 h-3.5" />
        Alle bereinigen
      </button>
    </div>

    <!-- Fix result -->
    <div
      v-if="lastFixResult"
      class="p-3 rounded-lg bg-emerald-500/10 text-emerald-600 text-xs flex items-center gap-2"
    >
      <CheckCircle2 class="w-4 h-4 shrink-0" />
      {{ lastFixResult.deleted }} Datensätze bereinigt.
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading && !checks.length" class="text-center py-8 text-[var(--color-text-tertiary)] text-sm">
      Prüfe Datenbank-Konsistenz...
    </div>

    <!-- Results -->
    <div v-if="checks.length" class="space-y-2">
      <div
        v-for="check in checks"
        :key="check.key"
        class="flex items-center gap-4 px-4 py-3 rounded-xl border bg-[var(--color-bg)]"
        :class="check.status === 'issue'
          ? 'border-amber-500/30 bg-amber-500/5'
          : 'border-[var(--color-border)]'"
      >
        <!-- Status icon -->
        <div
          class="p-2 rounded-lg shrink-0"
          :class="check.status === 'issue' ? 'bg-amber-500/15' : 'bg-emerald-500/10'"
        >
          <AlertTriangle v-if="check.status === 'issue'" class="w-4 h-4 text-amber-500" />
          <CheckCircle2 v-else class="w-4 h-4 text-emerald-500" />
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="text-xs font-semibold text-[var(--color-text-primary)]">{{ check.label }}</div>
          <div class="text-[11px] text-[var(--color-text-tertiary)] mt-0.5">{{ check.description }}</div>
        </div>

        <!-- Count -->
        <div class="shrink-0 text-right">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
            :class="check.status === 'issue'
              ? 'bg-amber-500/15 text-amber-600'
              : 'bg-emerald-500/10 text-emerald-500'"
          >
            {{ check.count }}
          </span>
        </div>

        <!-- Fix button -->
        <button
          v-if="check.status === 'issue'"
          class="pim-btn text-[11px] px-3 py-1.5 flex items-center gap-1.5 border border-amber-500/30 bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 shrink-0"
          :disabled="fixing === check.key"
          @click="fixIssue(check.key)"
        >
          <Trash2 v-if="fixing !== check.key" class="w-3 h-3" />
          <RefreshCw v-else class="w-3 h-3 animate-spin" />
          {{ fixing === check.key ? 'Bereinige...' : 'Bereinigen' }}
        </button>
      </div>
    </div>

    <!-- All good -->
    <div
      v-if="checks.length && !hasIssues && !loading"
      class="text-center py-6 text-emerald-500 text-sm font-medium"
    >
      <CheckCircle2 class="w-8 h-8 mx-auto mb-2" :stroke-width="1.5" />
      Datenbank ist konsistent. Keine verwaisten Datensätze gefunden.
    </div>
  </div>
</template>
