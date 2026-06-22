<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { Clapperboard, Search, X, Plus, Sparkles, Loader2, Download, Palette, SlidersHorizontal } from 'lucide-vue-next'
import productsApi from '@/api/products'
import socialVideoApi from '@/api/socialVideo'
import attributesApi from '@/api/attributes'
import mediaUsageTypesApi from '@/api/mediaUsageTypes'
import { priceTypes as priceTypesApi } from '@/api/prices'
import { useToastStore } from '@/stores/toast'
import ScenePreview from './ScenePreview.vue'

const toast = useToastStore()

// ─── Inhalts-Quellen (konfigurierbar, nichts hartkodiert) ──
// Feldliste kommt aus dem Backend (SocialVideoElementMap::configurableFields).
const contentFields = ref([])        // [{ target, label, kind, type, default }]
const usageTypeOptions = ref([])     // [{ value, label }]
const attributeOptions = ref([])
const priceTypeOptions = ref([])
const fieldSources = reactive({})    // { [target]: technical_name | '' }

function optionsForKind(kind) {
  if (kind === 'media') return usageTypeOptions.value
  if (kind === 'price') return priceTypeOptions.value
  return attributeOptions.value
}

async function loadContentConfig() {
  try {
    const [meta, usage, attrs, prices] = await Promise.all([
      socialVideoApi.defaultMapping(),
      mediaUsageTypesApi.list(),
      attributesApi.list({ perPage: 1000, sort: 'name_de' }),
      priceTypesApi.list(),
    ])

    const toOpts = (res) => (res.data?.data || res.data || [])
      .map(x => ({ value: x.technical_name, label: x.name_de || x.technical_name }))
      .filter(o => o.value)

    usageTypeOptions.value = toOpts(usage)
    attributeOptions.value = toOpts(attrs)
    priceTypeOptions.value = toOpts(prices)

    contentFields.value = meta.data?.fields || []
    // Vorbelegung: Standardwert nur übernehmen, wenn er in den Optionen existiert.
    for (const f of contentFields.value) {
      const opts = optionsForKind(f.kind)
      fieldSources[f.target] = opts.some(o => o.value === f.default) ? f.default : ''
    }
  } catch (e) {
    toast.showToast('Inhalts-Konfiguration konnte nicht geladen werden', 'error')
  }
}
onMounted(loadContentConfig)

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

// ─── Look & Stil ───────────────────────────────────────────
const style = reactive({
  brief: '',
  tonality: '',
  accent: '#06b6d4',
  background: '#0f0f23',
  transition: 'fade',
  media_animation: 'kenburns',
})

const transitions = [
  { value: 'fade', label: 'Weich' },
  { value: 'slide', label: 'Slide' },
  { value: 'zoom', label: 'Zoom' },
  { value: 'cut', label: 'Hart' },
]

const mediaAnimations = [
  { value: 'kenburns', label: 'Ken Burns' },
  { value: 'zoom-in', label: 'Zoom in' },
  { value: 'zoom-out', label: 'Zoom out' },
  { value: 'fade-in', label: 'Fade in' },
  { value: 'fade-out', label: 'Fade out' },
  { value: 'pan', label: 'Schwenk' },
  { value: 'none', label: 'Keine' },
]

const stylePresets = [
  { name: 'Tech', accent: '#06b6d4', background: '#0f0f23', tonality: 'technisch, präzise, hochwertig', transition: 'slide', media_animation: 'zoom-in' },
  { name: 'Bold', accent: '#f43f5e', background: '#1c0a14', tonality: 'laut, mutig, energiegeladen', transition: 'zoom', media_animation: 'zoom-out' },
  { name: 'Elegant', accent: '#d4af37', background: '#14110a', tonality: 'edel, ruhig, luxuriös', transition: 'fade', media_animation: 'kenburns' },
  { name: 'Frisch', accent: '#22c55e', background: '#08160f', tonality: 'natürlich, freundlich, leicht', transition: 'fade', media_animation: 'fade-in' },
]

function applyPreset(p) {
  style.accent = p.accent
  style.background = p.background
  style.tonality = p.tonality
  style.transition = p.transition
  style.media_animation = p.media_animation
}

