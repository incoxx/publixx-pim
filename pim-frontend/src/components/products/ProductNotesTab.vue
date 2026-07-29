<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  StickyNote, Plus, Pin, PinOff, Trash2, Users, Lock,
  CheckCircle2, RotateCcw, MessageSquare, ChevronDown, ChevronUp,
  ClipboardList, Lightbulb, AlertTriangle, XCircle, Send,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import notesApi from '@/api/notes'

const { t } = useI18n()

const props = defineProps({
  productId: { type: String, required: true },
})

const emit = defineEmits(['counts-updated'])

const authStore = useAuthStore()
const notes = ref([])
const loading = ref(false)
const showForm = ref(false)
const showDone = ref(false)
const expandedComments = ref(new Set())
const commentDrafts = ref({})
const submittingComment = ref(null)
const resolvingId = ref(null)
const reopeningId = ref(null)
const deletingId = ref(null)

const NOTE_TYPES = [
  { key: 'task',    label: t('Aufgabe'),  icon: ClipboardList,   bg: '#dbeafe', border: '#60a5fa', text: '#1e3a5f' },
  { key: 'hint',    label: t('Hinweis'),  icon: Lightbulb,       bg: '#fef9c3', border: '#facc15', text: '#713f12' },
  { key: 'warning', label: t('Warnung'),  icon: AlertTriangle,   bg: '#ffedd5', border: '#fb923c', text: '#7c2d12' },
  { key: 'error',   label: t('Fehler'),   icon: XCircle,         bg: '#fee2e2', border: '#f87171', text: '#7f1d1d' },
]

const form = ref({ title: '', body: '', type: 'task', is_shared: false })

function getType(key) {
  return NOTE_TYPES.find(t => t.key === key) || NOTE_TYPES[0]
}

const isOwnNote = (note) => note.created_by === authStore.user?.id

const openNotes = computed(() => notes.value.filter(n => n.status === 'open'))
const doneNotes = computed(() => notes.value.filter(n => n.status === 'done'))

async function fetchNotes() {
  loading.value = true
  try {
    const { data } = await notesApi.forProduct(props.productId, showDone.value)
    notes.value = data.data || data
    emit('counts-updated', data.open_counts || {})
  } catch { /* ignore */ }
  loading.value = false
}

function openNewNote() {
  form.value = { title: '', body: '', type: 'task', is_shared: false }
  showForm.value = true
}

async function saveNote() {
  if (!form.value.title.trim()) return
  try {
    await notesApi.create({ ...form.value, product_id: props.productId })
    showForm.value = false
    await fetchNotes()
  } catch { /* ignore */ }
}

async function togglePin(note) {
  if (!isOwnNote(note)) return
  try {
    await notesApi.update(note.id, { pinned: !note.pinned })
    note.pinned = !note.pinned
  } catch { /* ignore */ }
}

async function resolveNote(note) {
  resolvingId.value = note.id
  try {
    await notesApi.resolve(note.id)
    await fetchNotes()
  } catch { /* ignore */ }
  resolvingId.value = null
}

async function reopenNote(note) {
  reopeningId.value = note.id
  try {
    await notesApi.reopen(note.id)
    await fetchNotes()
  } catch { /* ignore */ }
  reopeningId.value = null
}

async function deleteNote(note) {
  if (!confirm('Notiz unwiderruflich löschen?')) return
  deletingId.value = note.id
  try {
    await notesApi.remove(note.id)
    await fetchNotes()
  } catch { /* ignore */ }
  deletingId.value = null
}

function toggleComments(noteId) {
  const next = new Set(expandedComments.value)
  if (next.has(noteId)) {
    next.delete(noteId)
  } else {
    next.add(noteId)
  }
  expandedComments.value = next
}

async function submitComment(note) {
  const body = (commentDrafts.value[note.id] || '').trim()
  if (!body) return
  submittingComment.value = note.id
  try {
    const { data } = await notesApi.addComment(note.id, body)
    if (!note.comments) note.comments = []
    note.comments.push(data.data)
    commentDrafts.value[note.id] = ''
  } catch { /* ignore */ }
  submittingComment.value = null
}

