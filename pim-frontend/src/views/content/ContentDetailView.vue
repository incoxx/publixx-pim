<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useContentStore } from '@/stores/content'
import ContentSectionCard from '@/components/content/ContentSectionCard.vue'
import ContentPageFormPanel from '@/components/panels/ContentPageFormPanel.vue'
import { ChevronLeft, Plus, Pencil, Clock } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const contentStore = useContentStore()

const page = ref(null)
const sections = ref([])
const sectionTypes = ref([])
const loading = ref(true)
const activeTab = ref('sections')
const lang = ref('de')
const showAddPicker = ref(false)

const statusLabels = {
  draft: 'Entwurf', active: 'Aktiv', inactive: 'Inaktiv', archived: 'Archiviert',
}

// Map: section_type_id → SectionType (für Schema-Auflösung in den Karten)
const sectionTypeById = computed(() => {
  const m = {}
  for (const st of sectionTypes.value) m[st.id] = st
  return m
})

// Erlaubte Sektionstypen laut Seitentyp
const allowedSectionTypes = computed(() => {
  const allowed = page.value?.content_type?.allowed_section_types
  if (!allowed?.length) return sectionTypes.value
  return sectionTypes.value.filter((st) => allowed.includes(st.technical_name))
})

async function load() {
  loading.value = true
  try {
    sectionTypes.value = await contentStore.loadSectionTypes()
    page.value = await contentStore.fetchOne(route.params.id)
    sections.value = page.value.sections || []
  } finally {
    loading.value = false
  }
}

function editPage() {
  authStore.openPanel(ContentPageFormPanel, {
    page: page.value,
    onSaved: () => load(),
  }, '460px')
}

async function addSection(st) {
  showAddPicker.value = false
  const created = await contentStore.addSection(page.value.id, { section_type_id: st.id })
  // sectionType anreichern für sofortiges Rendern
  created.section_type = st
  sections.value.push(created)
}

async function saveSection(section, valuesJson) {
  await contentStore.updateSection(section.id, { values_json: valuesJson })
}

async function toggleVisible(section) {
  const next = !section.is_visible
  section.is_visible = next
  await contentStore.updateSection(section.id, { is_visible: next })
}

async function deleteSection(section) {
  if (!confirm('Sektion wirklich löschen?')) return
  await contentStore.removeSection(section.id)
  sections.value = sections.value.filter((s) => s.id !== section.id)
}

// ─── Drag & Drop Reihenfolge (nativ) ────────────────────────────
const dragIndex = ref(null)

function onDragStart(index) {
  dragIndex.value = index
}

async function onDrop(targetIndex) {
  const from = dragIndex.value
  dragIndex.value = null
  if (from === null || from === targetIndex) return

  const moved = sections.value.splice(from, 1)[0]
  sections.value.splice(targetIndex, 0, moved)

  // sort_order neu vergeben und geänderte Sektionen persistieren
  const updates = []
  sections.value.forEach((s, i) => {
    if (s.sort_order !== i) {
      s.sort_order = i
      updates.push(contentStore.moveSection(s.id, { sort_order: i, parent_section_id: s.parent_section_id || null }))
    }
  })
  await Promise.all(updates)
}

onMounted(load)
</script>

