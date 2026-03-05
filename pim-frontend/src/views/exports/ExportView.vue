<script setup>
import { ref, onMounted, computed } from 'vue'
import {
  Download, FileSpreadsheet, FileJson, FileCode, FileText,
  Package, Tag, DollarSign, Link, Image, Layers,
} from 'lucide-vue-next'
import exportsApi from '@/api/exports'
import exportProfilesApi from '@/api/exportProfiles'
import searchProfilesApi from '@/api/searchProfiles'
import searchApi from '@/api/search'
import ProfileSelector from '@/components/shared/ProfileSelector.vue'
import SearchFilterPanel from '@/components/shared/SearchFilterPanel.vue'

// --- State ---
const activeTab = ref('filter') // 'filter' | 'data' | 'channel'
const error = ref('')
const exporting = ref(false)
const exportingFormat = ref(false)

// Filter
const searchProfiles = ref([])
const selectedSearchProfileId = ref(null)
const filters = ref({
  status: '',
  category_ids: [],
  attribute_filters: {},
  include_descendants: true,
})
const productCount = ref(null)
const countLoading = ref(false)

// Data selection
const includeProducts = ref(true)
const includeAttributes = ref(true)
const includeHierarchies = ref(false)
const includePrices = ref(false)
const includeRelations = ref(false)
const includeMedia = ref(false)
const includeVariants = ref(false)
const selectedLanguages = ref(['de'])

// Channel
const format = ref('excel')
const fileName = ref('')

// Export Profiles
const exportProfiles = ref([])
const selectedExportProfileId = ref(null)

// --- Load ---
onMounted(async () => {
  try {
    const [profilesRes, searchProfilesRes] = await Promise.all([
      exportProfilesApi.list(),
      searchProfilesApi.list(),
    ])
    exportProfiles.value = profilesRes.data.data || profilesRes.data
    searchProfiles.value = searchProfilesRes.data.data || searchProfilesRes.data
  } catch (e) { /* ignore */ }
})

// --- Actions ---
async function countProducts() {
  countLoading.value = true
  try {
    const params = { per_page: 1 }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.category_ids?.length) {
      params.category_ids = filters.value.category_ids
      params.include_descendants = true
    }
    const attrFilters = []
    for (const [attrId, val] of Object.entries(filters.value.attribute_filters || {})) {
      if (val !== '' && val !== null && val !== undefined) {
        attrFilters.push({ attribute_id: attrId, value: val, operator: 'eq' })
      }
    }
    if (attrFilters.length) params.attribute_filters = attrFilters
    const { data } = await searchApi.search(params)
    productCount.value = data.meta?.total ?? 0
  } catch (e) {
    productCount.value = null
  } finally { countLoading.value = false }
}

function loadSearchProfile(id) {
  const profile = searchProfiles.value.find(p => p.id === id)
  if (!profile) return
  filters.value = {
    status: profile.status_filter || '',
    category_ids: profile.category_ids || [],
    attribute_filters: profile.attribute_filters || {},
    include_descendants: profile.include_descendants ?? true,
  }
  selectedSearchProfileId.value = id
  countProducts()
}

function loadExportProfile(id) {
  const profile = exportProfiles.value.find(p => p.id === id)
  if (!profile) return
  includeProducts.value = profile.include_products
  includeAttributes.value = profile.include_attributes
  includeHierarchies.value = profile.include_hierarchies
  includePrices.value = profile.include_prices
  includeRelations.value = profile.include_relations
  includeMedia.value = profile.include_media
  includeVariants.value = profile.include_variants
  selectedLanguages.value = profile.languages || ['de']
  format.value = profile.format || 'excel'
  fileName.value = profile.file_name_template || ''
  if (profile.search_profile_id) {
    loadSearchProfile(profile.search_profile_id)
  }
}

async function saveExportProfile({ name, is_shared }) {
  try {
    await exportProfilesApi.create({
      name,
      is_shared,
      search_profile_id: selectedSearchProfileId.value,
      include_products: includeProducts.value,
      include_attributes: includeAttributes.value,
      include_hierarchies: includeHierarchies.value,
      include_prices: includePrices.value,
      include_relations: includeRelations.value,
      include_media: includeMedia.value,
      include_variants: includeVariants.value,
      languages: selectedLanguages.value,
      format: format.value,
      file_name_template: fileName.value || null,
    })
    const { data } = await exportProfilesApi.list()
    exportProfiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht gespeichert werden'
  }
}

