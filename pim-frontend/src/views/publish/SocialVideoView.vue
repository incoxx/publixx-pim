<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { Clapperboard, Search, X, Plus, Sparkles, Loader2, Download } from 'lucide-vue-next'
import productsApi from '@/api/products'
import socialVideoApi from '@/api/socialVideo'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

// ─── Produktauswahl ────────────────────────────────────────
const searchTerm = ref('')
const searchResults = ref([])
const searching = ref(false)
const selected = ref([]) // { id, sku, name }

let searchTimer = null
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(runSearch, 300)
}

async function runSearch() {
  const term = searchTerm.value.trim()
  if (term.length < 2) {
    searchResults.value = []
    return
  }
  searching.value = true
  try {
    const { data } = await productsApi.list({ search: term, perPage: 15 })
    searchResults.value = data.data || data || []
  } catch (e) {
    toast.showToast('Produktsuche fehlgeschlagen', 'error')
  } finally {
    searching.value = false
  }
}

function addProduct(p) {
  if (selected.value.some(s => s.id === p.id)) return
  selected.value.push({ id: p.id, sku: p.sku, name: p.name })
}
function removeProduct(id) {
  selected.value = selected.value.filter(s => s.id !== id)
}

// ─── Optionen ──────────────────────────────────────────────
const format = ref('9x16')
const language = ref('de')
const useAi = ref(true)

const formats = [
  { value: '9x16', label: '9:16 · Reels / Shorts / TikTok' },
  { value: '1x1', label: '1:1 · Feed-Post' },
  { value: '16x9', label: '16:9 · YouTube' },
]

// ─── Generierung & Status ──────────────────────────────────
const generating = ref(false)
const jobId = ref(null)
const status = ref(null) // { status, message, download_url }
const reel = ref(null)
let pollTimer = null

const canGenerate = computed(() => selected.value.length > 0 && !generating.value)
const isRendering = computed(() => ['queued', 'rendering'].includes(status.value?.status))
const isDone = computed(() => status.value?.status === 'done')
const isFailed = computed(() => status.value?.status === 'failed')

async function generate() {
  if (!canGenerate.value) return
  generating.value = true
  status.value = null
  reel.value = null
  stopPolling()

  try {
    const { data } = await socialVideoApi.generate({
      product_ids: selected.value.map(s => s.id),
      format: format.value,
      languages: [language.value],
      use_ai: useAi.value,
    })
    jobId.value = data.job_id
    reel.value = data.reel
    status.value = { status: 'queued', message: 'In Warteschlange …' }
    toast.showToast('Reel wird erzeugt …', 'success')
    startPolling()
  } catch (e) {
    toast.showToast(e.response?.data?.message || 'Erstellung fehlgeschlagen', 'error')
  } finally {
    generating.value = false
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!jobId.value) return
    try {
      const { data } = await socialVideoApi.status(jobId.value)
      status.value = data
      if (data.status === 'done' || data.status === 'failed') {
        stopPolling()
        toast.showToast(
          data.status === 'done' ? 'Video fertig!' : 'Rendering fehlgeschlagen',
          data.status === 'done' ? 'success' : 'error',
        )
      }
    } catch {
      /* weiter pollen */
    }
  }, 2500)
}
function stopPolling() {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}
onBeforeUnmount(stopPolling)

const downloadHref = computed(() => (jobId.value ? socialVideoApi.downloadUrl(jobId.value) : '#'))

