<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { ListChecks, MessageCirclePlus } from 'lucide-vue-next'
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

let listPollTimer = null

onMounted(async () => {
  await messengerStore.fetchConversations()
  listPollTimer = setInterval(() => messengerStore.fetchConversations(), CONVERSATION_LIST_POLL_INTERVAL)
})

onUnmounted(() => {
  if (listPollTimer) clearInterval(listPollTimer)
  messengerStore.closeConversation()
})

watch(() => messengerStore.messages.length, () => {
  nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }))
})

function formatPreview(conversation) {
  if (!conversation.last_message) return 'Noch keine Nachricht'
  const prefix = conversation.last_message.sender_id === authStore.user?.id ? 'Du: ' : ''
  return prefix + (conversation.last_message.body || 'Anhang')
}

function formatTime(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  const today = new Date()
  if (date.toDateString() === today.toDateString()) {
    return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
  }
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}

async function openConversation(id) {
  await messengerStore.openConversation(id)
  replyTo.value = null
}

async function handleSend(payload) {
  await messengerStore.sendMessage(payload)
}

function handleConversationsStarted(conversations) {
  const first = conversations?.[0]
  if (first?.conversation_id) openConversation(first.conversation_id)
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

      <div class="flex-1 overflow-y-auto">
        <button
          v-for="conversation in messengerStore.conversations"
          :key="conversation.id"
          class="flex items-center gap-2.5 w-full px-3 py-2.5 text-left border-b border-[var(--color-border)] hover:bg-[var(--color-bg)] transition-colors"
          :class="messengerStore.activeConversation?.id === conversation.id ? 'bg-[var(--color-bg)]' : ''"
          @click="openConversation(conversation.id)"
        >
          <span class="w-8 h-8 rounded-full bg-[var(--color-accent)]/15 text-[var(--color-accent)] flex items-center justify-center text-xs font-semibold shrink-0">
            {{ (conversation.other_user?.name || '?').charAt(0).toUpperCase() }}
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center justify-between gap-2">
              <span class="text-xs font-medium truncate">{{ conversation.other_user?.name || 'Unbekannt' }}</span>
              <span class="text-[10px] text-[var(--color-text-tertiary)] shrink-0">{{ formatTime(conversation.last_message_at) }}</span>
            </span>
            <span class="flex items-center justify-between gap-2">
              <span class="text-[11px] text-[var(--color-text-tertiary)] truncate">{{ formatPreview(conversation) }}</span>
              <span v-if="conversation.unread_count > 0" class="badge badge-sm badge-secondary shrink-0">{{ conversation.unread_count }}</span>
            </span>
          </span>
        </button>

        <p v-if="!messengerStore.conversations.length" class="px-3 py-6 text-center text-xs text-[var(--color-text-tertiary)]">
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
          <h3 class="text-sm font-semibold">{{ activeOtherUserName }}</h3>
          <button class="pim-btn pim-btn-ghost text-xs flex items-center gap-1.5" @click="taskDialogOpen = true">
            <ListChecks class="w-3.5 h-3.5" :stroke-width="1.75" /> Aufgabe anlegen
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <MessageBubble
            v-for="message in messengerStore.messages"
            :key="message.id"
            :message="message"
            :is-mine="message.sender?.id === authStore.user?.id"
            @reply="replyTo = $event"
          />
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
