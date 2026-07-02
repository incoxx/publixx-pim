<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import {
  Terminal, Play, Copy, CheckCircle, XCircle, AlertTriangle,
  RefreshCw, Lock, ChevronDown, ChevronRight,
} from 'lucide-vue-next'
import artisanCockpitApi from '@/api/artisanCockpit'

const categories = ref([])
const activeTab = ref('')
const loading = ref(true)
const error = ref('')

// command -> { argument: string, options: {...} }
const formValues = reactive({})
// command -> boolean
const running = reactive({})
// command -> { success, exit_code, output, cli, ran_at }
const results = reactive({})
// command -> boolean (Ergebnis-Panel aufgeklappt)
const expandedResults = reactive({})
// Befehl, für den gerade die Bestätigung angezeigt wird
const confirmingCommand = ref(null)

const activeCategory = computed(() => categories.value.find(c => c.key === activeTab.value) || null)

onMounted(loadCommands)

async function loadCommands() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await artisanCockpitApi.listCommands()
    categories.value = data.data || data
    if (categories.value.length) {
      activeTab.value = categories.value[0].key
    }
    for (const category of categories.value) {
      for (const cmd of category.commands) {
        if (!cmd.runnable) continue
        formValues[cmd.command] = {
          argument: cmd.argument?.default ?? '',
          options: Object.fromEntries(
            (cmd.options || []).map(o => [o.name, o.type === 'boolean' ? false : (o.default ?? '')]),
          ),
        }
      }
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Befehlsliste konnte nicht geladen werden'
  } finally {
    loading.value = false
  }
}

function requestRun(cmd) {
  if (cmd.danger === 'confirm' && confirmingCommand.value !== cmd.command) {
    confirmingCommand.value = cmd.command
    return
  }
  confirmingCommand.value = null
  executeRun(cmd)
}

function cancelConfirm() {
  confirmingCommand.value = null
}

async function executeRun(cmd) {
  running[cmd.command] = true
  error.value = ''
  try {
    const values = formValues[cmd.command] || { argument: '', options: {} }
    const { data } = await artisanCockpitApi.run({
      command: cmd.command,
      argument: cmd.argument ? values.argument : undefined,
      options: values.options,
      confirmed: cmd.danger === 'confirm',
    })
    results[cmd.command] = data.data || data
    expandedResults[cmd.command] = true
  } catch (e) {
    results[cmd.command] = {
      success: false,
      exit_code: -1,
      cli: null,
      output: e.response?.data?.message || 'Ausführung fehlgeschlagen',
      ran_at: new Date().toISOString(),
    }
    expandedResults[cmd.command] = true
  } finally {
    running[cmd.command] = false
  }
}

function toggleResult(command) {
  expandedResults[command] = !expandedResults[command]
}

async function copyHint(hint) {
  try {
    await navigator.clipboard.writeText(hint)
  } catch { /* ignore */ }
}
</script>