function sceneLabel(type) {
  return { hero: 'Aufmacher', feature: 'Highlight', price: 'Preis', cta: 'Call-to-Action' }[type] || type
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-2">
      <Clapperboard class="w-7 h-7 text-primary" />
      <h1 class="text-2xl font-bold">Social-Video</h1>
    </div>
    <p class="text-base-content/60 mb-6">
      Produkte auswählen → aus Bildtypen und Texten automatisch ein Social-Media-Video erzeugen.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Linke Spalte: Auswahl & Optionen -->
      <div class="space-y-6">
        <!-- Produktsuche -->
        <div class="card bg-base-100 shadow-sm border border-base-200">
          <div class="card-body">
            <h2 class="card-title text-base">1. Produkte wählen</h2>
            <label class="input input-bordered flex items-center gap-2">
              <Search class="w-4 h-4 opacity-60" />
              <input
                v-model="searchTerm"
                type="text"
                class="grow"
                placeholder="Produkt suchen (Name oder SKU) …"
                data-testid="social-video-search"
                @input="onSearchInput"
              />
              <Loader2 v-if="searching" class="w-4 h-4 animate-spin opacity-60" />
            </label>

            <ul v-if="searchResults.length" class="menu bg-base-200 rounded-box mt-2 max-h-64 overflow-y-auto">
              <li v-for="p in searchResults" :key="p.id">
                <button class="flex justify-between" @click="addProduct(p)">
                  <span class="truncate">
                    <span class="font-medium">{{ p.name || p.sku }}</span>
                    <span class="text-xs opacity-50 ml-2">{{ p.sku }}</span>
                  </span>
                  <Plus class="w-4 h-4 shrink-0" />
                </button>
              </li>
            </ul>

            <!-- Ausgewählte Produkte -->
            <div v-if="selected.length" class="flex flex-wrap gap-2 mt-3">
              <span
                v-for="s in selected"
                :key="s.id"
                class="badge badge-primary badge-lg gap-1"
              >
                {{ s.name || s.sku }}
                <button @click="removeProduct(s.id)"><X class="w-3 h-3" /></button>
              </span>
            </div>
            <p v-else class="text-sm opacity-50 mt-2">Noch keine Produkte ausgewählt.</p>
          </div>
        </div>

        <!-- Optionen -->
        <div class="card bg-base-100 shadow-sm border border-base-200">
          <div class="card-body">
            <h2 class="card-title text-base">2. Optionen</h2>

            <label class="form-control">
              <span class="label-text mb-1">Format</span>
              <select v-model="format" class="select select-bordered" data-testid="social-video-format">
                <option v-for="f in formats" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </label>

            <label class="form-control mt-3">
              <span class="label-text mb-1">Sprache</span>
              <select v-model="language" class="select select-bordered">
                <option value="de">Deutsch</option>
                <option value="en">Englisch</option>
                <option value="fr">Französisch</option>
              </select>
            </label>

            <label class="label cursor-pointer justify-start gap-3 mt-3">
              <input v-model="useAi" type="checkbox" class="toggle toggle-primary" />
              <span class="label-text flex items-center gap-1">
                <Sparkles class="w-4 h-4 text-primary" /> KI-Hook generieren (Claude)
              </span>
            </label>
          </div>
        </div>

        <button
          class="btn btn-primary btn-block"
          :disabled="!canGenerate"
          data-testid="social-video-generate"
          @click="generate"
        >
          <Loader2 v-if="generating" class="w-4 h-4 animate-spin" />
          <Clapperboard v-else class="w-4 h-4" />
          Video erstellen
        </button>
      </div>

      <!-- Rechte Spalte: Vorschau & Ergebnis -->
      <div class="space-y-6">
        <!-- Status -->
        <div v-if="status" class="card bg-base-100 shadow-sm border border-base-200">
          <div class="card-body">
            <h2 class="card-title text-base">Status</h2>
            <div v-if="isRendering" class="flex items-center gap-2 text-info">
              <Loader2 class="w-5 h-5 animate-spin" />
              <span>{{ status.message || 'Wird gerendert …' }}</span>
            </div>
            <div v-else-if="isFailed" class="alert alert-error text-sm">
              {{ status.message || 'Rendering fehlgeschlagen' }}
            </div>
            <div v-else-if="isDone" class="space-y-3">
              <video :src="status.download_url" controls class="w-full rounded-lg bg-black max-h-[480px] mx-auto" />
              <a :href="downloadHref" class="btn btn-success btn-sm" download>
                <Download class="w-4 h-4" /> MP4 herunterladen
              </a>
            </div>
          </div>
        </div>

        <!-- Szenen-Vorschau (Reel-Definition) -->
        <div v-if="reel" class="card bg-base-100 shadow-sm border border-base-200">
          <div class="card-body">
            <h2 class="card-title text-base">
              Storyboard
              <span class="badge badge-ghost">{{ reel.scenes.length }} Szenen</span>
            </h2>
            <ol class="space-y-2">
              <li
                v-for="(sc, i) in reel.scenes"
                :key="i"
                class="flex items-start gap-3 p-2 rounded-lg bg-base-200/50"
              >
                <span class="badge badge-sm badge-neutral mt-1">{{ i + 1 }}</span>
                <div class="min-w-0">
                  <div class="text-xs uppercase tracking-wide opacity-50">{{ sceneLabel(sc.type) }}</div>
                  <div class="font-medium truncate">
                    {{ sc.headline || (sc.value != null ? sc.value + ' ' + (sc.currency || '') : '—') }}
                  </div>
                  <div v-if="sc.sprecher" class="text-sm opacity-60 italic">„{{ sc.sprecher }}"</div>
                </div>
              </li>
            </ol>
          </div>
        </div>

        <!-- Leerzustand -->
        <div v-if="!status && !reel" class="text-center text-base-content/40 py-16">
          <Clapperboard class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p>Wähle Produkte und erstelle dein erstes Social-Video.</p>
        </div>
      </div>
    </div>
  </div>
</template>
