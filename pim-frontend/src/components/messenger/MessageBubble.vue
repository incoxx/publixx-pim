<script setup>
import { Check, CheckCheck, CornerUpLeft, Paperclip } from 'lucide-vue-next'
import { useRouter } from 'vue-router'

const props = defineProps({
  message: { type: Object, required: true },
  isMine: { type: Boolean, default: false },
})

defineEmits(['reply'])

const router = useRouter()

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
}

function openProduct(productId) {
  router.push({ name: 'product-detail', params: { id: productId } })
}
</script>

<template>
  <div class="chat" :class="isMine ? 'chat-end' : 'chat-start'">
    <div class="chat-header text-[11px] text-[var(--color-text-tertiary)] mb-0.5" v-if="!isMine">
      {{ message.sender?.name }}
    </div>

    <div class="chat-bubble" :class="isMine ? 'chat-bubble-primary' : ''">
      <!-- Zitat der Ursprungsnachricht bei "Antworten" -->
      <div
        v-if="message.reply_to"
        class="mb-1.5 px-2 py-1 rounded border-l-2 border-current/40 text-[11px] opacity-75 truncate"
      >
        {{ message.reply_to.body || 'Anhang' }}
      </div>

      <p v-if="message.body" class="whitespace-pre-wrap break-words text-sm">{{ message.body }}</p>

      <!-- Produkt-/Merkliste-Anhänge -->
      <div v-if="message.attachments?.length" class="mt-2 space-y-1">
        <div
          v-if="message.attachments.some(a => a.attached_via === 'merkliste')"
          class="text-[11px] font-medium opacity-75 flex items-center gap-1"
        >
          <Paperclip class="w-3 h-3" :stroke-width="2" /> Merkliste
        </div>
        <button
          v-for="attachment in message.attachments"
          :key="attachment.id"
          class="flex items-center gap-2 w-full px-2 py-1.5 rounded-lg text-left transition-colors"
          :class="attachment.product ? 'bg-black/5 hover:bg-black/10' : 'bg-black/5 opacity-60 cursor-not-allowed'"
          :disabled="!attachment.product"
          @click="attachment.product && openProduct(attachment.product.id)"
        >
          <span class="min-w-0">
            <span class="block text-xs font-medium truncate">{{ attachment.product?.name || 'Produkt nicht mehr verfügbar' }}</span>
            <span v-if="attachment.product" class="block text-[10px] font-mono opacity-70 truncate">{{ attachment.product.sku }}</span>
          </span>
        </button>
      </div>
    </div>

    <div class="chat-footer flex items-center gap-1.5 text-[10px] text-[var(--color-text-tertiary)] mt-0.5">
      <span>{{ formatTime(message.created_at) }}</span>
      <template v-if="isMine">
        <CheckCheck v-if="message.read_at" class="w-3 h-3" :stroke-width="2" title="Gelesen" />
        <Check v-else class="w-3 h-3" :stroke-width="2" title="Gesendet" />
      </template>
      <button class="hover:text-[var(--color-text-primary)] transition-colors" @click="$emit('reply', message)">
        <CornerUpLeft class="w-3 h-3" :stroke-width="2" />
      </button>
    </div>
  </div>
</template>
