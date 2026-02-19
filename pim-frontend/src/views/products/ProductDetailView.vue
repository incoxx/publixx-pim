<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProductStore } from '@/stores/products'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Save } from 'lucide-vue-next'
import PimCollectionGroup from '@/components/shared/PimCollectionGroup.vue'
import PimAttributeInput from '@/components/shared/PimAttributeInput.vue'
import PxfRenderer from '@/components/pxf/PxfRenderer.vue'

const route = useRoute()
const router = useRouter()
const store = useProductStore()
const { t } = useI18n()

const activeTab = ref('attributes')
const saving = ref(false)
const pxfData = ref(null)

const tabs = [
  { key: 'attributes', label: t('product.attributes') },
  { key: 'variants', label: t('product.variants') },
  { key: 'media', label: t('product.media') },
  { key: 'prices', label: t('product.prices') },
  { key: 'relations', label: t('product.relations') },
  { key: 'preview', label: t('product.preview') },
]

const product = computed(() => store.current)

async function save() {
  if (!product.value) return
  saving.value = true
  try {
    await store.update(product.value.id, product.value)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await store.fetchOne(route.params.id)
})
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="pim-btn pim-btn-ghost p-1.5" @click="router.push('/products')">
        <ArrowLeft class="w-4 h-4" :stroke-width="1.75" />
      </button>
      <div class="flex-1">
        <div v-if="store.loading" class="space-y-2">
          <div class="pim-skeleton h-5 w-48 rounded" />
          <div class="pim-skeleton h-3 w-32 rounded" />
        </div>
        <template v-else-if="product">
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">
            {{ product.name_de || product.name || product.sku }}
          </h2>
          <p class="text-xs text-[var(--color-text-tertiary)] font-mono">{{ product.sku }}</p>
        </template>
      </div>
      <button class="pim-btn pim-btn-primary" :disabled="saving" @click="save">
        <Save class="w-4 h-4" :stroke-width="1.75" />
        {{ saving ? 'Speichern…' : t('common.save') }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[var(--color-border)]">
      <nav class="flex gap-0 -mb-px">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="[
            'px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors',
            activeTab === tab.key
              ? 'border-[var(--color-accent)] text-[var(--color-accent)]'
              : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:border-[var(--color-border)]',
          ]"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Tab content -->
    <div v-if="store.loading" class="space-y-4">
      <div class="pim-card p-6">
        <div class="space-y-4">
          <div class="pim-skeleton h-4 w-1/3 rounded" />
          <div class="pim-skeleton h-8 w-full rounded" />
          <div class="pim-skeleton h-4 w-1/4 rounded" />
          <div class="pim-skeleton h-8 w-full rounded" />
        </div>
      </div>
    </div>

    <!-- Attributes tab -->
    <div v-else-if="activeTab === 'attributes' && product" class="space-y-3">
      <PimCollectionGroup title="Stammdaten" :filledCount="3" :totalCount="5">
        <div class="space-y-3 pt-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">SKU</label>
            <input class="pim-input font-mono" :value="product.sku" readonly />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Name (DE)</label>
            <input class="pim-input" v-model="product.name_de" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Status</label>
            <PimAttributeInput
              type="select"
              v-model="product.status"
              :options="[{ value: 'active', label: 'Aktiv' }, { value: 'draft', label: 'Entwurf' }, { value: 'inactive', label: 'Inaktiv' }]"
            />
          </div>
        </div>
      </PimCollectionGroup>

      <PimCollectionGroup title="Beschreibung" :filledCount="1" :totalCount="3" :defaultOpen="false">
        <div class="space-y-3 pt-3">
          <div>
            <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Kurzbeschreibung</label>
            <PimAttributeInput type="textarea" v-model="product.description_short" />
          </div>
        </div>
      </PimCollectionGroup>
    </div>

    <!-- Variants tab -->
    <div v-else-if="activeTab === 'variants'" class="pim-card p-6">
      <p class="text-sm text-[var(--color-text-tertiary)]">Varianten-Management wird hier angezeigt.</p>
    </div>

    <!-- Media tab -->
    <div v-else-if="activeTab === 'media'" class="pim-card p-6">
      <p class="text-sm text-[var(--color-text-tertiary)]">Medien werden hier angezeigt.</p>
    </div>

    <!-- Prices tab -->
    <div v-else-if="activeTab === 'prices'" class="pim-card p-6">
      <p class="text-sm text-[var(--color-text-tertiary)]">Preisverwaltung wird hier angezeigt.</p>
    </div>

    <!-- Relations tab -->
    <div v-else-if="activeTab === 'relations'" class="pim-card p-6">
      <p class="text-sm text-[var(--color-text-tertiary)]">Produktbeziehungen werden hier angezeigt.</p>
    </div>

    <!-- Preview tab (PXF) -->
    <div v-else-if="activeTab === 'preview'" class="pim-card p-6">
      <PxfRenderer v-if="pxfData" :pxf="pxfData" :zoom="0.6" />
      <div v-else class="text-center py-12">
        <p class="text-sm text-[var(--color-text-tertiary)]">Kein PXF-Template zugeordnet</p>
        <button class="pim-btn pim-btn-secondary mt-3 text-xs">Template zuordnen</button>
      </div>
    </div>
  </div>
</template>
