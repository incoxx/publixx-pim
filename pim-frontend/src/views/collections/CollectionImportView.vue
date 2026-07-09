<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { collections as collectionsApi } from '@/api/collections'
import { useCollectionsStore } from '@/stores/collections'
import { UploadCloud, ArrowLeft } from 'lucide-vue-next'
import CollectionMatchQueue from '@/components/collections/CollectionMatchQueue.vue'

const router = useRouter()
const store = useCollectionsStore()

const form = ref({
  collection_type_id: '',
  adapter: 'json',
  organization_name: '',
  organization_external_ref: '',
  currency: '',
})
const file = ref(null)
const payload = ref('')
const loading = ref(false)
const error = ref(null)
const result = ref(null)

const adapterOptions = [
  { value: 'json', label: 'JSON' },
  { value: 'csv', label: 'CSV' },
  { value: 'opentrans', label: 'openTRANS RFQ (XML)' },
]

const collectionTypeOptions = computed(() =>
  store.types.map((t) => ({ value: t.id, label: t.name_de || t.technical_name }))
)

function onFileChange(e) {
  file.value = e.target.files[0] || null
}

async function submit() {
  error.value = null
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('collection_type_id', form.value.collection_type_id)
    formData.append('adapter', form.value.adapter)
    if (file.value) {
      formData.append('file', file.value)
    } else {
      formData.append('payload', payload.value)
    }
    if (form.value.organization_name) formData.append('organization[name]', form.value.organization_name)
    if (form.value.organization_external_ref) formData.append('organization[external_ref]', form.value.organization_external_ref)
    if (form.value.currency) formData.append('currency', form.value.currency)

    const { data } = await collectionsApi.import(formData)
    result.value = data.data
  } catch (e) {
    error.value = e.response?.data?.detail || e.response?.data?.message || 'Import fehlgeschlagen.'
  } finally {
    loading.value = false
  }
}

function reset() {
  result.value = null
  file.value = null
  payload.value = ''
}

onMounted(() => {
  if (!store.types.length) store.fetchTypes()
})
</script>

<template>
  <div class="max-w-2xl space-y-4">
    <div class="flex items-center gap-3">
      <button class="pim-btn pim-btn-ghost p-1.5" @click="router.push('/collections')" title="Zurück">
        <ArrowLeft class="w-4 h-4" :stroke-width="2" />
      </button>
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Collection importieren</h2>
    </div>

    <div v-if="!result" class="pim-card p-4 space-y-4">
      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Collection-Typ *</label>
        <select v-model="form.collection_type_id" class="pim-input text-xs">
          <option value="" disabled>Auswählen…</option>
          <option v-for="o in collectionTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Format *</label>
        <select v-model="form.adapter" class="pim-input text-xs">
          <option v-for="o in adapterOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </div>

      <div>
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Datei</label>
        <input type="file" class="pim-input text-xs" @change="onFileChange" />
      </div>

      <div v-if="!file">
        <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">
          ...oder Rohdaten einfügen
        </label>
        <textarea v-model="payload" rows="6" class="pim-input text-xs font-mono" placeholder="JSON / CSV / XML" />
      </div>

      <div v-if="form.adapter === 'csv'" class="grid grid-cols-2 gap-3 pt-2 border-t border-[var(--color-border)]">
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Empfänger-Name</label>
          <input v-model="form.organization_name" class="pim-input text-xs" />
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Externe Referenz</label>
          <input v-model="form.organization_external_ref" class="pim-input text-xs" />
        </div>
        <div>
          <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Währung</label>
          <input v-model="form.currency" class="pim-input text-xs" placeholder="EUR" />
        </div>
      </div>

      <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">
        {{ error }}
      </div>

      <button
        class="pim-btn pim-btn-primary"
        :disabled="loading || !form.collection_type_id || (!file && !payload)"
        @click="submit"
      >
        <UploadCloud class="w-4 h-4" :stroke-width="2" />
        {{ loading ? 'Importiere…' : 'Importieren' }}
      </button>
    </div>

    <div v-else class="space-y-4">
      <div class="pim-card p-4">
        <p class="text-xs text-[var(--color-text-secondary)]">Collection erstellt:</p>
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ result.name }}</h3>
        <p class="text-[10px] text-[var(--color-text-tertiary)]">{{ result.items?.length || 0 }} Position(en) importiert</p>
      </div>

      <CollectionMatchQueue :collection-id="result.id" />

      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-secondary text-xs" @click="reset">Weiteren Import starten</button>
        <button class="pim-btn pim-btn-primary text-xs" @click="router.push('/collections')">Zur Collection-Liste</button>
      </div>
    </div>
  </div>
</template>