async function toggleShowDone() {
  showDone.value = !showDone.value
  await fetchNotes()
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
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
        <span class="text-xs text-[var(--color-text-tertiary)]">({{ openNotes.length }} offen)</span>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="pim-btn pim-btn-ghost text-xs"
          :class="showDone ? 'text-[var(--color-accent)]' : ''"
          @click="toggleShowDone"
        >
          {{ showDone ? 'Erledigte ausblenden' : 'Erledigte anzeigen' }}
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="openNewNote">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" /> Notiz erstellen
        </button>
      </div>
    </div>

    <!-- Neues Notiz-Formular -->
    <div v-if="showForm" class="mb-4 p-4 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] space-y-3">
      <!-- Typ-Auswahl -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="t in NOTE_TYPES"
          :key="t.key"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium border transition-all"
          :class="form.type === t.key ? 'ring-2 ring-offset-1' : 'opacity-50 hover:opacity-80'"
          :style="form.type === t.key
            ? { background: t.bg, borderColor: t.border, color: t.text, '--tw-ring-color': t.border }
            : { background: 'var(--color-surface)', borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }"
          @click="form.type = t.key"
        >
          <component :is="t.icon" class="w-3.5 h-3.5" :stroke-width="2" />
          {{ t.label }}
        </button>
      </div>

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
        rows="3"
        placeholder="Details (optional)..."
        maxlength="5000"
      ></textarea>
      <div class="flex items-center gap-4">
        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
          <input type="checkbox" v-model="form.is_shared" class="rounded border-[var(--color-border-strong)] text-[var(--color-accent)]" />
          <Users class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          Für alle sichtbar
        </label>
      </div>
      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-primary text-xs" @click="saveNote">Erstellen</button>
        <button class="pim-btn pim-btn-ghost text-xs" @click="showForm = false">Abbrechen</button>
      </div>
    </div>

    <!-- Laden -->
    <div v-if="loading" class="text-center py-8 text-xs text-[var(--color-text-tertiary)]">Laden...</div>

    <!-- Leer -->
    <div v-else-if="openNotes.length === 0 && !showForm && !showDone" class="text-center py-12">
      <StickyNote class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.25" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine offenen Notizen</p>
    </div>

    <!-- Offene Notizen -->
    <div v-else class="space-y-3">
      <div
        v-for="note in openNotes"
        :key="note.id"
        class="rounded-lg border overflow-hidden"
        :style="{ borderColor: getType(note.type).border }"
      >
        <!-- Notiz-Body -->
        <div
          class="flex gap-3 p-3"
          :style="{ background: getType(note.type).bg, color: getType(note.type).text }"
        >
          <component :is="getType(note.type).icon" class="w-4 h-4 shrink-0 mt-0.5 opacity-70" :stroke-width="2" />

          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                  <Pin v-if="note.pinned" class="w-3 h-3 shrink-0 opacity-60" :stroke-width="2.5" />
                  <h4 class="text-sm font-semibold">{{ note.title }}</h4>
                </div>
                <p v-if="note.body" class="text-xs mt-1 whitespace-pre-line opacity-80 leading-relaxed">{{ note.body }}</p>
              </div>

              <div class="flex items-center gap-1 shrink-0">
                <button
                  v-if="isOwnNote(note)"
                  class="p-1 rounded hover:bg-black/10 opacity-60 hover:opacity-100"
                  :title="note.pinned ? 'Loslösen' : 'Anheften'"
                  @click="togglePin(note)"
                >
                  <PinOff v-if="note.pinned" class="w-3.5 h-3.5" :stroke-width="2" />
                  <Pin v-else class="w-3.5 h-3.5" :stroke-width="2" />
                </button>
                <button
                  class="p-1 rounded hover:bg-black/10 opacity-60 hover:opacity-100"
                  title="Als erledigt markieren"
                  :disabled="resolvingId === note.id"
                  @click="resolveNote(note)"
                >
                  <CheckCircle2 class="w-3.5 h-3.5" :stroke-width="2" />
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

        <!-- Kommentar-Bereich -->
        <div class="bg-[var(--color-surface)] border-t" :style="{ borderColor: getType(note.type).border + '40' }">
          <button
            class="w-full flex items-center gap-1.5 px-3 py-1.5 text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-text-secondary)] transition-colors"
            @click="toggleComments(note.id)"
          >
            <MessageSquare class="w-3 h-3" :stroke-width="2" />
            <span>{{ note.comments?.length ? `${note.comments.length} Kommentar${note.comments.length !== 1 ? 'e' : ''}` : 'Kommentieren' }}</span>
            <ChevronDown v-if="!expandedComments.has(note.id)" class="w-3 h-3 ml-auto" :stroke-width="2" />
            <ChevronUp v-else class="w-3 h-3 ml-auto" :stroke-width="2" />
          </button>

          <div v-if="expandedComments.has(note.id)" class="px-3 pb-3 space-y-2">
            <div v-for="comment in note.comments" :key="comment.id" class="flex gap-2">
              <div class="w-5 h-5 rounded-full bg-[var(--color-accent)] text-white text-[9px] flex items-center justify-center shrink-0 mt-0.5 font-semibold">
                {{ (comment.user?.name || '?').charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1">
                <div class="flex items-baseline gap-1.5">
                  <span class="text-[11px] font-medium text-[var(--color-text-primary)]">{{ comment.user?.name }}</span>
                  <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ timeAgo(comment.created_at) }}</span>
                </div>
                <p class="text-xs text-[var(--color-text-secondary)] mt-0.5 leading-relaxed">{{ comment.body }}</p>
              </div>
            </div>

            <div class="flex gap-2 mt-2">
              <div class="w-5 h-5 rounded-full bg-[var(--color-accent)] text-white text-[9px] flex items-center justify-center shrink-0 mt-0.5 font-semibold">
                {{ (authStore.user?.name || '?').charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 flex gap-1.5">
                <input
                  v-model="commentDrafts[note.id]"
                  class="pim-input text-xs flex-1 py-1"
                  placeholder="Kommentar schreiben..."
                  maxlength="2000"
                  @keydown.enter.prevent="submitComment(note)"
                />
                <button
                  class="pim-btn pim-btn-primary p-1.5"
                  :disabled="!commentDrafts[note.id]?.trim() || submittingComment === note.id"
                  @click="submitComment(note)"
                >
                  <Send class="w-3.5 h-3.5" :stroke-width="2" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Erledigte Notizen -->
      <template v-if="showDone && doneNotes.length">
        <div class="flex items-center gap-2 mt-4 mb-2">
          <div class="h-px flex-1 bg-[var(--color-border)]" />
          <span class="text-[11px] text-[var(--color-text-tertiary)] font-medium">Erledigt ({{ doneNotes.length }})</span>
          <div class="h-px flex-1 bg-[var(--color-border)]" />
        </div>

        <div
          v-for="note in doneNotes"
          :key="note.id"
          class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] opacity-60 hover:opacity-80 transition-opacity overflow-hidden"
        >
          <div class="flex gap-3 p-3">
            <component :is="getType(note.type).icon" class="w-4 h-4 shrink-0 mt-0.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <h4 class="text-sm font-medium text-[var(--color-text-secondary)] line-through">{{ note.title }}</h4>
                <div class="flex items-center gap-1 shrink-0">
                  <button
                    class="p-1 rounded hover:bg-[var(--color-border)] text-[var(--color-text-tertiary)]"
                    title="Reaktivieren"
                    :disabled="reopeningId === note.id"
                    @click="reopenNote(note)"
                  >
                    <RotateCcw class="w-3.5 h-3.5" :stroke-width="2" />
                  </button>
                  <button
                    v-if="isOwnNote(note)"
                    class="p-1 rounded hover:bg-red-50 text-[var(--color-text-tertiary)] hover:text-red-500"
                    title="Endgültig löschen"
                    :disabled="deletingId === note.id"
                    @click="deleteNote(note)"
                  >
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                  </button>
                </div>
              </div>
              <div class="flex items-center gap-1.5 mt-1 text-[10px] text-[var(--color-text-tertiary)]">
                <CheckCircle2 class="w-3 h-3 text-green-500" :stroke-width="2" />
                <span>Erledigt von {{ note.resolver?.name || '?' }} · {{ timeAgo(note.resolved_at) }}</span>
              </div>
            </div>
          </div>

          <div v-if="note.comments?.length" class="border-t border-[var(--color-border)]">
            <button
              class="w-full flex items-center gap-1.5 px-3 py-1.5 text-[11px] text-[var(--color-text-tertiary)]"
              @click="toggleComments(note.id)"
            >
              <MessageSquare class="w-3 h-3" :stroke-width="2" />
              <span>{{ note.comments.length }} Kommentar{{ note.comments.length !== 1 ? 'e' : '' }}</span>
              <ChevronDown v-if="!expandedComments.has(note.id)" class="w-3 h-3 ml-auto" :stroke-width="2" />
              <ChevronUp v-else class="w-3 h-3 ml-auto" :stroke-width="2" />
            </button>
            <div v-if="expandedComments.has(note.id)" class="px-3 pb-3 space-y-2">
              <div v-for="comment in note.comments" :key="comment.id" class="flex gap-2">
                <div class="w-5 h-5 rounded-full bg-[var(--color-accent)] text-white text-[9px] flex items-center justify-center shrink-0 font-semibold">
                  {{ (comment.user?.name || '?').charAt(0).toUpperCase() }}
                </div>
                <div>
                  <div class="flex items-baseline gap-1.5">
                    <span class="text-[11px] font-medium text-[var(--color-text-primary)]">{{ comment.user?.name }}</span>
                    <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ timeAgo(comment.created_at) }}</span>
                  </div>
                  <p class="text-xs text-[var(--color-text-secondary)] mt-0.5">{{ comment.body }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
