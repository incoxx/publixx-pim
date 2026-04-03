<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { StickyNote, Plus, Pin, PinOff, Trash2, X, Package, Link2, Users, Lock } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import DashboardWidgetWrapper from './DashboardWidgetWrapper.vue'
import notesApi from '@/api/notes'
import searchApi from '@/api/search'

const authStore = useAuthStore()

const notes = ref([])
const loading = ref(false)
const showForm = ref(false)
const editingNote = ref(null)

const NOTE_COLORS = [
  { key: 'yellow', bg: '#fef9c3', border: '#facc15', text: '#713f12' },
  { key: 'blue', bg: '#dbeafe', border: '#60a5fa', text: '#1e3a5f' },
  { key: 'green', bg: '#dcfce7', border: '#4ade80', text: '#14532d' },
  { key: 'pink', bg: '#fce7f3', border: '#f472b6', text: '#831843' },
  { key: 'purple', bg: '#f3e8ff', border: '#c084fc', text: '#581c87' },
  { key: 'orange', bg: '#ffedd5', border: '#fb923c', text: '#7c2d12' },
]

const form = ref({ title: '', body: '', color: 'yellow', is_shared: false, product_id: null })

const isOwnNote = (note) => note.created_by === authStore.user?.id

// Produkt-Suche
const productSearch = ref('')
const productResults = ref([])
const searchTimeout = ref(null)
const linkedProduct = ref(null)

function getColor(key) {
  return NOTE_COLORS.find(c => c.key === key) || NOTE_COLORS[0]
}

async function fetchNotes() {
  loading.value = true
  try {
    const { data } = await notesApi.list()
    notes.value = data.data || data
  } catch { /* ignore */ }
  loading.value = false
}

function openNewNote() {
  editingNote.value = null
  form.value = { title: '', body: '', color: 'yellow', is_shared: false, product_id: null }
  linkedProduct.value = null
  productSearch.value = ''
  showForm.value = true
}

function openEditNote(note) {
  editingNote.value = note
  form.value = {
    title: note.title,
    body: note.body || '',
    color: note.color,
    is_shared: note.is_shared,
    product_id: note.product_id,
  }
  linkedProduct.value = note.product || null
  productSearch.value = ''
  showForm.value = true
}

async function saveNote() {
  if (!form.value.title.trim()) return
  try {
    if (editingNote.value) {
      await notesApi.update(editingNote.value.id, form.value)
    } else {
      await notesApi.create(form.value)
    }
    showForm.value = false
    await fetchNotes()
  } catch { /* ignore */ }
}

async function deleteNote(note) {
  try {
    await notesApi.remove(note.id)
    notes.value = notes.value.filter(n => n.id !== note.id)
  } catch { /* ignore */ }
}

async function togglePin(note) {
  try {
    await notesApi.update(note.id, { pinned: !note.pinned })
    note.pinned = !note.pinned
    // Gepinnte nach oben sortieren
    notes.value.sort((a, b) => (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0))
  } catch { /* ignore */ }
}

async function searchProducts() {
  const q = productSearch.value.trim()
  if (q.length < 2) { productResults.value = []; return }
  try {
    const { data } = await searchApi.search({
      search: q, per_page: 5, language: 'de',
    })
    productResults.value = (data.data || []).map(p => ({ id: p.id, name: p.name, sku: p.sku }))
  } catch { productResults.value = [] }
}

function onProductSearchInput() {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(searchProducts, 300)
}

function selectProduct(p) {
  form.value.product_id = p.id
  linkedProduct.value = p
  productSearch.value = ''
  productResults.value = []
}

function unlinkProduct() {
  form.value.product_id = null
  linkedProduct.value = null
}

function timeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'gerade eben'
  if (mins < 60) return `vor ${mins}m`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `vor ${hrs}h`
  const days = Math.floor(hrs / 24)
  return `vor ${days}d`
}

onMounted(fetchNotes)
onUnmounted(() => { if (searchTimeout.value) clearTimeout(searchTimeout.value) })
</script>

