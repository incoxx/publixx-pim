<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'
import {
  FlaskConical, Play, CheckCircle2, XCircle, AlertTriangle, Clock, Cpu,
  Shield, Zap, BarChart3, RefreshCw, ChevronDown, ChevronRight,
} from 'lucide-vue-next'

const info = ref(null)
const results = ref(null)
const running = ref(false)
const selectedSuite = ref('all')
const showOutput = ref({})
const loadingInfo = ref(true)

const suiteOptions = [
  { id: 'all', label: 'Kompletter Test', desc: 'Backend + Frontend' },
  { id: 'unit', label: 'Unit Tests', desc: 'Isolierte Logik' },
  { id: 'feature', label: 'Feature Tests', desc: 'API & Integration' },
  { id: 'frontend', label: 'Frontend Tests', desc: 'Stores, Utils, Composables' },
]

const overallStatus = computed(() => {
  if (!results.value) return null
  return results.value.success ? 'passed' : 'failed'
})

const passRate = computed(() => {
  if (!results.value?.summary) return 0
  const { total_tests, passed } = results.value.summary
  return total_tests > 0 ? Math.round((passed / total_tests) * 100) : 0
})

onMounted(async () => {
  try {
    const { data } = await client.get('/admin/test-runner/info')
    info.value = data.data
  } catch { /* ignore */ }
  finally { loadingInfo.value = false }
})

async function runTests() {
  running.value = true
  results.value = null
  showOutput.value = {}

  try {
    const { data } = await client.post('/admin/test-runner/run', {
      suite: selectedSuite.value,
    }, { timeout: 600000 })
    results.value = data.data
  } catch (e) {
    results.value = {
      success: false,
      summary: { total_tests: 0, passed: 0, failed: 0, errors: 1 },
      results: {
        error: {
          runner: 'System',
          success: false,
          tests: 0, passed: 0, failures: 0, errors: 1,
          output: e.response?.data?.message || e.message || 'Verbindungsfehler',
        },
      },
    }
  } finally {
    running.value = false
  }
}

function toggleOutput(key) {
  showOutput.value[key] = !showOutput.value[key]
}

function formatDuration(ms) {
  if (!ms) return '–'
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}
</script>