<template>
  <div class="space-y-4 max-w-5xl">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
        <Terminal class="w-5 h-5" :stroke-width="1.75" />
        Artisan-Cockpit
      </h2>
      <button class="pim-btn pim-btn-secondary text-xs" @click="loadCommands">
        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" :stroke-width="1.75" />
        Aktualisieren
      </button>
    </div>

    <p class="text-xs text-[var(--color-text-tertiary)]">
      Befehle für Tagesgeschäft und Entstörung lassen sich hier direkt ausführen. Alle übrigen Artisan-Befehle
      werden zur Übersicht mit aufgeführt, sind aber nur per Server-Konsole startbar (destruktiv, Datei-Pfad
      erforderlich oder Dauerprozess).
    </p>

    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">
      {{ error }}
    </div>

    <!-- Tabs -->
    <div v-if="!loading" class="flex flex-wrap gap-1 border-b border-[var(--color-border)]">
      <button
        v-for="cat in categories"
        :key="cat.key"
        :class="[
          'px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors',
          activeTab === cat.key
            ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
            : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-primary)]',
        ]"
        @click="activeTab = cat.key"
      >
        {{ cat.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 5" :key="i" class="pim-skeleton h-16 rounded-lg" />
    </div>

    <!-- Commands -->
    <div v-else-if="activeCategory" class="space-y-2">
      <div
        v-for="cmd in activeCategory.commands"
        :key="cmd.command"
        class="pim-card overflow-hidden"
      >
        <div class="flex items-start gap-3 p-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-semibold text-[var(--color-text-primary)]">{{ cmd.label }}</span>
              <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">
                {{ cmd.command }}
              </span>
              <span
                v-if="!cmd.runnable"
                class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-[var(--color-bg)] text-[var(--color-text-tertiary)]"
              >
                <Lock class="w-2.5 h-2.5" :stroke-width="2" />
                Nur Konsole
              </span>
              <span
                v-else-if="cmd.danger === 'confirm'"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-500/10 text-amber-600"
              >
                Achtung
              </span>
            </div>
            <p class="text-xs text-[var(--color-text-tertiary)] mt-1">{{ cmd.description }}</p>

            <!-- Console-only: Hinweis zum manuellen Aufruf -->
            <div v-if="!cmd.runnable" class="mt-2 flex items-center gap-2">
              <code class="flex-1 text-[11px] font-mono px-2 py-1.5 rounded bg-[var(--color-bg)] text-[var(--color-text-secondary)] overflow-x-auto whitespace-nowrap">{{ cmd.console_hint }}</code>
              <button
                class="pim-btn pim-btn-secondary text-xs px-2 py-1.5 shrink-0"
                title="Befehl kopieren"
                @click="copyHint(cmd.console_hint)"
              >
                <Copy class="w-3.5 h-3.5" :stroke-width="1.75" />
              </button>
            </div>

            <!-- Runnable: Parameter-Formular -->
            <div v-else class="mt-3 space-y-2">
              <div v-if="cmd.argument || (cmd.options && cmd.options.length)" class="flex flex-wrap gap-3">
                <div v-if="cmd.argument" class="min-w-[160px]">
                  <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-1">
                    {{ cmd.argument.label }}{{ cmd.argument.required ? ' *' : '' }}
                  </label>
                  <input
                    v-model="formValues[cmd.command].argument"
                    type="text"
                    class="pim-input text-xs w-full"
                    :placeholder="cmd.argument.help || cmd.argument.default || ''"
                  />
                </div>
                <template v-for="opt in cmd.options" :key="opt.name">
                  <label v-if="opt.type === 'boolean'" class="flex items-center gap-1.5 self-end mb-1.5 cursor-pointer">
                    <input
                      type="checkbox"
                      v-model="formValues[cmd.command].options[opt.name]"
                      class="checkbox checkbox-xs checkbox-accent"
                    />
                    <span class="text-[11px] text-[var(--color-text-secondary)]">{{ opt.label }}</span>
                  </label>
                  <div v-else class="min-w-[160px]">
                    <label class="block text-[10px] font-medium text-[var(--color-text-tertiary)] mb-1">
                      {{ opt.label }}{{ opt.required ? ' *' : '' }}
                    </label>
                    <input
                      v-model="formValues[cmd.command].options[opt.name]"
                      :type="opt.type === 'number' ? 'number' : 'text'"
                      class="pim-input text-xs w-full"
                      :placeholder="opt.help || opt.default || ''"
                    />
                  </div>
                </template>
              </div>

              <!-- Bestätigung für gefährliche Befehle -->
              <div
                v-if="confirmingCommand === cmd.command"
                class="flex items-center gap-2 p-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5"
              >
                <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0" :stroke-width="2" />
                <span class="text-[11px] text-[var(--color-text-primary)] flex-1">
                  „{{ cmd.label }}“ wirklich jetzt ausführen?
                </span>
                <button class="pim-btn pim-btn-secondary text-xs px-2 py-1" @click="cancelConfirm">
                  Abbrechen
                </button>
                <button
                  class="pim-btn text-xs px-2 py-1 bg-amber-500 text-white hover:bg-amber-600"
                  @click="requestRun(cmd)"
                >
                  Bestätigen
                </button>
              </div>

              <button
                v-else
                class="pim-btn pim-btn-primary text-xs"
                :disabled="running[cmd.command]"
                @click="requestRun(cmd)"
              >
                <RefreshCw v-if="running[cmd.command]" class="w-3.5 h-3.5 animate-spin" :stroke-width="2" />
                <Play v-else class="w-3.5 h-3.5" :stroke-width="2" />
                {{ running[cmd.command] ? 'Läuft...' : 'Ausführen' }}
              </button>

              <!-- Ergebnis -->
              <div v-if="results[cmd.command]" class="border border-[var(--color-border)] rounded-lg overflow-hidden">
                <button
                  class="w-full flex items-center gap-2 px-3 py-2 bg-[var(--color-bg)] text-left"
                  @click="toggleResult(cmd.command)"
                >
                  <component :is="expandedResults[cmd.command] ? ChevronDown : ChevronRight" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" />
                  <CheckCircle v-if="results[cmd.command].success" class="w-3.5 h-3.5 text-[var(--color-success)] shrink-0" :stroke-width="2" />
                  <XCircle v-else class="w-3.5 h-3.5 text-[var(--color-error)] shrink-0" :stroke-width="2" />
                  <span class="text-[11px] font-medium text-[var(--color-text-primary)]">
                    {{ results[cmd.command].success ? 'Erfolgreich' : 'Fehlgeschlagen' }}
                  </span>
                  <span class="text-[10px] text-[var(--color-text-tertiary)]">
                    Exit-Code {{ results[cmd.command].exit_code }} · {{ new Date(results[cmd.command].ran_at).toLocaleTimeString('de-DE') }}
                  </span>
                </button>
                <div v-if="expandedResults[cmd.command]" class="p-3 space-y-2">
                  <code v-if="results[cmd.command].cli" class="block text-[11px] font-mono px-2 py-1.5 rounded bg-[var(--color-bg)] text-[var(--color-text-secondary)] overflow-x-auto whitespace-nowrap">
                    {{ results[cmd.command].cli }}
                  </code>
                  <pre class="font-mono text-[11px] whitespace-pre-wrap text-[var(--color-text-primary)] bg-[var(--color-bg)] p-3 rounded-lg max-h-72 overflow-y-auto leading-relaxed">{{ results[cmd.command].output || '(keine Ausgabe)' }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
