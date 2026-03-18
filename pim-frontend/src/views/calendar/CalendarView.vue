<script setup>
import { ref, onMounted, watch } from 'vue'
import { Plus, Calendar, CalendarDays, CalendarClock } from 'lucide-vue-next'
import calendarApi from '@/api/calendar'
import scheduledActionsApi from '@/api/scheduledActions'
import exportJobsApi from '@/api/exportJobs'
import PimCalendar from '@/components/calendar/PimCalendar.vue'
import ScheduledActionDialog from '@/components/calendar/ScheduledActionDialog.vue'

const events = ref([])
const loading = ref(false)
const currentRange = ref({ from: null, to: null })
const view = ref('month')

// Dialog state
const showDialog = ref(false)
const dialogDate = ref(null)
const editAction = ref(null)
const exportJobs = ref([])

// Filters
const filterType = ref('')
const filterStatus = ref('')

const ACTION_TYPE_LABELS = {
  activate_product: 'Aktivierung',
  deactivate_product: 'Deaktivierung',
  price_change: 'Preisänderung',
  data_change: 'Datenänderung',
  export: 'Export',
  version_publish: 'Version',
  project_deadline: 'Projekt',
}

const views = [
  { key: 'month', icon: Calendar, label: 'Monat' },
  { key: 'week', icon: CalendarDays, label: 'Woche' },
  { key: 'day', icon: CalendarClock, label: 'Tag' },
]

async function loadEvents() {
  if (!currentRange.value.from || !currentRange.value.to) return
  loading.value = true
  try {
    const { data } = await calendarApi.getEvents(currentRange.value.from, currentRange.value.to)
    let items = data.data || []

    // Client-side filter
    if (filterType.value) {
      items = items.filter(e => e.action_type === filterType.value)
    }
    if (filterStatus.value) {
      items = items.filter(e => e.status === filterStatus.value)
    }

    events.value = items
  } catch (e) {
    console.error('Failed to load calendar events:', e)
  } finally {
    loading.value = false
  }
}

async function loadExportJobs() {
  try {
    const { data } = await exportJobsApi.list()
    exportJobs.value = data.data || data || []
  } catch { /* ignore */ }
}

function onRangeChange(range) {
  currentRange.value = range
  loadEvents()
}

function onDateClick(date) {
  dialogDate.value = date
  editAction.value = null
  showDialog.value = true
}

async function onEventClick(event) {
  if (event.source === 'scheduled_action' && event.editable) {
    try {
      const { data } = await scheduledActionsApi.show(event.source_id)
      editAction.value = { ...data.data, product_name: event.product_name }
      dialogDate.value = null
      showDialog.value = true
    } catch (e) {
      console.error('Failed to load scheduled action:', e)
    }
  }
}

function onDialogSaved() {
  loadEvents()
}

watch([filterType, filterStatus], () => loadEvents())

onMounted(() => {
  loadExportJobs()
})
</script>

<template>
  <div class="space-y-4 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Planungskalender</h2>
      <div class="flex items-center gap-2 flex-wrap">
        <!-- View Switcher -->
        <div class="flex rounded-md border border-[var(--color-border)] overflow-hidden">
          <button
            v-for="v in views"
            :key="v.key"
            class="flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-medium transition-colors"
            :class="view === v.key
              ? 'bg-[var(--color-primary)] text-white'
              : 'bg-[var(--color-bg-primary)] text-[var(--color-text-secondary)] hover:bg-[var(--color-bg-hover)]'"
            @click="view = v.key"
          >
            <component :is="v.icon" class="w-3.5 h-3.5" />
            {{ v.label }}
          </button>
        </div>

        <!-- Filters -->
        <select v-model="filterType" class="pim-input text-[11px] py-1.5">
          <option value="">Alle Typen</option>
          <option v-for="(label, key) in ACTION_TYPE_LABELS" :key="key" :value="key">{{ label }}</option>
        </select>
        <select v-model="filterStatus" class="pim-input text-[11px] py-1.5">
          <option value="">Alle Status</option>
          <option value="pending">Ausstehend</option>
          <option value="completed">Abgeschlossen</option>
          <option value="failed">Fehlgeschlagen</option>
        </select>

        <!-- New Action -->
        <button class="pim-btn pim-btn-primary text-xs" @click="dialogDate = null; editAction = null; showDialog = true">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          Neue Aktion
        </button>
      </div>
    </div>

    <!-- Calendar -->
    <div class="pim-card p-4">
      <div v-if="loading" class="text-center py-12 text-sm text-[var(--color-text-tertiary)]">Laden...</div>
      <PimCalendar
        :events="events"
        :view="view"
        @range-change="onRangeChange"
        @date-click="onDateClick"
        @event-click="onEventClick"
      />
    </div>

    <!-- Legend -->
    <div class="flex items-center gap-4 flex-wrap text-[10px] text-[var(--color-text-tertiary)]">
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #22c55e"></span> Aktivierung</div>
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #ef4444"></span> Deaktivierung</div>
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #3b82f6"></span> Preisänderung</div>
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #f59e0b"></span> Datenänderung</div>
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #14b8a6"></span> Export</div>
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #8b5cf6"></span> Version</div>
      <div class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background: #ec4899"></span> Projekt</div>
    </div>

    <!-- Dialog -->
    <ScheduledActionDialog
      :visible="showDialog"
      :initial-date="dialogDate"
      :edit-action="editAction"
      :export-jobs="exportJobs"
      @close="showDialog = false"
      @saved="onDialogSaved"
      @deleted="onDialogSaved"
    />
  </div>
</template>
