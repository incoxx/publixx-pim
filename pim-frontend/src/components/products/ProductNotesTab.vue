<script setup>
import { ref, onMounted } from 'vue'
import { StickyNote, Plus, Pin, PinOff, Trash2, Edit3, Users, Lock, X } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import notesApi from '@/api/notes'

const props = defineProps({
  productId: { type: String, required: true },
})

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

const form = ref({ title: '', body: '', color: 'yellow', is_shared: false })

function getColor(key) {
  return NOTE_COLORS.find(c => c.key === key) || NOTE_COLORS[0]
}

const isOwnNote = (note) => note.created_by === authStore.user?.id

async function fetchNotes() {
  loading.value = true
  try {
    const { data } = await notesApi.forProduct(props.productId)
    notes.value = data.data || data
  } catch { /* ignore */ }
  loading.value = false
}

function openNewNote() {
  editingNote.value = null
  form.value = { title: '', body: '', color: 'yellow', is_shared: false }
  showForm.value = true
}

function openEditNote(note) {
  if (!isOwnNote(note)) return
  editingNote.value = note
  form.value = { title: note.title, body: note.body || '', color: note.color, is_shared: note.is_shared }
  showForm.value = true
}

async function saveNote() {
  if (!form.value.title.trim()) return
  try {
    if (editingNote.value) {
      await notesApi.update(editingNote.value.id, { ...form.value, product_id: props.productId })
    } else {
      await notesApi.create({ ...form.value, product_id: props.productId })
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
  } catch { /* ignore */ }
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
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <StickyNote class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Notizen</h3>
        <span class="text-xs text-[var(--color-text-tertiary)]">({{ notes.length }})</span>
      </div>
      <button class="pim-btn pim-btn-primary text-xs" @click="openNewNote">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Notiz erstellen
      </button>
    </div>

    <!-- Formular -->
    <div v-if="showForm" class="mb-4 p-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] space-y-3">
      <input
        v-model="form.title"
        class="pim-input text-sm font-medium w-full"
        placeholder="Titel..."
        maxlength="255"
        @keydown.enter.prevent="saveNote"
      />
      <textarea
        v-model="form.body"
        class="pim-input text-xs w-full"
        rows="4"
        placeholder="Notiz schreiben..."
        maxlength="5000"
      ></textarea>
      <div class="flex items-center gap-4">
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
        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
          <input type="checkbox" v-model="form.is_shared" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
          <Users class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          Für alle sichtbar
        </label>
      </div>
      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-primary text-xs" @click="saveNote">{{ editingNote ? 'Speichern' : 'Erstellen' }}</button>
        <button class="pim-btn pim-btn-ghost text-xs" @click="showForm = false">Abbrechen</button>
      </div>
    </div>

    <!-- Notizen-Liste -->
    <div v-if="loading" class="text-center py-8 text-xs text-[var(--color-text-tertiary)]">Laden...</div>

    <div v-else-if="notes.length === 0 && !showForm" class="text-center py-12">
      <StickyNote class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Notizen für dieses Produkt</p>
      <p class="text-xs text-[var(--color-text-tertiary)] mt-1">Erstelle eine Notiz um Informationen festzuhalten</p>
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="note in notes"
        :key="note.id"
        class="group flex gap-3 p-3 rounded-lg border transition-colors"
        :style="{
          background: getColor(note.color).bg,
          borderColor: getColor(note.color).border,
          color: getColor(note.color).text,
        }"
      >
        <!-- Farb-Streifen -->
        <div class="w-1 shrink-0 rounded-full" :style="{ background: getColor(note.color).border }"></div>

        <!-- Inhalt -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start gap-2">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <Pin v-if="note.pinned" class="w-3 h-3 shrink-0 opacity-60" :stroke-width="2.5" />
                <Users v-if="note.is_shared" class="w-3 h-3 shrink-0 opacity-40" :stroke-width="2" />
                <h4 class="text-sm font-semibold truncate">{{ note.title }}</h4>
              </div>
              <p v-if="note.body" class="text-xs mt-1 whitespace-pre-line opacity-80 leading-relaxed">{{ note.body }}</p>
            </div>

            <!-- Aktionen (nur eigene) -->
            <div v-if="isOwnNote(note)" class="hidden group-hover:flex items-center gap-1 shrink-0">
              <button class="p-1 rounded hover:bg-black/10" :title="note.pinned ? 'Loslösen' : 'Anheften'" @click="togglePin(note)">
                <PinOff v-if="note.pinned" class="w-3.5 h-3.5" :stroke-width="2" />
                <Pin v-else class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <button class="p-1 rounded hover:bg-black/10" title="Bearbeiten" @click="openEditNote(note)">
                <Edit3 class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
              <button class="p-1 rounded hover:bg-black/10" title="Löschen" @click="deleteNote(note)">
                <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
              </button>
            </div>
          </div>

          <!-- Meta -->
          <div class="flex items-center gap-2 mt-2 text-[10px] opacity-50">
            <span>{{ note.creator?.name || 'Du' }}</span>
            <span>&middot;</span>
            <span>{{ timeAgo(note.updated_at) }}</span>
            <span v-if="note.is_shared" class="ml-auto flex items-center gap-0.5">
              <Users class="w-2.5 h-2.5" :stroke-width="2" /> Geteilt
            </span>
            <span v-else class="ml-auto flex items-center gap-0.5">
              <Lock class="w-2.5 h-2.5" :stroke-width="2" /> Privat
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