function transitionLabel(value) {
  return transitions.find(t => t.value === value)?.label || value
}

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
      field_sources: { ...fieldSources },
      style: { ...style },
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
    <div class="flex items-center gap-3 mb-1">
      <Clapperboard class="w-6 h-6 text-[var(--color-accent)]" :stroke-width="1.75" />
      <h1 class="text-xl font-semibold text-[var(--color-text-primary)]">Social-Video</h1>
    </div>
    <p class="text-[13px] text-[var(--color-text-tertiary)] mb-6">
      Produkte auswählen → aus Bildtypen und Texten automatisch ein Social-Media-Video erzeugen.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Linke Spalte: Auswahl & Optionen -->
      <div class="space-y-4">

        <!-- 1. Produktsuche -->
        <div class="pim-card p-4">
          <h2 class="text-[13px] font-semibold text-[var(--color-text-primary)] mb-3">1. Produkte wählen</h2>

          <div class="relative">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            <input
              v-model="searchTerm"
              type="text"
              class="pim-input text-xs w-full pl-8"
              placeholder="Produkt suchen (Name oder SKU) …"
              data-testid="social-video-search"
              @input="onSearchInput"
            />
            <Loader2 v-if="searching" class="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 animate-spin text-[var(--color-text-tertiary)]" />
          </div>

          <ul v-if="searchResults.length" class="mt-2 border border-[var(--color-border)] rounded-lg overflow-hidden max-h-56 overflow-y-auto bg-[var(--color-surface)]">
            <li v-for="p in searchResults" :key="p.id">
              <button
                class="w-full flex items-center justify-between px-3 py-2 text-xs hover:bg-[var(--color-bg)] transition-colors text-left"
                @click="addProduct(p)"
              >
                <span class="truncate">
                  <span class="font-medium text-[var(--color-text-primary)]">{{ p.name || p.sku }}</span>
                  <span class="text-[var(--color-text-tertiary)] ml-2">{{ p.sku }}</span>
                </span>
                <Plus class="w-3.5 h-3.5 shrink-0 text-[var(--color-accent)]" :stroke-width="2" />
              </button>
            </li>
          </ul>

          <!-- Ausgewählte Produkte -->
          <div v-if="selected.length" class="flex flex-wrap gap-1.5 mt-3">
            <span
              v-for="s in selected"
              :key="s.id"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-[color-mix(in_srgb,var(--color-accent)_10%,transparent)] text-[var(--color-accent)] border border-[color-mix(in_srgb,var(--color-accent)_25%,transparent)]"
            >
              {{ s.name || s.sku }}
              <button @click="removeProduct(s.id)" class="hover:text-[var(--color-error)] transition-colors">
                <X class="w-3 h-3" />
              </button>
            </span>
          </div>
          <p v-else class="text-[11px] text-[var(--color-text-tertiary)] mt-2">Noch keine Produkte ausgewählt.</p>
        </div>

        <!-- 2. Optionen -->
        <div class="pim-card p-4">
          <h2 class="text-[13px] font-semibold text-[var(--color-text-primary)] mb-3">2. Optionen</h2>

          <div class="space-y-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Format</label>
              <select v-model="format" class="pim-input text-xs w-full" data-testid="social-video-format">
                <option v-for="f in formats" :key="f.value" :value="f.value">{{ f.label }}</option>
              </select>
            </div>

            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Sprache</label>
              <select v-model="language" class="pim-input text-xs w-full">
                <option value="de">Deutsch</option>
                <option value="en">Englisch</option>
                <option value="fr">Französisch</option>
              </select>
            </div>

            <label class="flex items-center gap-2.5 cursor-pointer group">
              <input v-model="useAi" type="checkbox" class="w-4 h-4 rounded accent-[var(--color-accent)]" />
              <span class="text-[12px] text-[var(--color-text-secondary)] flex items-center gap-1.5">
                <Sparkles class="w-3.5 h-3.5 text-[var(--color-accent)]" />
                KI-Hook generieren (Claude)
              </span>
            </label>
          </div>
        </div>

        <!-- 3. Inhalte / Quellen -->
        <div class="pim-card p-4">
          <h2 class="text-[13px] font-semibold text-[var(--color-text-primary)] mb-1 flex items-center gap-1.5">
            <SlidersHorizontal class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
            3. Inhalte
          </h2>
          <p class="text-[11px] text-[var(--color-text-tertiary)] mb-3">
            Lege fest, welches Bild und welche Texte/Preise das Video verwendet. „(leer)" lässt ein Feld weg.
          </p>
          <div v-if="contentFields.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="f in contentFields" :key="f.target">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">{{ f.label }}</label>
              <select v-model="fieldSources[f.target]" class="pim-input text-xs w-full" :data-testid="`social-video-source-${f.target}`">
                <option value="">— (leer) —</option>
                <option v-for="o in optionsForKind(f.kind)" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
          </div>
          <p v-else class="text-[11px] text-[var(--color-text-tertiary)]">Wird geladen …</p>
        </div>

        <!-- 4. Look & Stil -->
        <div class="pim-card p-4">
          <h2 class="text-[13px] font-semibold text-[var(--color-text-primary)] mb-3 flex items-center gap-1.5">
            <Palette class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
            4. Look &amp; Stil
          </h2>

          <!-- Presets -->
          <div class="flex flex-wrap gap-1.5 mb-3">
            <button
              v-for="p in stylePresets"
              :key="p.name"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] hover:bg-[var(--color-bg)] hover:border-[var(--color-border-strong)] transition-colors text-[var(--color-text-secondary)]"
              @click="applyPreset(p)"
            >
              <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: p.accent }"></span>
              {{ p.name }}
            </button>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Kreativ-Briefing <span class="font-normal text-[var(--color-text-tertiary)]">(optional)</span></label>
              <textarea
                v-model="style.brief"
                class="pim-input text-xs w-full h-16 resize-none"
                placeholder="z.B. Fokus auf Nachhaltigkeit, Outdoor-Feeling, junge Zielgruppe …"
              ></textarea>
            </div>

            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Tonalität</label>
              <input
                v-model="style.tonality"
                type="text"
                class="pim-input text-xs w-full"
                placeholder="z.B. jung, energiegeladen, direkt"
              />
            </div>

            <div class="flex gap-3">
              <div>
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Akzent</label>
                <input v-model="style.accent" type="color" class="w-10 h-8 rounded cursor-pointer border border-[var(--color-border)] bg-[var(--color-surface)] p-0.5" />
              </div>
              <div>
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Hintergrund</label>
                <input v-model="style.background" type="color" class="w-10 h-8 rounded cursor-pointer border border-[var(--color-border)] bg-[var(--color-surface)] p-0.5" />
              </div>
              <div class="flex-1">
                <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Übergang</label>
                <select v-model="style.transition" class="pim-input text-xs w-full">
                  <option v-for="t in transitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Medien-Animation</label>
              <select v-model="style.media_animation" class="pim-input text-xs w-full" data-testid="social-video-media-anim">
                <option v-for="m in mediaAnimations" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
            </div>
          </div>
        </div>

        <button
          class="pim-btn pim-btn-primary w-full"
          :disabled="!canGenerate"
          data-testid="social-video-generate"
          @click="generate"
        >
          <Loader2 v-if="generating" class="w-4 h-4 animate-spin" />
          <Clapperboard v-else class="w-4 h-4" :stroke-width="1.75" />
          Video erstellen
        </button>
      </div>

      <!-- Rechte Spalte: Vorschau & Ergebnis -->
      <div class="space-y-4">

        <!-- Status -->
        <div v-if="status" class="pim-card p-4">
          <h2 class="text-[13px] font-semibold text-[var(--color-text-primary)] mb-3">Status</h2>
          <div v-if="isRendering" class="flex items-center gap-2 text-[13px] text-[var(--color-info)]">
            <Loader2 class="w-4 h-4 animate-spin" />
            <span>{{ status.message || 'Wird gerendert …' }}</span>
          </div>
          <div v-else-if="isFailed" class="flex items-start gap-2 p-3 rounded-lg bg-[var(--color-error-light)] text-[13px] text-[var(--color-error)]">
            {{ status.message || 'Rendering fehlgeschlagen' }}
          </div>
          <div v-else-if="isDone" class="space-y-3">
            <video :src="status.download_url" controls class="w-full rounded-lg bg-black max-h-[480px] mx-auto" />
            <a :href="downloadHref" class="pim-btn pim-btn-secondary text-xs" download>
              <Download class="w-3.5 h-3.5" :stroke-width="2" /> MP4 herunterladen
            </a>
          </div>
        </div>

        <!-- Szenen-Vorschau (Reel-Definition) -->
        <div v-if="reel" class="pim-card p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-[13px] font-semibold text-[var(--color-text-primary)]">Storyboard</h2>
            <div class="flex items-center gap-2">
              <span class="text-[11px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">
                {{ reel.scenes.length }} Szenen
              </span>
              <span class="text-[11px] px-1.5 py-0.5 rounded bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">
                {{ transitionLabel(reel.meta?.style?.transition || style.transition) }}
              </span>
            </div>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div v-for="(sc, i) in reel.scenes" :key="i" class="space-y-1">
              <div class="relative">
                <ScenePreview
                  :scene="sc"
                  :format="reel.meta?.format || format"
                  :theme="reel.meta?.style || style"
                />
                <span class="absolute top-1 left-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-black/60 text-white">{{ i + 1 }}</span>
              </div>
              <div class="text-[10px] uppercase tracking-wide text-[var(--color-text-tertiary)]">{{ sceneLabel(sc.type) }}</div>
              <div v-if="sc.sprecher" class="text-[11px] text-[var(--color-text-tertiary)] italic leading-tight line-clamp-2">
                „{{ sc.sprecher }}"
              </div>
            </div>
          </div>
        </div>

        <!-- Leerzustand -->
        <div v-if="!status && !reel" class="pim-card p-12 text-center">
          <Clapperboard class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)] opacity-40" :stroke-width="1.5" />
          <p class="text-[13px] text-[var(--color-text-tertiary)]">Wähle Produkte und erstelle dein erstes Social-Video.</p>
        </div>
      </div>
    </div>
  </div>
</template>