<template>
  <div class="space-y-6 max-w-5xl">

    <!-- Hero Header mit Marketing -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-cyan-500/10 border border-emerald-500/20 p-6">
      <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
      <div class="relative">
        <div class="flex items-center gap-3 mb-2">
          <div class="p-2.5 rounded-xl bg-emerald-500/15">
            <FlaskConical class="w-6 h-6 text-emerald-500" />
          </div>
          <div>
            <h1 class="text-xl font-bold text-[var(--color-text-primary)]">anyPIM Quality Assurance</h1>
            <p class="text-xs text-[var(--color-text-secondary)]">Automatisierte Tests sichern die Qualit&auml;t deiner PIM-Instanz</p>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <Shield class="w-4 h-4 text-emerald-500 shrink-0" />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Abgesichert</div>
              <div class="text-sm font-semibold text-[var(--color-text-primary)]">167+ Tests</div>
            </div>
          </div>
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <Zap class="w-4 h-4 text-amber-500 shrink-0" />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Schnell</div>
              <div class="text-sm font-semibold text-[var(--color-text-primary)]">< 10 Sek.</div>
            </div>
          </div>
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <Cpu class="w-4 h-4 text-blue-500 shrink-0" />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Backend</div>
              <div class="text-sm font-semibold text-[var(--color-text-primary)]">PHPUnit 11</div>
            </div>
          </div>
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--color-bg)]/60 border border-[var(--color-border)]">
            <BarChart3 class="w-4 h-4 text-violet-500 shrink-0" />
            <div>
              <div class="text-[10px] text-[var(--color-text-secondary)]">Frontend</div>
              <div class="text-sm font-semibold text-[var(--color-text-primary)]">Vitest</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Test Suites Info -->
    <div v-if="info" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div
        v-for="suite in info.suites"
        :key="suite.id"
        class="flex items-center gap-3 px-4 py-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)]"
      >
        <div class="p-2 rounded-lg" :class="suite.runner === 'PHPUnit' ? 'bg-blue-500/10' : 'bg-violet-500/10'">
          <Cpu v-if="suite.runner === 'PHPUnit'" class="w-4 h-4 text-blue-500" />
          <BarChart3 v-else class="w-4 h-4 text-violet-500" />
        </div>
        <div>
          <div class="text-xs font-semibold text-[var(--color-text-primary)]">{{ suite.name }}</div>
          <div class="text-[10px] text-[var(--color-text-secondary)]">
            {{ suite.test_files }} Testdateien &middot; {{ suite.runner }}
          </div>
        </div>
      </div>
    </div>

    <!-- Run Controls -->
    <div class="flex flex-wrap items-center gap-3">
      <select v-model="selectedSuite" class="pim-input text-xs w-auto min-w-[200px]">
        <option v-for="opt in suiteOptions" :key="opt.id" :value="opt.id">
          {{ opt.label }} — {{ opt.desc }}
        </option>
      </select>

      <button
        class="pim-btn pim-btn-primary text-xs gap-2 min-w-[160px]"
        :disabled="running"
        @click="runTests"
      >
        <RefreshCw v-if="running" class="w-3.5 h-3.5 animate-spin" />
        <Play v-else class="w-3.5 h-3.5" />
        {{ running ? 'Tests laufen...' : 'Tests starten' }}
      </button>

      <div v-if="results" class="ml-auto flex items-center gap-2 text-xs">
        <Clock class="w-3.5 h-3.5 text-[var(--color-text-secondary)]" />
        <span class="text-[var(--color-text-secondary)]">{{ results.ran_at ? new Date(results.ran_at).toLocaleTimeString('de') : '' }}</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="running" class="flex flex-col items-center justify-center py-16 gap-4">
      <div class="relative">
        <div class="w-16 h-16 rounded-full border-4 border-emerald-500/20 border-t-emerald-500 animate-spin"></div>
        <FlaskConical class="w-6 h-6 text-emerald-500 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
      </div>
      <div class="text-center">
        <div class="text-sm font-semibold text-[var(--color-text-primary)]">Tests werden ausgef&uuml;hrt...</div>
        <div class="text-xs text-[var(--color-text-secondary)] mt-1">
          {{ selectedSuite === 'all' ? 'Backend + Frontend' : suiteOptions.find(s => s.id === selectedSuite)?.label }}
          werden gepr&uuml;ft
        </div>
      </div>
    </div>

    <!-- Results -->
    <div v-if="results && !running" class="space-y-4">

      <!-- Overall Status Banner -->
      <div
        class="flex items-center gap-4 p-5 rounded-2xl border-2 transition-all"
        :class="overallStatus === 'passed'
          ? 'bg-emerald-500/5 border-emerald-500/30'
          : 'bg-red-500/5 border-red-500/30'"
      >
        <div class="p-3 rounded-xl" :class="overallStatus === 'passed' ? 'bg-emerald-500/15' : 'bg-red-500/15'">
          <CheckCircle2 v-if="overallStatus === 'passed'" class="w-8 h-8 text-emerald-500" />
          <XCircle v-else class="w-8 h-8 text-red-500" />
        </div>
        <div class="flex-1">
          <div class="text-lg font-bold" :class="overallStatus === 'passed' ? 'text-emerald-600' : 'text-red-600'">
            {{ overallStatus === 'passed' ? 'Alle Tests bestanden!' : 'Tests fehlgeschlagen' }}
          </div>
          <div class="text-xs text-[var(--color-text-secondary)] mt-0.5">
            {{ results.summary.passed }}/{{ results.summary.total_tests }} Tests erfolgreich
            <template v-if="results.summary.failed > 0">
              &middot; {{ results.summary.failed }} fehlgeschlagen
            </template>
            <template v-if="results.summary.errors > 0">
              &middot; {{ results.summary.errors }} Fehler
            </template>
          </div>
        </div>

        <!-- Pass Rate Circle -->
        <div class="relative w-16 h-16">
          <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
            <circle cx="18" cy="18" r="15.5" fill="none" stroke-width="3" class="stroke-[var(--color-border)]" />
            <circle
              cx="18" cy="18" r="15.5" fill="none" stroke-width="3"
              stroke-linecap="round"
              :stroke-dasharray="`${passRate * 0.974} 100`"
              :class="overallStatus === 'passed' ? 'stroke-emerald-500' : 'stroke-red-500'"
            />
          </svg>
          <div class="absolute inset-0 flex items-center justify-center">
            <span class="text-sm font-bold" :class="overallStatus === 'passed' ? 'text-emerald-600' : 'text-red-600'">
              {{ passRate }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Per-Runner Results -->
      <div v-for="(result, key) in results.results" :key="key" class="rounded-xl border border-[var(--color-border)] bg-[var(--color-bg)] overflow-hidden">
        <div class="flex items-center gap-3 px-4 py-3">
          <div class="p-1.5 rounded-lg" :class="result.success ? 'bg-emerald-500/10' : 'bg-red-500/10'">
            <CheckCircle2 v-if="result.success" class="w-4 h-4 text-emerald-500" />
            <XCircle v-else class="w-4 h-4 text-red-500" />
          </div>
          <div class="flex-1">
            <div class="text-xs font-semibold text-[var(--color-text-primary)]">{{ result.runner }}</div>
            <div class="text-[10px] text-[var(--color-text-secondary)]">
              {{ result.passed }}/{{ result.tests }} bestanden
              <template v-if="result.duration_ms"> &middot; {{ formatDuration(result.duration_ms) }}</template>
            </div>
          </div>

          <!-- Mini bar -->
          <div class="w-32 h-2 rounded-full bg-[var(--color-border)] overflow-hidden hidden sm:block">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="result.success ? 'bg-emerald-500' : 'bg-red-500'"
              :style="{ width: result.tests > 0 ? (result.passed / result.tests * 100) + '%' : '0%' }"
            ></div>
          </div>

          <!-- Expand output -->
          <button
            v-if="result.output"
            class="pim-btn pim-btn-ghost text-[10px] px-2 py-1 gap-1"
            @click="toggleOutput(key)"
          >
            <ChevronDown v-if="showOutput[key]" class="w-3 h-3" />
            <ChevronRight v-else class="w-3 h-3" />
            Output
          </button>
        </div>

        <!-- Test Files -->
        <div v-if="result.test_files" class="border-t border-[var(--color-border)] px-4 py-2">
          <div v-for="file in result.test_files" :key="file.file" class="flex items-center gap-2 py-1 text-[11px]">
            <CheckCircle2 v-if="file.failed === 0" class="w-3 h-3 text-emerald-500 shrink-0" />
            <XCircle v-else class="w-3 h-3 text-red-500 shrink-0" />
            <span class="font-mono text-[var(--color-text-secondary)] truncate flex-1">{{ file.file }}</span>
            <span class="text-[var(--color-text-secondary)] shrink-0">
              {{ file.passed }}/{{ file.tests }} &middot; {{ formatDuration(file.duration_ms) }}
            </span>
          </div>
        </div>

        <!-- Raw Output -->
        <div v-if="showOutput[key] && result.output" class="border-t border-[var(--color-border)]">
          <pre class="px-4 py-3 text-[10px] leading-relaxed font-mono text-[var(--color-text-secondary)] overflow-x-auto max-h-64 whitespace-pre-wrap">{{ result.output }}</pre>
        </div>
      </div>

      <!-- Marketing Footer -->
      <div class="text-center py-4 space-y-1">
        <div class="text-[10px] text-[var(--color-text-secondary)]">
          Powered by <span class="font-semibold">anyPIM Quality Assurance</span>
        </div>
        <div class="text-[10px] text-[var(--color-text-secondary)]/60">
          PHPUnit 11 &middot; Vitest &middot; GitHub Actions CI/CD &middot; 100% Open Source
        </div>
      </div>
    </div>

    <!-- Empty State (before first run) -->
    <div v-if="!results && !running && !loadingInfo" class="text-center py-12 space-y-3">
      <div class="inline-flex p-4 rounded-2xl bg-emerald-500/5 border border-emerald-500/10">
        <FlaskConical class="w-10 h-10 text-emerald-500/40" />
      </div>
      <div>
        <div class="text-sm font-semibold text-[var(--color-text-primary)]">Bereit f&uuml;r den Test</div>
        <div class="text-xs text-[var(--color-text-secondary)] mt-1 max-w-sm mx-auto">
          W&auml;hle eine Test-Suite und klicke &quot;Tests starten&quot; um die Qualit&auml;t deiner anyPIM-Instanz zu pr&uuml;fen.
          Alle Tests laufen isoliert und beeinflussen keine Produktionsdaten.
        </div>
      </div>
    </div>

  </div>
</template>
