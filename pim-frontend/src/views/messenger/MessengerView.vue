<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { CheckCircle2, ListChecks, MessageCirclePlus, RotateCcw, Search, Trash2, X } from 'lucide-vue-next'
import { useMessengerStore } from '@/stores/messenger'
import { useAuthStore } from '@/stores/auth'
import MessageBubble from '@/components/messenger/MessageBubble.vue'
import MessageComposer from '@/components/messenger/MessageComposer.vue'
import NewConversationDialog from '@/components/messenger/NewConversationDialog.vue'
import MessengerTaskDialog from '@/components/messenger/MessengerTaskDialog.vue'

const CONVERSATION_LIST_POLL_INTERVAL = 20000

const messengerStore = useMessengerStore()
const authStore = useAuthStore()

const newConversationOpen = ref(false)
const taskDialogOpen = ref(false)
const replyTo = ref(null)
const messagesEnd = ref(null)

// Übersicht: serverseitige Suche (Name + Inhalt) + clientseitiger Datumsfilter.
const searchTerm = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const showFilters = ref(false)
// Suche innerhalb des offenen Chats (clientseitig über die geladenen Nachrichten).
const messageSearch = ref('')

let listPollTimer = null
let searchDebounce = null

onMounted(async () => {
  await messengerStore.fetchConversations()
  listPollTimer = setInterval(() => messengerStore.fetchConversations(), CONVERSATION_LIST_POLL_INTERVAL)
})

onUnmounted(() => {
  if (listPollTimer) clearInterval(listPollTimer)
  if (searchDebounce) clearTimeout(searchDebounce)
  messengerStore.closeConversation()
})

watch(() => messengerStore.messages.length, () => {
  nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }))
})

// Suche entprellt an den Store weiterreichen (Serveranfrage nur nach kurzer Pause).
watch(searchTerm, (term) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => messengerStore.setSearch(term.trim()), 300)
})

// Datumsfilter clientseitig auf last_message_at anwenden -- die Liste ist komplett
// geladen, daher kein zusätzlicher Server-Roundtrip nötig.
const visibleConversations = computed(() => {
  const from = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00') : null
  const to = dateTo.value ? new Date(dateTo.value + 'T23:59:59') : null
  if (!from && !to) return messengerStore.conversations
  return messengerStore.conversations.filter((c) => {
    if (!c.last_message_at) return false
    const d = new Date(c.last_message_at)
    if (from && d < from) return false
    if (to && d > to) return false
    return true
  })
})

const hasActiveFilter = computed(() => !!(searchTerm.value || dateFrom.value || dateTo.value))

function clearFilters() {
  searchTerm.value = ''
  dateFrom.value = ''
  dateTo.value = ''
}

// Innerhalb des offenen Chats über die geladenen Nachrichten filtern.
const visibleMessages = computed(() => {
  const term = messageSearch.value.trim().toLowerCase()
  if (!term) return messengerStore.messages
  return messengerStore.messages.filter((m) => (m.body || '').toLowerCase().includes(term))
})

function formatPreview(conversation) {
  if (!conversation.last_message) return 'Noch keine Nachricht'
  const prefix = conversation.last_message.sender_id === authStore.user?.id ? 'Du: ' : ''
  return prefix + (conversation.last_message.body || 'Anhang')
}

// Datum UND Uhrzeit des letzten Updates -- heute/gestern relativ, sonst mit Datum.
function formatTime(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  const time = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  if (date.toDateString() === today.toDateString()) return time
  if (date.toDateString() === yesterday.toDateString()) return `Gestern ${time}`
  return `${date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })} ${time}`
}

async function openConversation(id) {
  messageSearch.value = ''
  await messengerStore.openConversation(id)
  replyTo.value = null
}

async function handleDeleteFromList(conversation) {
  if (!window.confirm(`Chat mit ${conversation.other_user?.name || 'Unbekannt'} wirklich löschen? Das kann nicht rückgängig gemacht werden.`)) return
  await messengerStore.deleteConversation(conversation.id)
}

async function handleSend(payload) {
  await messengerStore.sendMessage(payload)
}

function handleConversationsStarted(conversations) {
  const first = conversations?.[0]
  if (first?.conversation_id) openConversation(first.conversation_id)
}

