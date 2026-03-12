<script setup>
import { ref, onMounted, markRaw } from 'vue'
import { Plus, Copy, Check, Shield, Link2 } from 'lucide-vue-next'
import accessLinksApi from '@/api/accessLinks'
import { useAuthStore } from '@/stores/auth'
import PimTable from '@/components/shared/PimTable.vue'
import PimDeleteConfirmDialog from '@/components/shared/PimDeleteConfirmDialog.vue'
import AccessLinkFormPanel from '@/components/panels/AccessLinkFormPanel.vue'

const authStore = useAuthStore()
const items = ref([])
const loading = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const copiedId = ref(null)
const statusFilter = ref('')

const report = ref({ total: 0, used: 0, open: 0, expired: 0 })

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'role', label: 'Rolle' },
  { key: 'link', label: 'Link' },
  { key: 'status', label: 'Status' },
  { key: 'expires_at', label: 'Ablauf', sortable: true },
  { key: 'used_at', label: 'Eingelöst', sortable: true },
  { key: 'ip_address', label: 'IP' },
]

async function fetchLinks() {
  loading.value = true
  try {
    const [linksRes, reportRes] = await Promise.all([
      accessLinksApi.list({ perPage: 50, ...(statusFilter.value ? { status: statusFilter.value } : {}) }),
      accessLinksApi.report(),
    ])
    items.value = linksRes.data.data || linksRes.data
    report.value = reportRes.data.data || reportRes.data
  } finally {
    loading.value = false
  }
}

function openCreatePanel() {
  authStore.openPanel(markRaw(AccessLinkFormPanel), {
    onSaved: () => fetchLinks(),
  })
}

function handleRowAction(row) {
  deleteTarget.value = row
}

async function confirmDelete() {
  deleting.value = true
  try {
    await accessLinksApi.remove(deleteTarget.value.id)
    deleteTarget.value = null
    await fetchLinks()
  } finally {
    deleting.value = false
  }
}

async function copyLink(url, id) {
  try {
    await navigator.clipboard.writeText(url)
    copiedId.value = id
    setTimeout(() => { copiedId.value = null }, 2000)
  } catch { /* ignore */ }
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function setFilter(status) {
  statusFilter.value = statusFilter.value === status ? '' : status
  fetchLinks()
}

onMounted(fetchLinks)
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Zugangslinks</h2>
      <button class="pim-btn pim-btn-primary" @click="openCreatePanel">
        <Plus class="w-4 h-4" :stroke-width="2" /> Neuer Link
      </button>
    </div>

    <!-- Report cards -->
    <div class="grid grid-cols-4 gap-3">
      <button
        class="pim-card p-3 text-center transition-colors cursor-pointer"
        :class="statusFilter === '' ? 'ring-2 ring-[var(--color-accent)]' : 'hover:bg-[var(--color-surface-hover)]'"
        @click="setFilter('')"
      >
        <div class="text-2xl font-bold text-[var(--color-text-primary)]">{{ report.total }}</div>
        <div class="text-xs text-[var(--color-text-tertiary)]">Gesamt</div>
      </button>
      <button
        class="pim-card p-3 text-center transition-colors cursor-pointer"
        :class="statusFilter === 'open' ? 'ring-2 ring-[var(--color-accent)]' : 'hover:bg-[var(--color-surface-hover)]'"
        @click="setFilter('open')"
      >
        <div class="text-2xl font-bold text-[var(--color-accent)]">{{ report.open }}</div>
        <div class="text-xs text-[var(--color-text-tertiary)]">Offen</div>
      </button>
      <button
        class="pim-card p-3 text-center transition-colors cursor-pointer"
        :class="statusFilter === 'used' ? 'ring-2 ring-[var(--color-accent)]' : 'hover:bg-[var(--color-surface-hover)]'"
        @click="setFilter('used')"
      >
        <div class="text-2xl font-bold text-[var(--color-success)]">{{ report.used }}</div>
        <div class="text-xs text-[var(--color-text-tertiary)]">Eingelöst</div>
      </button>
      <button
        class="pim-card p-3 text-center transition-colors cursor-pointer"
        :class="statusFilter === 'expired' ? 'ring-2 ring-[var(--color-accent)]' : 'hover:bg-[var(--color-surface-hover)]'"
        @click="setFilter('expired')"
      >
        <div class="text-2xl font-bold text-[var(--color-text-tertiary)]">{{ report.expired }}</div>
        <div class="text-xs text-[var(--color-text-tertiary)]">Abgelaufen</div>
      </button>
    </div>

    <PimTable
      :columns="columns"
      :rows="items"
      :loading="loading"
      :showActions="true"
      emptyText="Keine Zugangslinks"
      @row-action="handleRowAction"
    >
      <template #cell-role="{ row }">
        <span class="pim-badge bg-[var(--color-info-light)] text-[var(--color-info)]">
          <Shield class="w-3 h-3" :stroke-width="2" /> {{ row.role?.name || '–' }}
        </span>
      </template>

      <template #cell-link="{ row }">
        <div class="flex items-center gap-1" v-if="row.status === 'open'">
          <span class="text-xs font-mono text-[var(--color-text-tertiary)] truncate max-w-[160px]">{{ row.url }}</span>
          <button class="p-0.5 rounded hover:bg-[var(--color-bg)] transition-colors" @click.stop="copyLink(row.url, row.id)">
            <Check v-if="copiedId === row.id" class="w-3.5 h-3.5 text-[var(--color-success)]" :stroke-width="2" />
            <Copy v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          </button>
        </div>
        <span v-else class="text-xs text-[var(--color-text-tertiary)]">–</span>
      </template>

      <template #cell-status="{ row }">
        <span
          class="pim-badge"
          :class="{
            'bg-[var(--color-success-light)] text-[var(--color-success)]': row.status === 'used',
            'bg-[var(--color-accent-light,rgba(46,117,182,0.1))] text-[var(--color-accent)]': row.status === 'open',
            'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]': row.status === 'expired',
          }"
        >
          {{ row.status === 'used' ? 'Eingelöst' : row.status === 'open' ? 'Offen' : 'Abgelaufen' }}
        </span>
      </template>

      <template #cell-expires_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ formatDate(value) }}</span>
      </template>

      <template #cell-used_at="{ value }">
        <span class="text-xs text-[var(--color-text-tertiary)]">{{ formatDate(value) }}</span>
      </template>

      <template #cell-ip_address="{ value }">
        <span class="text-xs font-mono text-[var(--color-text-tertiary)]">{{ value || '–' }}</span>
      </template>
    </PimTable>

    <PimDeleteConfirmDialog
      :open="!!deleteTarget"
      title="Zugangslink löschen?"
      :message="`Der Zugangslink für '${deleteTarget?.name || ''}' wird unwiderruflich gelöscht.`"
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>