async function deleteExportProfile(id) {
  try {
    await exportProfilesApi.remove(id)
    selectedExportProfileId.value = null
    const { data } = await exportProfilesApi.list()
    exportProfiles.value = data.data || data
  } catch (e) {
    error.value = 'Profil konnte nicht gelöscht werden'
  }
}

async function runExport() {
  if (!selectedExportProfileId.value) {
    // Erst Profil speichern (temporär)
    try {
      const { data } = await exportProfilesApi.create({
        name: `Export ${new Date().toISOString().slice(0, 16)}`,
        is_shared: false,
        search_profile_id: selectedSearchProfileId.value,
        include_products: includeProducts.value,
        include_attributes: includeAttributes.value,
        include_hierarchies: includeHierarchies.value,
        include_prices: includePrices.value,
        include_relations: includeRelations.value,
        include_media: includeMedia.value,
        include_variants: includeVariants.value,
        languages: selectedLanguages.value,
        format: format.value,
        file_name_template: fileName.value || null,
      })
      selectedExportProfileId.value = (data.data || data).id
    } catch (e) {
      error.value = 'Export-Profil konnte nicht erstellt werden'
      return
    }
  }

  exporting.value = true
  error.value = ''
  try {
    const response = await exportProfilesApi.execute(selectedExportProfileId.value, {
      file_name: fileName.value || undefined,
    })
    const ext = { excel: 'xlsx', csv: 'csv', json: 'json', xml: 'xml' }[format.value] || 'xlsx'
    const name = fileName.value || `export-${new Date().toISOString().slice(0, 10)}`
    triggerDownload(new Blob([response.data]), `${name}.${ext}`)
  } catch (e) {
    error.value = e.response?.data?.title || 'Export fehlgeschlagen'
  } finally { exporting.value = false }
}

async function exportImportFormat() {
  exportingFormat.value = true
  error.value = ''
  try {
    const response = await exportsApi.exportAsImportFormat()
    triggerDownload(new Blob([response.data]), `pim-export-${new Date().toISOString().slice(0, 10)}.xlsx`)
  } catch (e) {
    error.value = e.response?.data?.title || 'Export fehlgeschlagen'
  } finally { exportingFormat.value = false }
}

function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 200)
}

const formatOptions = [
  { value: 'excel', label: 'Excel (.xlsx)', icon: FileSpreadsheet },
  { value: 'csv', label: 'CSV (.csv)', icon: FileText },
  { value: 'json', label: 'JSON (.json)', icon: FileJson },
  { value: 'xml', label: 'XML (.xml)', icon: FileCode },
]

const tabs = [
  { key: 'filter', label: 'Suchfilter' },
  { key: 'data', label: 'Datenauswahl' },
  { key: 'channel', label: 'Export-Kanal' },
]
</script>

