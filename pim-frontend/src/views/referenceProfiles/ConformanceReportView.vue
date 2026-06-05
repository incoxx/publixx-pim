<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { conformance, referenceProfiles } from '@/api/referenceProfiles'
import PimTable from '@/components/shared/PimTable.vue'
import { ShieldCheck, ShieldAlert, ShieldX, Gauge } from 'lucide-vue-next'

const router = useRouter()

const loading = ref(false)
const summary = ref({ total: 0, pass: 0, warning: 0, fail: 0, avg_score: 0 })
const byProfile = ref([])
const topDeviations = ref([])
const products = ref({ data: [], current_page: 1, last_page: 1, total: 0 })

const profiles = ref([])
const filterProfile = ref('')
const filterStatus = ref('')
const page = ref(1)

const cards = computed(() => [
  { key: 'total', label: 'Geprüft', value: summary.value.total, icon: Gauge, color: 'var(--color-text-secondary)' },
  { key: 'pass', label: 'Konform', value: summary.value.pass, icon: ShieldCheck, color: 'var(--color-success)' },
  { key: 'warning', label: 'Warnungen', value: summary.value.warning, icon: ShieldAlert, color: 'var(--color-warning)' },
  { key: 'fail', label: 'Nicht konform', value: summary.value.fail, icon: ShieldX, color: 'var(--color-error)' },
])

const STATUS_LABEL = { pass: 'Konform', warning: 'Warnungen', fail: 'Nicht konform' }
const STATUS_COLOR = { pass: 'var(--color-success)', warning: 'var(--color-warning)', fail: 'var(--color-error)' }

const productColumns = [
  { key: 'sku', label: 'SKU', mono: true },
  { key: 'name', label: 'Produkt' },
  { key: 'profile', label: 'Profil' },
  { key: 'status', label: 'Status' },
  { key: 'score', label: 'Score' },
  { key: 'deviation_count', label: 'Abweichungen' },
]

const productRows = computed(() =>
  (products.value.data || []).map(r => ({
    id: r.product_id,
    sku: r.product?.sku || '—',
    name: r.product?.name || '—',
    profile: byProfile.value.find(p => p.profile_id === r.profile_id)?.profile_name || '—',
    status: r.status,
    score: r.score,
    deviation_count: r.deviation_count,
  }))
)

async function loadProfiles() {
  try {
    const { data } = await referenceProfiles.list()
    profiles.value = data.data || data
  } catch { /* ignore */ }
}

async function loadReport() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: 25 }
    if (filterProfile.value) params.profile_id = filterProfile.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await conformance.report(params)
    summary.value = data.summary
    byProfile.value = data.by_profile
    topDeviations.value = data.top_deviations
    products.value = data.products
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  loadReport()
}

function goPage(p) {
  page.value = p
  loadReport()
}

function openProduct(row) {
  router.push({ name: 'product-detail', params: { id: row.id } })
}

onMounted(() => {
  loadProfiles()
  loadReport()
})
</script>

<template>
  <div class="space-y-5">
    <div>
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Konformitäts-Report</h2>
      <p class="text-xs text-[var(--color-text-tertiary)]">
        Datenqualität der Produkte gegen ihre Referenz-Profile.
      </p>
    </div>

    <!-- Summary-Karten -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div v-for="c in cards" :key="c.key" class="pim-card p-3 flex items-center gap-3">
        <component :is="c.icon" class="w-7 h-7" :style="{ color: c.color }" />
        <div>
          <div class="text-xl font-bold" :style="{ color: c.color }">{{ c.value }}</div>
          <div class="text-xs text-[var(--color-text-tertiary)]">{{ c.label }}</div>
        </div>
      </div>
    </div>
    <div class="text-xs text-[var(--color-text-tertiary)]">
      Ø Score: <span class="font-semibold text-[var(--color-text-primary)]">{{ summary.avg_score }}%</span>
    </div>

    <!-- Aufschlüsselung pro Profil -->
    <div v-if="byProfile.length" class="space-y-2">
      <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Pro Profil</h3>
      <div class="space-y-1.5">
        <div v-for="p in byProfile" :key="p.profile_id"
             class="pim-card p-2 flex items-center gap-3 text-sm">
          <div class="flex-1 font-medium">{{ p.profile_name || '—' }}</div>
          <div class="flex h-3 w-40 rounded overflow-hidden bg-[var(--color-surface-hover)]">
            <div :style="{ width: (p.pass / p.total * 100) + '%', background: 'var(--color-success)' }"></div>
            <div :style="{ width: (p.warning / p.total * 100) + '%', background: 'var(--color-warning)' }"></div>
            <div :style="{ width: (p.fail / p.total * 100) + '%', background: 'var(--color-error)' }"></div>
          </div>
          <div class="text-xs text-[var(--color-text-tertiary)] w-32 text-right">
            {{ p.pass }}/{{ p.warning }}/{{ p.fail }} · Ø {{ p.avg_score }}%
          </div>
        </div>
      </div>
    </div>

    <!-- Top-Abweichungen -->
    <div v-if="topDeviations.length" class="space-y-2">
      <h3 class="text-sm font-medium text-[var(--color-text-primary)]">Häufigste Abweichungen</h3>
      <div class="flex flex-wrap gap-2">
        <span v-for="(d, i) in topDeviations" :key="i"
              class="inline-flex items-center gap-2 text-xs rounded px-2 py-1 border border-[var(--color-border)]">
          <span class="font-mono">{{ d.attribute }}</span>
          <span class="text-[var(--color-text-tertiary)]">{{ d.check }}</span>
          <span class="font-semibold" :style="{ color: STATUS_COLOR[d.severity === 'error' ? 'fail' : 'warning'] }">
            ×{{ d.count }}
          </span>
        </span>
      </div>
    </div>

    <!-- Filter + Produktliste -->
    <div class="space-y-2">
      <div class="flex flex-wrap gap-2 items-end">
        <div>
          <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Profil</label>
          <select v-model="filterProfile" class="pim-input" @change="applyFilters">
            <option value="">Alle</option>
            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs mb-1 text-[var(--color-text-secondary)]">Status</label>
          <select v-model="filterStatus" class="pim-input" @change="applyFilters">
            <option value="">Alle</option>
            <option value="fail">Nicht konform</option>
            <option value="warning">Warnungen</option>
            <option value="pass">Konform</option>
          </select>
        </div>
      </div>

      <PimTable
        :columns="productColumns"
        :rows="productRows"
        :loading="loading"
        emptyText="Keine Prüfergebnisse"
        @row-click="openProduct"
      >
        <template #cell-status="{ value }">
          <span :style="{ color: STATUS_COLOR[value] }">{{ STATUS_LABEL[value] || value }}</span>
        </template>
        <template #cell-score="{ value }">{{ value }}%</template>

        <template #pagination>
          <div v-if="products.last_page > 1" class="flex items-center justify-between px-2 py-2 text-sm">
            <span class="text-[var(--color-text-tertiary)]">
              Seite {{ products.current_page }} / {{ products.last_page }} ({{ products.total }})
            </span>
            <div class="flex gap-2">
              <button class="pim-btn pim-btn-ghost" :disabled="page <= 1" @click="goPage(page - 1)">Zurück</button>
              <button class="pim-btn pim-btn-ghost" :disabled="page >= products.last_page" @click="goPage(page + 1)">Weiter</button>
            </div>
          </div>
        </template>
      </PimTable>
    </div>
  </div>
</template>
