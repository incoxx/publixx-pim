<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import contentApi from '@/api/content'

const props = defineProps({
  node: { type: Object, default: null },       // edit-Modus
  parentNodeId: { type: String, default: null }, // create-Modus: Elternknoten
  onSaved: { type: Function, default: null },
})

const authStore = useAuthStore()
const navStore = useNavigationStore()
const loading = ref(false)
const errors = ref({})
const isEdit = computed(() => !!props.node)

const targetTypes = [
  { value: 'folder', label: 'Ordner (nur Menüpunkt)' },
  { value: 'content_page', label: 'Content-Seite' },
  { value: 'product_category', label: 'Produktkategorie (Hierarchieknoten)' },
  { value: 'product', label: 'Einzelnes Produkt' },
  { value: 'external_url', label: 'Externer Link' },
]

const form = ref(
  props.node
    ? { ...props.node }
    : {
        label_de: '', label_en: '', slug: '', icon: '',
        is_visible: true, target_type: 'folder',
        content_page_id: null, hierarchy_node_id: null, product_id: null,
        external_url: '', auto_expand: false, expand_depth: null,
      }
)

// Content-Seiten für das Dropdown (target_type = content_page)
const contentPages = ref([])

async function save() {
  loading.value = true
  errors.value = {}
  try {
    const payload = { ...form.value }
    if (isEdit.value) {
      await navStore.updateNode(props.node.id, payload)
    } else {
      if (props.parentNodeId) payload.parent_node_id = props.parentNodeId
      await navStore.createNode(payload)
    }
    authStore.closePanel()
    if (props.onSaved) props.onSaved()
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data.errors || {}
      for (const [key, val] of Object.entries(serverErrors)) {
        errors.value[key] = Array.isArray(val) ? val[0] : val
      }
    } else {
      errors.value._general = e.response?.data?.title || 'Ein Fehler ist aufgetreten.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await contentApi.list({ perPage: 200 })
    contentPages.value = data.data ?? data
  } catch { contentPages.value = [] }
})
</script>

<template>
  <div class="p-4 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
      {{ isEdit ? 'Knoten bearbeiten' : 'Neuer Knoten' }}
    </h3>
    <p v-if="errors._general" class="text-[12px] text-[var(--color-error)] bg-[var(--color-error-light)] px-3 py-2 rounded-lg">
      {{ errors._general }}
    </p>

    <div>
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beschriftung (DE) <span class="text-[var(--color-error)]">*</span></label>
      <input v-model="form.label_de" class="pim-input text-xs w-full" />
      <p v-if="errors.label_de" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.label_de }}</p>
    </div>

    <div>
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Beschriftung (EN)</label>
      <input v-model="form.label_en" class="pim-input text-xs w-full" />
    </div>

    <div>
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">URL-Segment (Slug)</label>
      <input v-model="form.slug" class="pim-input text-xs w-full" placeholder="z. B. loesungen" />
    </div>

    <div>
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Zieltyp <span class="text-[var(--color-error)]">*</span></label>
      <select v-model="form.target_type" class="pim-input text-xs w-full">
        <option v-for="t in targetTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>
    </div>

    <!-- Ziel-spezifische Felder -->
    <div v-if="form.target_type === 'content_page'">
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Content-Seite <span class="text-[var(--color-error)]">*</span></label>
      <select v-model="form.content_page_id" class="pim-input text-xs w-full">
        <option :value="null">— wählen —</option>
        <option v-for="p in contentPages" :key="p.id" :value="p.id">{{ p.title }} (/{{ p.slug }})</option>
      </select>
      <p v-if="errors.content_page_id" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.content_page_id }}</p>
    </div>

    <template v-else-if="form.target_type === 'product_category'">
      <div>
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Hierarchieknoten-ID <span class="text-[var(--color-error)]">*</span></label>
        <input v-model="form.hierarchy_node_id" class="pim-input text-xs w-full" placeholder="UUID (Picker folgt)" />
        <p v-if="errors.hierarchy_node_id" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.hierarchy_node_id }}</p>
      </div>
      <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
        <input type="checkbox" v-model="form.auto_expand" class="pim-checkbox" />
        Kategorie-Unterbaum automatisch als Menü expandieren (Mount-Point)
      </label>
      <div v-if="form.auto_expand">
        <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Expansionstiefe (leer = alle)</label>
        <input v-model.number="form.expand_depth" type="number" min="1" class="pim-input text-xs w-full" />
      </div>
    </template>

    <div v-else-if="form.target_type === 'product'">
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">Produkt-ID <span class="text-[var(--color-error)]">*</span></label>
      <input v-model="form.product_id" class="pim-input text-xs w-full" placeholder="UUID (Picker folgt)" />
      <p v-if="errors.product_id" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.product_id }}</p>
    </div>

    <div v-else-if="form.target_type === 'external_url'">
      <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">URL <span class="text-[var(--color-error)]">*</span></label>
      <input v-model="form.external_url" class="pim-input text-xs w-full" placeholder="https://…" />
      <p v-if="errors.external_url" class="mt-1 text-[11px] text-[var(--color-error)]">{{ errors.external_url }}</p>
    </div>

    <label class="flex items-center gap-2 text-[12px] text-[var(--color-text-secondary)]">
      <input type="checkbox" v-model="form.is_visible" class="pim-checkbox" />
      Im Menü sichtbar (sonst nur in der Sitemap)
    </label>

    <div class="flex items-center gap-2 pt-3 border-t border-[var(--color-border)]">
      <button class="pim-btn pim-btn-primary text-xs" :disabled="loading" @click="save">
        {{ loading ? 'Speichern…' : 'Speichern' }}
      </button>
      <button class="pim-btn pim-btn-secondary text-xs" @click="authStore.closePanel()">Abbrechen</button>
    </div>
  </div>
</template>
