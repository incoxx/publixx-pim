<script setup>
import { ref, onMounted } from 'vue'
import { Plus, Trash2, Edit3, Clock, CheckCircle, XCircle, AlertCircle } from 'lucide-vue-next'
import scheduledActionsApi from '@/api/scheduledActions'
import exportJobsApi from '@/api/exportJobs'
import ScheduledActionDialog from '@/components/calendar/ScheduledActionDialog.vue'

const props = defineProps({
  productId: { type: String, required: true },
})

const actions = ref([])
const loading = ref(false)
const showDialog = ref(false)
const editAction = ref(null)
const exportJobs = ref([])

const ACTION_TYPE_LABELS = {
  activate_product: 'Aktivierung',
  deactivate_product: 'Deaktivierung',
  price_change: 'Preisänderung',
  data_change: 'Datenänderung',
  export: 'Export',
}

const ACTION_COLORS = {
  activate_product: '#22c55e',
  deactivate_product: '#ef4444',
  price_change: '#3b82f6',
  data_change: '#f59e0b',
  export: '#14b8a6',
}

const STATUS_CONFIG = {
  pending: { icon: Clock, label: 'Ausstehend', class: 'text-amber-500' },
  processing: { icon: AlertCircle, label: 'In Bearbeitung', class: 'text-blue-500' },
  completed: { icon: CheckCircle, label: 'Abgeschlossen', class: 'text-green-500' },
  failed: { icon: XCircle, label: 'Fehlgeschlagen', class: 'text-red-500' },
}

async function loadActions() {
  loading.value = true
  try {
    const { data } = await scheduledActionsApi.forProduct(props.productId)
    actions.value = data.data || []
  } catch (e) {
    console.error('Failed to load scheduled actions:', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editAction.value = null
  showDialog.value = true
}

function openEdit(action) {
  editAction.value = action
  showDialog.value = true
}

async function deleteAction(action) {
  if (!confirm('Geplante Aktion wirklich löschen?')) return
  try {
    await scheduledActionsApi.destroy(action.id)
    loadActions()
  } catch (e) {
    console.error('Failed to delete action:', e)
  }
}

function onSaved() {
  loadActions()
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loadExportJobs() {
  try {
    const { data } = await exportJobsApi.list()
    exportJobs.value = data.data || data || []
  } catch { /* ignore */ }
}

onMounted(() => {
  loadActions()
  loadExportJobs()
})
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-xs font-semibold text-[var(--color-text-secondary)]">Geplante Aktionen</h3>
      <button class="pim-btn pim-btn-primary text-xs" @click="openCreate">
        <Plus class="w-3.5 h-3.5" :stroke-width="2" />
        Neue Aktion
      </button>
    </div>

    <div v-if="loading" class="text-xs text-[var(--color-text-tertiary)] py-4 text-center">Laden...</div>

    <div v-else-if="actions.length === 0" class="text-xs text-[var(--color-text-tertiary)] py-8 text-center">
      Keine geplanten Aktionen für dieses Produkt.
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="action in actions"
        :key="action.id"
        class="pim-card p-3 flex items-start gap-3"
      >
        <!-- Color indicator -->
        <div class="w-1 self-stretch rounded-full flex-shrink-0" :style="{ backgroundColor: ACTION_COLORS[action.action_type] || '#6b7280' }"></div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-0.5">
            <span class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ action.title }}</span>
            <span
              class="text-[10px] px-1.5 py-0.5 rounded-full"
              :style="{ backgroundColor: (ACTION_COLORS[action.action_type] || '#6b7280') + '15', color: ACTION_COLORS[action.action_type] || '#6b7280' }"
            >
              {{ ACTION_TYPE_LABELS[action.action_type] || action.action_type }}
            </span>
          </div>
          <div class="flex items-center gap-3 text-[10px] text-[var(--color-text-tertiary)]">
            <span>{{ formatDateTime(action.scheduled_at) }}</span>
            <span class="flex items-center gap-0.5" :class="STATUS_CONFIG[action.status]?.class">
              <component :is="STATUS_CONFIG[action.status]?.icon" class="w-3 h-3" />
              {{ STATUS_CONFIG[action.status]?.label }}
            </span>
          </div>
          <div v-if="action.result_message" class="text-[10px] mt-1" :class="action.status === 'failed' ? 'text-red-500' : 'text-[var(--color-text-tertiary)]'">
            {{ action.result_message }}
          </div>
        </div>

        <!-- Actions -->
        <div v-if="action.status === 'pending'" class="flex items-center gap-1 flex-shrink-0">
          <button class="p-1 rounded hover:bg-[var(--color-bg-hover)]" @click="openEdit(action)" title="Bearbeiten">
            <Edit3 class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
          </button>
          <button class="p-1 rounded hover:bg-[var(--color-bg-hover)]" @click="deleteAction(action)" title="Löschen">
            <Trash2 class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
          </button>
        </div>
      </div>
    </div>

    <ScheduledActionDialog
      :visible="showDialog"
      :initial-product-id="productId"
      :edit-action="editAction"
      :export-jobs="exportJobs"
      @close="showDialog = false"
      @saved="onSaved"
    />
  </div>
</template>
