<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTranslationJobsStore } from '@/stores/translationJobs'
import searchApi from '@/api/search'
import { ArrowLeft, ArrowRight, Languages, Check } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const store = useTranslationJobsStore()

const step = ref(1)
const loading = ref(false)
const error = ref('')

// Step 1: Scope
const scope = ref('products')

// Step 2: Product selection (if scope includes products)
const productIds = ref([])
const productCount = ref(0)
const productSearchTerm = ref('')

// Step 3: Attribute selection
const allAttributes = ref([])
const selectedAttributeIds = ref([])

// Step 4: Languages
const sourceLanguage = ref('de')
const targetLanguage = ref('')

// Step 5: Summary
const jobName = ref('')

// System entity types
const systemEntityTypes = [
  { value: 'attribute', label: 'Attribute' },
  { value: 'attribute_type', label: 'Attributtypen' },
  { value: 'value_list', label: 'Wertelisten' },
  { value: 'value_list_entry', label: 'Wertelisten-Einträge' },
  { value: 'hierarchy', label: 'Hierarchien' },
  { value: 'hierarchy_node', label: 'Hierarchie-Knoten' },
  { value: 'product_type', label: 'Produkttypen' },
  { value: 'unit_group', label: 'Einheitengruppen' },
  { value: 'relation_type', label: 'Relationstypen' },
]
const selectedEntityTypes = ref([])

const availableLanguages = [
  { code: 'de', label: 'Deutsch' },
  { code: 'en', label: 'Englisch' },
  { code: 'fr', label: 'Französisch' },
  { code: 'it', label: 'Italienisch' },
  { code: 'es', label: 'Spanisch' },
  { code: 'nl', label: 'Niederländisch' },
  { code: 'pl', label: 'Polnisch' },
  { code: 'pt', label: 'Portugiesisch' },
  { code: 'cs', label: 'Tschechisch' },
  { code: 'da', label: 'Dänisch' },
  { code: 'sv', label: 'Schwedisch' },
]

const translatableAttributes = computed(() => {
  return allAttributes.value.filter(a => a.is_translatable && (a.data_type === 'String' || a.data_type === 'RichText'))
})

const canProceed = computed(() => {
  switch (step.value) {
    case 1: return !!scope.value
    case 2:
      if (scope.value === 'products' || scope.value === 'mixed') {
        return productIds.value.length > 0
      }
      if (scope.value === 'system') {
        return selectedEntityTypes.value.length > 0
      }
      return true
    case 3:
      if (scope.value === 'products' || scope.value === 'mixed') {
        return selectedAttributeIds.value.length > 0
      }
      if (scope.value === 'system') {
        return selectedEntityTypes.value.length > 0
      }
      return true
    case 4: return !!sourceLanguage.value && !!targetLanguage.value && sourceLanguage.value !== targetLanguage.value
    case 5: return !!jobName.value.trim()
    default: return false
  }
})

const totalSteps = computed(() => {
  if (scope.value === 'system') return 4 // skip attribute selection
  return 5
})

onMounted(async () => {
  // Load searchable attributes
  try {
    const { data } = await searchApi.searchableAttributes()
    allAttributes.value = data.data || data
  } catch (e) { /* ignore */ }

  // Check for pre-selected product IDs from search
  const idsParam = route.query.product_ids
  if (idsParam) {
    productIds.value = idsParam.split(',').filter(Boolean)
    productCount.value = productIds.value.length
  }
})

function nextStep() {
  if (step.value < totalSteps.value) {
    // Skip attribute step for system scope
    if (scope.value === 'system' && step.value === 2) {
      step.value = 4 // jump to languages
    } else {
      step.value++
    }
  }
}

function prevStep() {
  if (step.value > 1) {
    if (scope.value === 'system' && step.value === 4) {
      step.value = 2 // jump back to entity types
    } else {
      step.value--
    }
  }
}

async function createJob() {
  loading.value = true
  error.value = ''
  try {
    const payload = {
      name: jobName.value.trim(),
      source_language: sourceLanguage.value,
      target_language: targetLanguage.value,
      scope: scope.value,
    }

    if (scope.value === 'products' || scope.value === 'mixed') {
      payload.product_ids = productIds.value
      payload.attribute_ids = selectedAttributeIds.value
    }
    if (scope.value === 'system' || scope.value === 'mixed') {
      payload.entity_types = selectedEntityTypes.value
    }

    const job = await store.createJob(payload)
    router.push(`/translation-jobs/${job.id}`)
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Erstellen'
  } finally {
    loading.value = false
  }
}

function toggleAttribute(id) {
  const idx = selectedAttributeIds.value.indexOf(id)
  if (idx === -1) selectedAttributeIds.value.push(id)
  else selectedAttributeIds.value.splice(idx, 1)
}