<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Export</h2>
      <button class="pim-btn pim-btn-secondary text-xs" :disabled="exportingFormat" @click="exportImportFormat">
        <Download class="w-3.5 h-3.5" :stroke-width="1.75" />
        {{ exportingFormat ? 'Exportieren...' : 'Komplett-Export (14-Sheet)' }}
      </button>
    </div>

    <!-- Export Profile Selector -->
    <ProfileSelector
      :profiles="exportProfiles"
      v-model="selectedExportProfileId"
      label="Export-Profil"
      @load="loadExportProfile"
      @save="saveExportProfile"
      @delete="deleteExportProfile"
    />

    <!-- Tabs -->
    <div class="flex border-b border-[var(--color-border)]">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="[
          'px-4 py-2.5 text-xs font-medium border-b-2 -mb-px transition-colors',
          activeTab === tab.key
            ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
            : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]',
        ]"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab: Suchfilter -->
    <div v-if="activeTab === 'filter'" class="space-y-4">
      <div class="flex items-center gap-3 mb-2">
        <p class="text-xs text-[var(--color-text-secondary)]">Suchprofil verwenden:</p>
        <select
          class="pim-input text-xs w-64"
          :value="selectedSearchProfileId"
          @change="loadSearchProfile($event.target.value)"
        >
          <option value="">— Eigene Filter setzen —</option>
          <option v-for="p in searchProfiles" :key="p.id" :value="p.id">
            {{ p.name }}{{ p.is_shared ? ' (geteilt)' : '' }}
          </option>
        </select>
      </div>

      <SearchFilterPanel v-model="filters" />

      <div class="flex items-center gap-3">
        <button class="pim-btn pim-btn-secondary text-xs" @click="countProducts" :disabled="countLoading">
          {{ countLoading ? 'Zähle...' : 'Produkte zählen' }}
        </button>
        <span v-if="productCount !== null" class="text-xs text-[var(--color-text-secondary)]">
          {{ productCount }} Produkte gefunden
        </span>
      </div>
    </div>

    <!-- Tab: Datenauswahl -->
    <div v-if="activeTab === 'data'" class="pim-card p-5 space-y-4">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Welche Daten exportieren?</h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includeProducts" class="rounded" disabled />
          <Package class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Produkte
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includeAttributes" class="rounded" />
          <Tag class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Attribute
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includeHierarchies" class="rounded" />
          <Layers class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Hierarchien
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includePrices" class="rounded" />
          <DollarSign class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Preise
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includeRelations" class="rounded" />
          <Link class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Beziehungen
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includeMedia" class="rounded" />
          <Image class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Medien
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-xs">
          <input type="checkbox" v-model="includeVariants" class="rounded" />
          <Layers class="w-4 h-4 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          Varianten
        </label>
      </div>

      <div>
        <p class="text-[12px] font-medium text-[var(--color-text-secondary)] mb-2">Sprachen</p>
        <div class="flex gap-3">
          <label v-for="lang in ['de', 'en', 'fr', 'it', 'es']" :key="lang" class="flex items-center gap-1.5 text-xs cursor-pointer">
            <input
              type="checkbox"
              :checked="selectedLanguages.includes(lang)"
              @change="selectedLanguages.includes(lang) ? selectedLanguages.splice(selectedLanguages.indexOf(lang), 1) : selectedLanguages.push(lang)"
              class="rounded"
            />
            {{ lang.toUpperCase() }}
          </label>
        </div>
      </div>
    </div>

    <!-- Tab: Export-Kanal -->
    <div v-if="activeTab === 'channel'" class="pim-card p-5 space-y-4">
      <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Export-Format</h3>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <button
          v-for="opt in formatOptions"
          :key="opt.value"
          :class="[
            'flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-colors cursor-pointer',
            format === opt.value
              ? 'border-[var(--color-accent)] bg-[color-mix(in_srgb,var(--color-accent)_5%,transparent)]'
              : 'border-[var(--color-border)] hover:border-[var(--color-accent)]/50',
          ]"
          @click="format = opt.value"
        >
          <component :is="opt.icon" class="w-6 h-6" :class="format === opt.value ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-tertiary)]'" :stroke-width="1.5" />
          <span class="text-xs font-medium" :class="format === opt.value ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-secondary)]'">{{ opt.label }}</span>
        </button>
      </div>

      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Dateiname (optional)</label>
        <input
          v-model="fileName"
          class="pim-input text-xs w-full max-w-md"
          :placeholder="`export-${new Date().toISOString().slice(0,10)}`"
        />
        <p class="text-[10px] text-[var(--color-text-tertiary)] mt-1">
          Platzhalter: {date}, {profile}, {format}
        </p>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">{{ error }}</div>

    <!-- Export Button -->
    <div class="flex gap-3">
      <button
        class="pim-btn pim-btn-primary"
        :disabled="exporting"
        @click="runExport"
      >
        <Download class="w-4 h-4" :stroke-width="2" />
        {{ exporting ? 'Exportieren...' : 'Export starten' }}
      </button>
    </div>

    <!-- REST/CLI Hinweis -->
    <div class="pim-card p-4 bg-[var(--color-bg)]">
      <p class="text-[11px] font-medium text-[var(--color-text-secondary)] mb-2">REST / CLI</p>
      <div class="space-y-1 text-[10px] font-mono text-[var(--color-text-tertiary)]">
        <p>curl -X POST /api/v1/export-profiles/{id}/execute -H "Authorization: Bearer {token}" -o export.xlsx</p>
        <p>php artisan pim:export {profile-id} --format=csv --file-name=mein-export</p>
        <p>php artisan pim:export --list</p>
      </div>
    </div>
  </div>
</template>