<template>
  <DashboardWidgetWrapper title="Notizen" :icon="StickyNote">
    <div class="p-4">

      <!-- Neue Notiz Formular -->
      <div v-if="showForm" class="mb-4 p-3 rounded-lg border-2 border-dashed border-[var(--color-border-strong)] space-y-3">
        <input
          v-model="form.title"
          class="pim-input text-sm font-medium w-full"
          placeholder="Titel..."
          maxlength="255"
          @keydown.enter.prevent="saveNote"
          ref="titleInput"
        />
        <textarea
          v-model="form.body"
          class="pim-input text-xs w-full"
          rows="3"
          placeholder="Notiz schreiben..."
          maxlength="5000"
        ></textarea>

        <!-- Farbe -->
        <div class="flex items-center gap-1.5">
          <span class="text-[10px] text-[var(--color-text-tertiary)] uppercase tracking-wide mr-1">Farbe</span>
          <button
            v-for="c in NOTE_COLORS"
            :key="c.key"
            class="w-5 h-5 rounded-full border-2 transition-transform"
            :class="form.color === c.key ? 'scale-125 ring-2 ring-offset-1' : 'hover:scale-110'"
            :style="{ background: c.bg, borderColor: c.border, '--tw-ring-color': c.border }"
            @click="form.color = c.key"
          />
        </div>

        <!-- Sichtbarkeit -->
        <label class="flex items-center gap-2 text-xs cursor-pointer">
          <input type="checkbox" v-model="form.is_shared" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
          <Users class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          <span class="text-[var(--color-text-secondary)]">Für alle sichtbar</span>
        </label>

        <!-- Produkt-Verknüpfung -->
        <div>
          <div v-if="linkedProduct" class="flex items-center gap-2 text-xs bg-[var(--color-bg)] rounded px-2 py-1.5">
            <Package class="w-3.5 h-3.5 text-[var(--color-accent)] shrink-0" :stroke-width="2" />
            <span class="truncate flex-1">{{ linkedProduct.name }}</span>
            <span v-if="linkedProduct.sku" class="text-[var(--color-text-tertiary)] shrink-0">{{ linkedProduct.sku }}</span>
            <button @click="unlinkProduct" class="p-0.5 hover:text-red-500"><X class="w-3 h-3" /></button>
          </div>
          <div v-else class="relative">
            <div class="flex items-center gap-1.5">
              <Link2 class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
              <input
                v-model="productSearch"
                class="pim-input text-xs flex-1"
                placeholder="Produkt verknüpfen (Name/SKU)..."
                @input="onProductSearchInput"
              />
            </div>
            <div v-if="productResults.length" class="absolute z-10 left-0 right-0 mt-1 bg-[var(--color-bg-elevated,var(--color-bg))] border border-[var(--color-border)] rounded-md shadow-lg max-h-40 overflow-y-auto">
              <button
                v-for="p in productResults"
                :key="p.id"
                class="w-full text-left px-3 py-2 text-xs hover:bg-[var(--color-bg)] flex items-center gap-2"
                @click="selectProduct(p)"
              >
                <span class="truncate flex-1">{{ p.name }}</span>
                <span class="text-[var(--color-text-tertiary)] shrink-0">{{ p.sku }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Aktionen -->
        <div class="flex items-center gap-2">
          <button class="pim-btn pim-btn-primary text-xs" @click="saveNote">
            {{ editingNote ? 'Speichern' : 'Erstellen' }}
          </button>
          <button class="pim-btn pim-btn-ghost text-xs" @click="showForm = false">Abbrechen</button>
        </div>
      </div>

      <!-- Post-Its Grid -->
      <div v-if="notes.length === 0 && !loading && !showForm" class="text-center py-8">
        <StickyNote class="w-8 h-8 mx-auto mb-2 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        <p class="text-xs text-[var(--color-text-tertiary)]">Noch keine Notizen</p>
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2">
        <div
          v-for="note in notes"
          :key="note.id"
          class="group relative rounded-lg p-3 border cursor-pointer transition-shadow hover:shadow-md min-h-[80px] flex flex-col"
          :style="{
            background: getColor(note.color).bg,
            borderColor: getColor(note.color).border,
            color: getColor(note.color).text,
          }"
          @click="isOwnNote(note) ? openEditNote(note) : null"
        >
          <!-- Pin + Shared Indikatoren -->
          <Pin
            v-if="note.pinned"
            class="absolute -top-1 -right-1 w-3.5 h-3.5 rotate-45"
            :style="{ color: getColor(note.color).border }"
            :stroke-width="2.5"
          />
          <div v-if="note.is_shared" class="absolute top-1 left-1">
            <Users class="w-3 h-3 opacity-40" :stroke-width="2" />
          </div>

          <!-- Titel -->
          <p class="text-xs font-semibold leading-tight truncate pr-4">{{ note.title }}</p>

          <!-- Body Preview -->
          <p v-if="note.body" class="text-[10px] mt-1 leading-snug opacity-75 line-clamp-3 flex-1">
            {{ note.body }}
          </p>

          <!-- Footer -->
          <div class="flex items-center gap-1 mt-2 pt-1 border-t opacity-60" :style="{ borderColor: getColor(note.color).border }">
            <router-link
              v-if="note.product"
              :to="{ name: 'product-detail', params: { id: note.product.id } }"
              class="flex items-center gap-0.5 text-[9px] hover:underline truncate"
              @click.stop
            >
              <Package class="w-2.5 h-2.5 shrink-0" :stroke-width="2" />
              {{ note.product.sku || note.product.name }}
            </router-link>
            <span v-if="!isOwnNote(note) && note.creator" class="text-[9px] truncate">
              {{ note.creator.name }}
            </span>
            <span class="flex-1"></span>
            <span class="text-[9px] shrink-0">{{ timeAgo(note.updated_at) }}</span>
          </div>

          <!-- Hover-Aktionen (nur eigene Notizen) -->
          <div v-if="isOwnNote(note)" class="absolute top-1 right-1 hidden group-hover:flex items-center gap-0.5" @click.stop>
            <button
              class="p-0.5 rounded hover:bg-black/10 transition-colors"
              :title="note.pinned ? 'Loslösen' : 'Anheften'"
              @click="togglePin(note)"
            >
              <PinOff v-if="note.pinned" class="w-3 h-3" :stroke-width="2" />
              <Pin v-else class="w-3 h-3" :stroke-width="2" />
            </button>
            <button
              class="p-0.5 rounded hover:bg-black/10 transition-colors"
              title="Löschen"
              @click="deleteNote(note)"
            >
              <Trash2 class="w-3 h-3" :stroke-width="2" />
            </button>
          </div>
        </div>
      </div>

      <!-- Neue Notiz Button -->
      <button
        v-if="!showForm"
        class="mt-3 w-full py-2 rounded-lg border-2 border-dashed border-[var(--color-border)] text-xs text-[var(--color-text-tertiary)] hover:border-[var(--color-accent)] hover:text-[var(--color-accent)] transition-colors flex items-center justify-center gap-1.5"
        @click="openNewNote"
      >
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neue Notiz
      </button>
    </div>
  </DashboardWidgetWrapper>
</template>