async function handleDelete() {
  const conversation = messengerStore.activeConversation
  if (!conversation) return
  if (!window.confirm(`Chat mit ${activeOtherUserName.value} wirklich löschen? Das kann nicht rückgängig gemacht werden.`)) return
  await messengerStore.deleteConversation(conversation.id)
}

async function handleResolve() {
  if (messengerStore.activeConversation) await messengerStore.resolveConversation(messengerStore.activeConversation.id)
}

async function handleReopen() {
  if (messengerStore.activeConversation) await messengerStore.reopenConversation(messengerStore.activeConversation.id)
}

const activeOtherUserName = computed(() => messengerStore.activeConversation?.other_user?.name || '')
</script>

<template>
  <div class="flex h-[calc(100vh-8.5rem)] rounded-xl border border-[var(--color-border)] overflow-hidden bg-[var(--color-surface)]">
    <!-- Konversationsliste -->
    <div class="w-72 shrink-0 border-r border-[var(--color-border)] flex flex-col">
      <div class="flex items-center justify-between px-3 py-2.5 border-b border-[var(--color-border)]">
        <h2 class="text-sm font-semibold">Nachrichten</h2>
        <button class="pim-btn pim-btn-ghost p-1.5" title="Neue Nachricht" @click="newConversationOpen = true">
          <MessageCirclePlus class="w-4 h-4" :stroke-width="1.75" />
        </button>
      </div>

      <!-- Suche (Empfänger + Inhalt) + Datumsfilter -->
      <div class="px-3 py-2 border-b border-[var(--color-border)] space-y-2">
        <div class="flex items-center gap-1.5">
          <div class="relative flex-1">
            <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <input
              v-model="searchTerm"
              type="text"
              class="pim-input pim-input-icon text-xs w-full"
              placeholder="Name oder Inhalt suchen…"
            />
          </div>
          <button
            class="pim-btn pim-btn-ghost p-1.5"
            :class="showFilters || dateFrom || dateTo ? 'text-[var(--color-accent)]' : ''"
            title="Nach Datum filtern"
            @click="showFilters = !showFilters"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 8h12M10 12h4M12 16h0"/></svg>
          </button>
        </div>

        <div v-if="showFilters" class="flex items-center gap-1.5">
          <input v-model="dateFrom" type="date" class="pim-input text-[11px] flex-1" title="Von" />
          <span class="text-[11px] text-[var(--color-text-tertiary)]">–</span>
          <input v-model="dateTo" type="date" class="pim-input text-[11px] flex-1" title="Bis" />
        </div>

        <button
          v-if="hasActiveFilter"
          class="flex items-center gap-1 text-[11px] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
          @click="clearFilters"
        >
          <X class="w-3 h-3" :stroke-width="2" /> Filter zurücksetzen
        </button>
      </div>

      <div class="flex-1 overflow-y-auto">
        <div
          v-for="conversation in visibleConversations"
          :key="conversation.id"
          class="group flex items-center gap-1 border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
          :class="messengerStore.activeConversation?.id === conversation.id ? 'bg-[var(--color-bg)]' : ''"
        >
          <button
            class="flex items-center gap-2.5 flex-1 min-w-0 px-3 py-2.5 text-left"
            @click="openConversation(conversation.id)"
          >
            <span class="w-8 h-8 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)] flex items-center justify-center text-xs font-semibold shrink-0">
              {{ (conversation.other_user?.name || '?').charAt(0).toUpperCase() }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center justify-between gap-2">
                <span class="flex items-center gap-1 min-w-0">
                  <span class="text-xs font-medium truncate">{{ conversation.other_user?.name || 'Unbekannt' }}</span>
                  <CheckCircle2 v-if="conversation.status === 'done'" class="w-3 h-3 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="2" title="Erledigt" />
                </span>
                <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0">{{ formatTime(conversation.last_message_at) }}</span>
              </span>
              <span class="flex items-center justify-between gap-2">
                <span class="text-[11px] text-[var(--color-text-tertiary)] truncate">{{ formatPreview(conversation) }}</span>
                <span v-if="conversation.unread_count > 0" class="badge badge-sm badge-secondary shrink-0">{{ conversation.unread_count }}</span>
              </span>
            </span>
          </button>
          <button
            v-if="conversation.is_own"
            class="p-2 mr-1 shrink-0 text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors"
            title="Chat löschen"
            @click.stop="handleDeleteFromList(conversation)"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
          </button>
        </div>

        <p v-if="!visibleConversations.length && hasActiveFilter" class="px-3 py-6 text-center text-xs text-[var(--color-text-tertiary)]">
          Keine Treffer für die aktuellen Filter.
        </p>
        <p v-else-if="!messengerStore.conversations.length" class="px-3 py-6 text-center text-xs text-[var(--color-text-tertiary)]">
          Noch keine Nachrichten. Starte eine neue Konversation.
        </p>

        <p v-if="messengerStore.error" class="px-3 py-2 text-xs text-[var(--color-error)]">
          {{ messengerStore.error }}
        </p>
      </div>
    </div>

    <!-- Aktive Konversation -->
    <div class="flex-1 flex flex-col min-w-0">
      <template v-if="messengerStore.activeConversation">
        <div class="flex items-center justify-between px-4 py-2.5 border-b border-[var(--color-border)]">
          <div class="flex items-center gap-2 min-w-0">
            <h3 class="text-sm font-semibold truncate">{{ activeOtherUserName }}</h3>
            <span v-if="messengerStore.activeConversation.status === 'done'" class="badge badge-sm badge-ghost shrink-0">Erledigt</span>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <button class="pim-btn pim-btn-ghost text-xs flex items-center gap-1.5" @click="taskDialogOpen = true">
              <ListChecks class="w-3.5 h-3.5" :stroke-width="1.75" /> Aufgabe anlegen
            </button>
            <button
              v-if="messengerStore.activeConversation.status === 'done'"
              class="pim-btn pim-btn-ghost text-xs flex items-center gap-1.5"
              title="Wieder öffnen"
              @click="handleReopen"
            >
              <RotateCcw class="w-3.5 h-3.5" :stroke-width="1.75" /> Wieder öffnen
            </button>
            <button
              v-else
              class="pim-btn pim-btn-ghost text-xs flex items-center gap-1.5"
              title="Als erledigt markieren"
              @click="handleResolve"
            >
              <CheckCircle2 class="w-3.5 h-3.5" :stroke-width="1.75" /> Erledigt
            </button>
            <button
              v-if="messengerStore.activeConversation.is_own"
              class="pim-btn pim-btn-ghost text-xs p-2 hover:text-[var(--color-error)]"
              title="Chat löschen"
              @click="handleDelete"
            >
              <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
            </button>
          </div>
        </div>

        <!-- Suche innerhalb dieses Chats -->
        <div class="px-4 py-2 border-b border-[var(--color-border)]">
          <div class="relative">
            <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
            <input
              v-model="messageSearch"
              type="text"
              class="pim-input pim-input-icon text-xs w-full"
              placeholder="In diesem Chat suchen…"
            />
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <MessageBubble
            v-for="message in visibleMessages"
            :key="message.id"
            :message="message"
            :is-mine="message.sender?.id === authStore.user?.id"
            @reply="replyTo = $event"
          />
          <p v-if="messageSearch && !visibleMessages.length" class="text-center text-xs text-[var(--color-text-tertiary)] py-4">
            Keine Nachricht in diesem Chat gefunden.
          </p>
          <div ref="messagesEnd" />
        </div>

        <MessageComposer :reply-to="replyTo" :on-send="handleSend" @cancel-reply="replyTo = null" @task="taskDialogOpen = true" />

        <MessengerTaskDialog
          v-model="taskDialogOpen"
          :conversation-id="messengerStore.activeConversation.id"
          :assigned-to="messengerStore.activeConversation.other_user?.id"
          :assigned-to-name="activeOtherUserName"
        />
      </template>

      <div v-else class="flex-1 flex flex-col items-center justify-center gap-2 text-[var(--color-text-tertiary)]">
        <p class="text-sm">Wähle eine Konversation oder starte eine neue.</p>
        <button class="pim-btn pim-btn-primary text-xs" @click="newConversationOpen = true">Neue Nachricht</button>
      </div>
    </div>

    <NewConversationDialog v-model="newConversationOpen" @started="handleConversationsStarted" />
  </div>
</template>
