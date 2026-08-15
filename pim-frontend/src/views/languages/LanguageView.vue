<script setup>
import { ref, computed, onMounted } from 'vue'
import { Languages, Plus, Pencil, Trash2, X, Check, AlertTriangle } from 'lucide-vue-next'
import languagesApi from '@/api/languages'
import { useLocaleStore } from '@/stores/locale'
import { languageLabel } from '@/config/languages'

const localeStore = useLocaleStore()

const rows = ref([])
const loading = ref(false)
const error = ref('')

const showDialog = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = ref({ technical_name: '', name_de: '', name_en: '', is_active: true, fallback_language: '', sort_order: 0 })
const formError = ref('')

/** Kette zur Anzeige in der Tabelle, z.B. "en → de". */
function chainFor(row) {
  const chain = []
  const seen = new Set([row.technical_name])
  let current = row.technical_name

  while (chain.length < 5) {
    const next = rows.value.find(r => r.technical_name === current)?.fallback_language
    if (!next || seen.has(next)) break
    chain.push(next)
    seen.add(next)
    current = next
  }

  return chain
}

const sorted = computed(() =>
  [...rows.value].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
)

const fallbackCandidates = computed(() =>
  sorted.value.filter(r => r.technical_name !== form.value.technical_name),
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await languagesApi.list()
    rows.value = data?.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  formError.value = ''
  form.value = {
    technical_name: '',
    name_de: '',
    name_en: '',
    is_active: true,
    // Englisch ist die uebliche Bruecke; sonst die Quellsprache
    fallback_language: rows.value.some(r => r.technical_name === 'en')
      ? 'en'
      : (rows.value.find(r => r.is_source)?.technical_name || ''),
    sort_order: (rows.value.length ? Math.max(...rows.value.map(r => r.sort_order ?? 0)) : 0) + 1,
  }
  showDialog.value = true
}

function openEdit(row) {
  editing.value = row
  formError.value = ''
  form.value = {
    technical_name: row.technical_name,
    name_de: row.name_de,
    name_en: row.name_en || '',
    is_active: row.is_active !== false,
    fallback_language: row.fallback_language || '',
    sort_order: row.sort_order ?? 0,
  }
  showDialog.value = true
}

// Beim Tippen des Codes den Namen vorschlagen — spart das Nachschlagen und
// verhindert, dass Sprachen nur als "FI" in der Oberflaeche auftauchen.
function onCodeInput() {
  form.value.technical_name = (form.value.technical_name || '').toLowerCase().trim()
  if (!editing.value && form.value.technical_name.length === 2 && !form.value.name_de) {
    const suggested = languageLabel(form.value.technical_name)
    if (suggested !== form.value.technical_name.toUpperCase()) {
      form.value.name_de = suggested
    }
  }
}

async function save() {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) {
      await languagesApi.update(editing.value.id, form.value)
    } else {
      await languagesApi.create(form.value)
    }
    showDialog.value = false
    await load()
    // Die Sprachliste haengt ueberall in der Oberflaeche — sofort nachziehen,
    // damit Produkteditor, Suche und Export die neue Sprache direkt kennen.
    await localeStore.fetchLanguages(true)
  } catch (e) {
    const data = e.response?.data
    formError.value = data?.errors
      ? Object.values(data.errors).flat().join(' ')
      : (data?.message || e.message)
  } finally {
    saving.value = false
  }
}

