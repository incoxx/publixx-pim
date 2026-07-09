<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth'
import { useContentStore } from '@/stores/content'
import { useServerQuickLookup } from '@/composables/useServerQuickLookup'
import PimTable from '@/components/shared/PimTable.vue'
import ContentPageFormPanel from '@/components/panels/ContentPageFormPanel.vue'
import { Plus, Search, ChevronLeft, ChevronRight, Globe, ListFilter } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const contentStore = useContentStore()

const statusLabels = {
  draft: 'Entwurf', active: 'Aktiv', inactive: 'Inaktiv', archived: 'Archiviert',
}

const pimTableRef = ref(null)

// Quick Lookup — filtert serverseitig über die komplette Treffermenge
const quickLookupConfig = computed(() => ({
  title: { type: 'text', placeholder: 'Titel...' },
  slug: { type: 'text', placeholder: 'Slug...' },
  content_type: {
    type: 'select',
    options: contentStore.contentTypes.map(t => ({ value: t.id, label: t.name_de || t.technical_name })),
  },
  status: {
    type: 'select',
    options: Object.entries(statusLabels).map(([value, label]) => ({ value, label })),
  },
}))

const QUICK_LOOKUP_FIELD_MAP = {
  title: 'title',
  slug: 'slug',
  content_type: 'content_type_id',
  status: 'status',
}

function applyQuickLookupFilters(rawFilters) {
  const mapped = {}
  for (const [colKey, value] of Object.entries(rawFilters)) {
    if (value === '' || value == null) continue
    const field = QUICK_LOOKUP_FIELD_MAP[colKey]
    if (!field) continue
    mapped[field] = value
  }
  contentStore.setFilters(mapped)
  load()
}

const { showQuickLookup, onQuickLookupChange, toggleQuickLookup } = useServerQuickLookup(applyQuickLookupFilters)

const columns = [
  { key: 'title', label: 'Titel', sortable: true },
  { key: 'slug', label: 'Slug', render: (r) => r.slug },
  { key: 'content_type', label: 'Typ', render: (r) => r.content_type?.name_de || '—' },
  { key: 'status', label: 'Status', render: (r) => statusLabels[r.status] || r.status },
  {
    key: 'validity', label: 'Sichtbarkeit',
    render: (r) => (r.is_currently_valid ? 'sichtbar' : 'verborgen'),
  },
  {
    key: 'updated_at', label: 'Geändert', sortable: true,
    render: (r) => (r.updated_at ? new Date(r.updated_at).toLocaleDateString('de-DE') : '—'),
  },
]

async function load() {
  await contentStore.fetchList({ include: 'contentType' })
}

function onSort(field, order) {
  contentStore.setSort(field, order)
  contentStore.setPage(1)
  load()
}

const searchTerm = ref('')
const onSearch = useDebounceFn(() => {
  contentStore.setSearch(searchTerm.value)
  load()
}, 300)

function openPage(row) {
  router.push(`/content/${row.id}`)
}

function newPage() {
  authStore.openPanel(ContentPageFormPanel, {
    onSaved: (page) => router.push(`/content/${page.id}`),
  }, '460px')
}

async function deletePage(row) {
  if (!confirm(`Seite "${row.title}" wirklich löschen?`)) return
  await contentStore.remove(row.id)
  load()
}

function goToPage(page) {
  contentStore.setPage(page)
  load()
}

onMounted(() => {
  load()
  contentStore.loadContentTypes()
})
</script>

<template>
  <div class="space-y-4" data-testid="content-list">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Content</h2>
      <div class="flex items-center gap-2">
        <div class="relative">
          <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
          <input
            v-model="searchTerm"
            class="pim-input text-xs pim-input-icon"
            placeholder="Seiten suchen…"
            @input="onSearch"
          />
        </div>
        <button
          class="pim-btn pim-btn-secondary text-xs"
          :class="showQuickLookup ? 'bg-[var(--color-accent-light)] text-[var(--color-accent)]' : ''"
          @click="toggleQuickLookup(pimTableRef)"
          title="Quick Lookup"
        >
          <ListFilter class="w-3.5 h-3.5" :stroke-width="1.75" />
          <span class="hidden sm:inline">Quick Lookup</span>
        </button>
        <button class="pim-btn pim-btn-secondary text-xs" @click="router.push({ name: 'website-preview' })">
          <Globe class="w-3.5 h-3.5" :stroke-width="2" /> Website-Vorschau
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="newPage" data-testid="btn-new-page">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Neue Seite
        </button>
      </div>
    </div>

    <PimTable
      ref="pimTableRef"
      :columns="columns"
      :rows="contentStore.items"
      :loading="contentStore.loading"
      :sort-field="contentStore.sort.field"
      :sort-order="contentStore.sort.order"
      :quickLookup="showQuickLookup"
      :quickLookupConfig="quickLookupConfig"
      empty-text="Noch keine Content-Seiten"
      @sort="onSort"
      @row-click="openPage"
      @row-action="deletePage"
      @quick-lookup-change="onQuickLookupChange"
    >
      <template #pagination>
        <div v-if="contentStore.meta.last_page > 1" class="flex items-center justify-between pt-2">
          <span class="text-[11px] text-[var(--color-text-tertiary)]">
            Seite {{ contentStore.meta.current_page }} von {{ contentStore.meta.last_page }}
            ({{ contentStore.meta.total }} gesamt)
          </span>
          <div class="flex items-center gap-1">
            <button
              class="pim-btn pim-btn-ghost p-1 disabled:opacity-30"
              :disabled="contentStore.meta.current_page <= 1"
              @click="goToPage(contentStore.meta.current_page - 1)"
            >
              <ChevronLeft class="w-4 h-4" :stroke-width="2" />
            </button>
            <button
              class="pim-btn pim-btn-ghost p-1 disabled:opacity-30"
              :disabled="contentStore.meta.current_page >= contentStore.meta.last_page"
              @click="goToPage(contentStore.meta.current_page + 1)"
            >
              <ChevronRight class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>
        </div>
      </template>
    </PimTable>
  </div>
</template>