function toggleEntityType(value) {
  const idx = selectedEntityTypes.value.indexOf(value)
  if (idx === -1) selectedEntityTypes.value.push(value)
  else selectedEntityTypes.value.splice(idx, 1)
}

function selectAllAttributes() {
  selectedAttributeIds.value = translatableAttributes.value.map(a => a.id)
}
</script>

<template>
  <div class="space-y-6 max-w-3xl mx-auto">
    <!-- Back -->
    <button class="flex items-center gap-2 text-sm text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]" @click="router.push('/translation-jobs')">
      <ArrowLeft class="w-4 h-4" />
      Zurück zu Übersetzungsjobs
    </button>

    <!-- Header -->
    <div class="flex items-center gap-3">
      <Languages class="w-6 h-6 text-[var(--color-accent)]" />
      <h1 class="text-xl font-bold">Neuen Übersetzungsjob erstellen</h1>
    </div>

    <!-- Progress indicator -->
    <div class="flex items-center gap-2">
      <template v-for="s in totalSteps" :key="s">
        <div
          class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium border-2 transition-colors"
          :class="s <= step ? 'border-[var(--color-accent)] bg-[var(--color-accent)] text-white' : 'border-[var(--color-border)] text-[var(--color-text-tertiary)]'"
        >
          <Check v-if="s < step" class="w-4 h-4" />
          <span v-else>{{ s }}</span>
        </div>
        <div v-if="s < totalSteps" class="flex-1 h-0.5" :class="s < step ? 'bg-[var(--color-accent)]' : 'bg-[var(--color-border)]'" />
      </template>
    </div>

    <!-- Step content -->
    <div class="pim-card p-6 space-y-4">
      <!-- Step 1: Scope -->
      <div v-if="step === 1">
        <h2 class="text-sm font-semibold mb-4">Was soll übersetzt werden?</h2>
        <div class="space-y-3">
          <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--color-border)] cursor-pointer hover:bg-[var(--color-bg)]" :class="scope === 'products' ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/5' : ''">
            <input type="radio" v-model="scope" value="products" class="mt-0.5" />
            <div>
              <p class="text-sm font-medium">Produkt-Attribute</p>
              <p class="text-xs text-[var(--color-text-tertiary)]">Übersetzen Sie Produkttexte wie Langbeschreibung, Kurzbeschreibung etc.</p>
            </div>
          </label>
          <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--color-border)] cursor-pointer hover:bg-[var(--color-bg)]" :class="scope === 'system' ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/5' : ''">
            <input type="radio" v-model="scope" value="system" class="mt-0.5" />
            <div>
              <p class="text-sm font-medium">System-Texte</p>
              <p class="text-xs text-[var(--color-text-tertiary)]">Übersetzen Sie Attributnamen, Wertelisten-Einträge, Hierarchie-Namen etc.</p>
            </div>
          </label>
          <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--color-border)] cursor-pointer hover:bg-[var(--color-bg)]" :class="scope === 'mixed' ? 'border-[var(--color-accent)] bg-[var(--color-accent)]/5' : ''">
            <input type="radio" v-model="scope" value="mixed" class="mt-0.5" />
            <div>
              <p class="text-sm font-medium">Beides</p>
              <p class="text-xs text-[var(--color-text-tertiary)]">Produkt-Attribute und System-Texte in einem Job.</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Step 2: Selection -->
      <div v-if="step === 2">
        <!-- Products -->
        <div v-if="scope === 'products' || scope === 'mixed'">
          <h2 class="text-sm font-semibold mb-4">Produkte auswählen</h2>
          <div v-if="productIds.length > 0" class="p-3 rounded-lg bg-green-50 text-green-700 text-sm mb-4">
            {{ productIds.length }} Produkte aus der Suche ausgewählt.
          </div>
          <p v-else class="text-xs text-[var(--color-text-tertiary)]">
            Tipp: Verwenden Sie die Suche, um Produkte auszuwählen und von dort einen Übersetzungsjob zu erstellen.
          </p>
        </div>

        <!-- System entities -->
        <div v-if="scope === 'system' || scope === 'mixed'">
          <h2 class="text-sm font-semibold mb-4">System-Entitäten auswählen</h2>
          <div class="space-y-2">
            <label
              v-for="et in systemEntityTypes"
              :key="et.value"
              class="flex items-center gap-2 px-3 py-2 rounded hover:bg-[var(--color-bg)] cursor-pointer text-sm"
            >
              <input
                type="checkbox"
                :checked="selectedEntityTypes.includes(et.value)"
                @change="toggleEntityType(et.value)"
                class="rounded border-[var(--color-border)]"
              />
              {{ et.label }}
            </label>
          </div>
        </div>
      </div>

      <!-- Step 3: Attributes (products only) -->
      <div v-if="step === 3 && (scope === 'products' || scope === 'mixed')">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold">Attribute zum Übersetzen auswählen</h2>
          <button class="text-xs text-[var(--color-accent)] hover:underline" @click="selectAllAttributes">
            Alle auswählen
          </button>
        </div>
        <div class="space-y-2 max-h-80 overflow-y-auto">
          <label
            v-for="attr in translatableAttributes"
            :key="attr.id"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-[var(--color-bg)] cursor-pointer text-sm"
          >
            <input
              type="checkbox"
              :checked="selectedAttributeIds.includes(attr.id)"
              @change="toggleAttribute(attr.id)"
              class="rounded border-[var(--color-border)]"
            />
            <span>{{ attr.name_de || attr.technical_name }}</span>
            <span class="text-[11px] text-[var(--color-text-tertiary)]">({{ attr.data_type }})</span>
          </label>
          <p v-if="translatableAttributes.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-4 text-center">
            Keine übersetzbaren Attribute gefunden.
          </p>
        </div>
      </div>

      <!-- Step 4: Languages -->
      <div v-if="step === 4 || (scope === 'system' && step === 3)">
        <h2 class="text-sm font-semibold mb-4">Sprachen wählen</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-2">Quellsprache</label>
            <select class="pim-input text-sm" v-model="sourceLanguage">
              <option v-for="lang in availableLanguages" :key="lang.code" :value="lang.code">
                {{ lang.label }} ({{ lang.code }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-2">Zielsprache</label>
            <select class="pim-input text-sm" v-model="targetLanguage">
              <option value="">— Wählen —</option>
              <option v-for="lang in availableLanguages.filter(l => l.code !== sourceLanguage)" :key="lang.code" :value="lang.code">
                {{ lang.label }} ({{ lang.code }})
              </option>
            </select>
          </div>
        </div>
        <p v-if="sourceLanguage === targetLanguage && targetLanguage" class="text-xs text-[var(--color-error)] mt-2">
          Quell- und Zielsprache müssen unterschiedlich sein.
        </p>
      </div>

      <!-- Step 5: Summary -->
      <div v-if="step === 5 || (scope === 'system' && step === 4)">
        <h2 class="text-sm font-semibold mb-4">Zusammenfassung</h2>
        <div>
          <label class="block text-xs font-medium text-[var(--color-text-secondary)] mb-2">Job-Name</label>
          <input
            class="pim-input text-sm w-full"
            v-model="jobName"
            :placeholder="`${scope === 'products' ? 'Produkte' : 'System'} ${sourceLanguage} → ${targetLanguage}`"
          />
        </div>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between py-2 border-b border-[var(--color-border)]">
            <span class="text-[var(--color-text-tertiary)]">Scope</span>
            <span>{{ scope === 'products' ? 'Produkte' : scope === 'system' ? 'System' : 'Gemischt' }}</span>
          </div>
          <div v-if="scope !== 'system'" class="flex justify-between py-2 border-b border-[var(--color-border)]">
            <span class="text-[var(--color-text-tertiary)]">Produkte</span>
            <span>{{ productIds.length }}</span>
          </div>
          <div v-if="scope !== 'system'" class="flex justify-between py-2 border-b border-[var(--color-border)]">
            <span class="text-[var(--color-text-tertiary)]">Attribute</span>
            <span>{{ selectedAttributeIds.length }}</span>
          </div>
          <div v-if="scope !== 'products'" class="flex justify-between py-2 border-b border-[var(--color-border)]">
            <span class="text-[var(--color-text-tertiary)]">Entitätstypen</span>
            <span>{{ selectedEntityTypes.length }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-[var(--color-border)]">
            <span class="text-[var(--color-text-tertiary)]">Sprachen</span>
            <span class="uppercase">{{ sourceLanguage }} → {{ targetLanguage }}</span>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="p-3 rounded-lg bg-[var(--color-error-light)] text-[var(--color-error)] text-xs">
        {{ error }}
      </div>
    </div>

    <!-- Navigation buttons -->
    <div class="flex justify-between">
      <button v-if="step > 1" class="pim-btn pim-btn-secondary" @click="prevStep">
        <ArrowLeft class="w-4 h-4" />
        Zurück
      </button>
      <div v-else />

      <button
        v-if="step < totalSteps || (scope === 'system' && step < 4)"
        class="pim-btn pim-btn-primary"
        :disabled="!canProceed"
        @click="nextStep"
      >
        Weiter
        <ArrowRight class="w-4 h-4" />
      </button>
      <button
        v-else
        class="pim-btn pim-btn-primary"
        :disabled="!canProceed || loading"
        @click="createJob"
      >
        {{ loading ? 'Erstelle...' : 'Job erstellen' }}
      </button>
    </div>
  </div>
</template>