<template>
  <div class="space-y-4" data-testid="content-detail">
    <div v-if="loading" class="space-y-3">
      <div class="pim-skeleton h-8 w-1/3 rounded" />
      <div class="pim-skeleton h-40 rounded" />
    </div>

    <template v-else-if="page">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0">
          <button class="pim-btn pim-btn-ghost p-1" @click="router.push('/content')" title="Zurück">
            <ChevronLeft class="w-4 h-4" :stroke-width="2" />
          </button>
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)] truncate">{{ page.title }}</h2>
          <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-[var(--color-bg)] text-[var(--color-text-tertiary)]">
            {{ statusLabels[page.status] || page.status }}
          </span>
          <span class="text-xs text-[var(--color-text-tertiary)] font-mono truncate">/{{ page.slug }}</span>
          <span v-if="page.valid_from || page.valid_to" class="flex items-center gap-1 text-[10px] text-[var(--color-text-tertiary)]" title="Gültigkeitszeitraum">
            <Clock class="w-3 h-3" :stroke-width="2" />
            {{ page.valid_from ? new Date(page.valid_from).toLocaleDateString('de-DE') : '…' }}
            – {{ page.valid_to ? new Date(page.valid_to).toLocaleDateString('de-DE') : '…' }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <!-- Sprach-Umschaltung -->
          <div class="flex items-center border border-[var(--color-border)] rounded-md overflow-hidden">
            <button
              v-for="l in ['de', 'en']" :key="l"
              class="px-2 py-1 text-xs uppercase transition-colors"
              :class="lang === l ? 'bg-[var(--color-accent)] text-white' : 'bg-[var(--color-surface)] text-[var(--color-text-tertiary)] hover:bg-[var(--color-bg)]'"
              @click="lang = l"
            >{{ l }}</button>
          </div>
          <button class="pim-btn pim-btn-secondary text-xs" @click="editPage">
            <Pencil class="w-3.5 h-3.5" :stroke-width="2" /> Seite bearbeiten
          </button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex items-center gap-1 border-b border-[var(--color-border)]">
        <button
          v-for="tab in [{ k: 'sections', l: 'Sektionen' }, { k: 'seo', l: 'SEO' }]"
          :key="tab.k"
          class="px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors"
          :class="activeTab === tab.k
            ? 'border-[var(--color-accent)] text-[var(--color-text-primary)]'
            : 'border-transparent text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)]'"
          @click="activeTab = tab.k"
        >{{ tab.l }}</button>
      </div>

      <!-- Tab: Sektionen -->
      <div v-if="activeTab === 'sections'" class="space-y-3">
        <div
          v-for="(section, index) in sections"
          :key="section.id"
          draggable="true"
          @dragstart="onDragStart(index)"
          @dragover.prevent
          @drop="onDrop(index)"
        >
          <ContentSectionCard
            :section="section"
            :section-type="sectionTypeById[section.section_type_id] || section.section_type"
            :lang="lang"
            @save="(v) => saveSection(section, v)"
            @delete="deleteSection(section)"
            @toggle-visible="toggleVisible(section)"
          />
        </div>

        <p v-if="!sections.length" class="text-xs text-[var(--color-text-tertiary)] py-4 text-center">
          Noch keine Sektionen. Füge unten einen Block hinzu.
        </p>

        <!-- Block hinzufügen -->
        <div class="relative">
          <button class="pim-btn pim-btn-secondary text-xs w-full justify-center" @click="showAddPicker = !showAddPicker">
            <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Block hinzufügen
          </button>
          <div
            v-if="showAddPicker"
            class="absolute z-10 mt-1 w-full max-h-72 overflow-y-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg p-1"
          >
            <button
              v-for="st in allowedSectionTypes"
              :key="st.id"
              class="flex items-center justify-between w-full px-2 py-1.5 rounded hover:bg-[var(--color-bg)] text-left"
              @click="addSection(st)"
            >
              <span class="text-xs font-medium">{{ st.name_de || st.technical_name }}</span>
              <span class="text-[10px] text-[var(--color-text-tertiary)] uppercase">{{ st.category }}</span>
            </button>
            <p v-if="!allowedSectionTypes.length" class="text-xs text-[var(--color-text-tertiary)] px-2 py-1.5">
              Keine Sektionstypen für diesen Seitentyp erlaubt.
            </p>
          </div>
        </div>
      </div>

      <!-- Tab: SEO -->
      <div v-else-if="activeTab === 'seo'" class="max-w-lg space-y-3">
        <p class="text-xs text-[var(--color-text-tertiary)]">
          SEO-Titel und -Beschreibung ({{ lang.toUpperCase() }}). Bearbeitung folgt im SEO-Editor.
        </p>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SEO-Titel</label>
          <p class="pim-input text-xs bg-[var(--color-bg)]">{{ page.seo_title_json?.[lang] || '—' }}</p>
        </div>
        <div>
          <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SEO-Beschreibung</label>
          <p class="pim-input text-xs bg-[var(--color-bg)] min-h-16">{{ page.seo_description_json?.[lang] || '—' }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
