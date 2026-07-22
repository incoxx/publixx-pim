<script setup>
import { ref } from 'vue'
import { ListChecks, Package, Send, Star, X } from 'lucide-vue-next'
import EntityPickerDialog from '@/components/shared/EntityPickerDialog.vue'
import productsApi from '@/api/products'
import watchlistApi from '@/api/watchlist'

const props = defineProps({
  replyTo: { type: Object, default: null },
})

const emit = defineEmits(['send', 'cancel-reply', 'task'])

const body = ref('')
const pendingAttachments = ref([]) // [{type:'product', product_id, label, sublabel} | {type:'merkliste', count}]
const productPickerOpen = ref(false)
const sending = ref(false)

function productFetcher(query, page) {
  return productsApi.list({ search: query || undefined, page, perPage: 20 }).then(({ data }) => ({
    items: data.data,
    meta: data.meta,
  }))
}

function onProductsPicked(picked) {
  for (const product of picked) {
    if (pendingAttachments.value.some(a => a.type === 'product' && a.product_id === product.id)) continue
    pendingAttachments.value.push({
      type: 'product',
      product_id: product.id,
      label: product.name,
      sublabel: product.sku,
    })
  }
}

async function attachMerkliste() {
  if (pendingAttachments.value.some(a => a.type === 'merkliste')) return
  const { data } = await watchlistApi.productIds()
  const count = (data.data || data).length
  if (!count) return
  pendingAttachments.value.push({ type: 'merkliste', count })
}

function removeAttachment(index) {
  pendingAttachments.value.splice(index, 1)
}

async function send() {
  if (!body.value.trim() && !pendingAttachments.value.length) return
  sending.value = true
  try {
    const attachments = pendingAttachments.value.map(a =>
      a.type === 'product' ? { type: 'product', product_id: a.product_id } : { type: 'merkliste' }
    )
    await emit('send', {
      body: body.value.trim() || null,
      replyToMessageId: props.replyTo?.id || null,
      attachments,
    })
    body.value = ''
    pendingAttachments.value = []
    emit('cancel-reply')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="border-t border-[var(--color-border)] p-3">
    <!-- Antwort-Vorschau -->
    <div v-if="replyTo" class="flex items-center justify-between px-2 py-1.5 mb-2 rounded bg-[var(--color-bg)] text-xs">
      <span class="truncate">Antwort auf: {{ replyTo.body || 'Anhang' }}</span>
      <button class="p-0.5 hover:text-[var(--color-error)]" @click="$emit('cancel-reply')">
        <X class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>

    <!-- Ausstehende Anhänge -->
    <div v-if="pendingAttachments.length" class="flex flex-wrap gap-1.5 mb-2">
      <span
        v-for="(a, index) in pendingAttachments"
        :key="index"
        class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-[var(--color-bg)] text-[11px]"
      >
        <template v-if="a.type === 'product'">{{ a.label }}</template>
        <template v-else>Merkliste ({{ a.count }} Produkte)</template>
        <button class="hover:text-[var(--color-error)]" @click="removeAttachment(index)">
          <X class="w-3 h-3" :stroke-width="2.5" />
        </button>
      </span>
    </div>

    <div class="flex items-end gap-2">
      <textarea
        v-model="body"
        rows="1"
        class="pim-input text-sm flex-1 resize-none"
        placeholder="Nachricht schreiben…"
        @keydown.enter.exact.prevent="send"
      />
      <button class="pim-btn pim-btn-ghost p-2" title="Produkt anhängen" @click="productPickerOpen = true">
        <Package class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <button class="pim-btn pim-btn-ghost p-2" title="Merkliste anhängen" @click="attachMerkliste">
        <Star class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <button class="pim-btn pim-btn-ghost p-2" title="Aufgabe anlegen" @click="$emit('task')">
        <ListChecks class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <button class="pim-btn pim-btn-primary p-2" :disabled="sending" title="Senden" @click="send">
        <Send class="w-4 h-4" :stroke-width="1.75" />
      </button>
    </div>

    <EntityPickerDialog
      v-model="productPickerOpen"
      title="Produkt anhängen"
      multiple
      :fetcher="productFetcher"
      :labelFn="p => p.name"
      :sublabelFn="p => p.sku"
      @confirm="onProductsPicked"
    />
  </div>
</template>