async function remove(row) {
  if (!confirm(`Sprache "${row.name_de}" (${row.technical_name}) löschen?`)) return
  try {
    await languagesApi.remove(row.id)
    await load()
    await localeStore.fetchLanguages(true)
  } catch (e) {
    alert(e.response?.data?.message || e.message)
  }
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <Languages class="w-5 h-5 text-[var(--color-accent)]" />
        <h1 class="text-lg font-semibold">Sprachen</h1>
      </div>
      <button
        class="flex items-center gap-1.5 px-3 py-2 text-sm rounded-md bg-[var(--color-accent)] text-white"
        @click="openCreate"
      >
        <Plus class="w-4 h-4" /> Sprache anlegen
      </button>
    </div>

    <p class="text-xs text-[var(--color-text-secondary)] max-w-3xl">
      Diese Liste steuert, in welchen Sprachen Inhalte gepflegt werden: Produkteditor,
      Suche, Export und die maschinelle Übersetzung greifen alle darauf zu.
      Die Quellsprache ist der Ausgangspunkt jeder Übersetzung und kann weder
      deaktiviert noch gelöscht werden.
    </p>

    <div v-if="error" class="flex items-center gap-2 p-3 text-sm rounded-md bg-red-500/10 text-red-600">
      <AlertTriangle class="w-4 h-4" /> {{ error }}
    </div>

    <div class="border border-[var(--color-border)] rounded-md overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-[var(--color-surface)] text-[var(--color-text-secondary)]">
          <tr>
            <th class="text-left px-3 py-2 font-medium">Code</th>
            <th class="text-left px-3 py-2 font-medium">Bezeichnung</th>
            <th class="text-left px-3 py-2 font-medium">English</th>
            <th class="text-left px-3 py-2 font-medium">Rolle</th>
            <th class="text-left px-3 py-2 font-medium">Anzeige-Fallback</th>
            <th class="text-left px-3 py-2 font-medium">Status</th>
            <th class="text-right px-3 py-2 font-medium">Reihenfolge</th>
            <th class="w-24"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="px-3 py-6 text-center text-[var(--color-text-secondary)]">Wird geladen…</td>
          </tr>
          <tr v-else-if="!sorted.length">
            <td colspan="8" class="px-3 py-6 text-center text-[var(--color-text-secondary)]">Keine Sprachen gepflegt.</td>
          </tr>
          <tr
            v-for="row in sorted"
            :key="row.id"
            class="border-t border-[var(--color-border)] hover:bg-[var(--color-surface)]"
          >
            <td class="px-3 py-2 font-mono">{{ row.technical_name }}</td>
            <td class="px-3 py-2">{{ row.name_de }}</td>
            <td class="px-3 py-2 text-[var(--color-text-secondary)]">{{ row.name_en || '—' }}</td>
            <td class="px-3 py-2">
              <span v-if="row.is_source" class="px-2 py-0.5 text-xs rounded bg-[var(--color-accent)]/15 text-[var(--color-accent)]">
                Quellsprache
              </span>
              <span v-else class="text-xs text-[var(--color-text-secondary)]">Zielsprache</span>
            </td>
            <td class="px-3 py-2 text-xs text-[var(--color-text-secondary)]">
              <span v-if="chainFor(row).length" class="font-mono">{{ chainFor(row).join(' → ') }}</span>
              <span v-else>—</span>
            </td>
            <td class="px-3 py-2">
              <span v-if="row.is_active !== false" class="flex items-center gap-1 text-xs text-green-600">
                <Check class="w-3.5 h-3.5" /> aktiv
              </span>
              <span v-else class="text-xs text-[var(--color-text-secondary)]">inaktiv</span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums">{{ row.sort_order }}</td>
            <td class="px-3 py-2">
              <div class="flex items-center justify-end gap-1">
                <button class="p-1.5 rounded hover:bg-[var(--color-border)]" title="Bearbeiten" @click="openEdit(row)">
                  <Pencil class="w-4 h-4" />
                </button>
                <button
                  v-if="!row.is_source"
                  class="p-1.5 rounded hover:bg-red-500/10 text-red-600"
                  title="Löschen"
                  @click="remove(row)"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Dialog -->
    <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-[var(--color-bg)] border border-[var(--color-border)] shadow-xl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--color-border)]">
          <h2 class="text-sm font-semibold">{{ editing ? 'Sprache bearbeiten' : 'Sprache anlegen' }}</h2>
          <button class="p-1 rounded hover:bg-[var(--color-border)]" @click="showDialog = false">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="p-4 space-y-3">
          <div v-if="formError" class="p-2 text-xs rounded bg-red-500/10 text-red-600">{{ formError }}</div>

          <label class="block">
            <span class="text-xs text-[var(--color-text-secondary)]">Sprachcode (ISO 639-1)</span>
            <input
              v-model="form.technical_name"
              :disabled="!!editing && editing.is_source"
              maxlength="2"
              placeholder="fi"
              class="mt-1 w-full px-3 py-2 text-sm font-mono bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md disabled:opacity-50"
              @input="onCodeInput"
            />
            <span class="text-[11px] text-[var(--color-text-secondary)]">
              Zwei Kleinbuchstaben. Dieser Code wird an den Übersetzungen gespeichert
              und lässt sich später nur schwer ändern.
            </span>
          </label>

          <label class="block">
            <span class="text-xs text-[var(--color-text-secondary)]">Bezeichnung</span>
            <input
              v-model="form.name_de"
              placeholder="Finnisch"
              class="mt-1 w-full px-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
            />
          </label>

          <label class="block">
            <span class="text-xs text-[var(--color-text-secondary)]">Bezeichnung (englisch)</span>
            <input
              v-model="form.name_en"
              placeholder="Finnish"
              class="mt-1 w-full px-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
            />
          </label>

          <label class="block">
            <span class="text-xs text-[var(--color-text-secondary)]">Anzeige-Fallback</span>
            <select
              v-model="form.fallback_language"
              class="mt-1 w-full px-3 py-2 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
            >
              <option value="">— kein Fallback —</option>
              <option v-for="c in fallbackCandidates" :key="c.id" :value="c.technical_name">
                {{ c.name_de }} ({{ c.technical_name }})
              </option>
            </select>
            <span class="text-[11px] text-[var(--color-text-secondary)]">
              Fehlt im Produkteditor ein Wert in dieser Sprache, wird der Wert der
              Fallback-Sprache ausgegraut angezeigt. Es wird nichts gespeichert —
              erst ein Klick auf „Übernehmen" schreibt den Wert.
            </span>
          </label>

          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.is_active" type="checkbox" :disabled="editing?.is_source" />
              aktiv
            </label>
            <label class="flex items-center gap-2 text-sm">
              <span class="text-xs text-[var(--color-text-secondary)]">Reihenfolge</span>
              <input
                v-model.number="form.sort_order"
                type="number"
                class="w-20 px-2 py-1 text-sm bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md"
              />
            </label>
          </div>

          <p v-if="!editing" class="text-[11px] text-[var(--color-text-secondary)]">
            Nach dem Anlegen: Übersetzungen → Fehlend → „Alle fehlenden übersetzen",
            danach <code>php artisan tms:sync</code>.
          </p>
        </div>

        <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-[var(--color-border)]">
          <button class="px-3 py-2 text-sm rounded-md hover:bg-[var(--color-border)]" @click="showDialog = false">
            Abbrechen
          </button>
          <button
            class="px-3 py-2 text-sm rounded-md bg-[var(--color-accent)] text-white disabled:opacity-50"
            :disabled="saving || !form.technical_name || !form.name_de"
            @click="save"
          >
            {{ saving ? 'Speichert…' : 'Speichern' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
