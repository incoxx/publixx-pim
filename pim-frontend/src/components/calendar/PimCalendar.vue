<script setup>
import { ref, computed, watch } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  events: { type: Array, default: () => [] },
  view: { type: String, default: 'month' },
})

const emit = defineEmits(['date-click', 'event-click', 'range-change'])

const currentDate = ref(new Date())
const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const HOURS = Array.from({ length: 24 }, (_, i) => i)

const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonth = computed(() => currentDate.value.getMonth())

const monthLabel = computed(() => {
  return currentDate.value.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
})

const weekLabel = computed(() => {
  const start = getWeekStart(currentDate.value)
  const end = new Date(start)
  end.setDate(end.getDate() + 6)
  return `${start.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })} – ${end.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })}`
})

const dayLabel = computed(() => {
  return currentDate.value.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

// --- Month view calendar grid ---
const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  // Monday = 0, Sunday = 6
  let startOffset = firstDay.getDay() - 1
  if (startOffset < 0) startOffset = 6

  const days = []

  // Previous month fill
  for (let i = startOffset - 1; i >= 0; i--) {
    const d = new Date(year, month, -i)
    days.push({ date: d, isCurrentMonth: false, isToday: isToday(d) })
  }

  // Current month
  for (let d = 1; d <= lastDay.getDate(); d++) {
    const date = new Date(year, month, d)
    days.push({ date, isCurrentMonth: true, isToday: isToday(date) })
  }

  // Next month fill
  const remaining = 42 - days.length
  for (let d = 1; d <= remaining; d++) {
    const date = new Date(year, month + 1, d)
    days.push({ date, isCurrentMonth: false, isToday: isToday(date) })
  }

  return days
})

// --- Week view ---
const weekDays = computed(() => {
  const start = getWeekStart(currentDate.value)
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(start)
    d.setDate(d.getDate() + i)
    return { date: d, isToday: isToday(d), label: d.toLocaleDateString('de-DE', { weekday: 'short', day: 'numeric' }) }
  })
})

// --- Day view ---
const dayEvents = computed(() => {
  return getEventsForDate(currentDate.value)
})

function getWeekStart(date) {
  const d = new Date(date)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  d.setDate(diff)
  d.setHours(0, 0, 0, 0)
  return d
}

function isToday(date) {
  const today = new Date()
  return date.getDate() === today.getDate() && date.getMonth() === today.getMonth() && date.getFullYear() === today.getFullYear()
}

function isSameDay(a, b) {
  return a.getDate() === b.getDate() && a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear()
}

// Pre-group events by date key for O(1) lookup instead of O(n) per cell
const eventsByDate = computed(() => {
  const map = {}
  for (const e of props.events) {
    const d = new Date(e.scheduled_at)
    const key = `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
    if (!map[key]) map[key] = []
    map[key].push(e)
  }
  return map
})

function dateKey(date) {
  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`
}

function getEventsForDate(date) {
  return eventsByDate.value[dateKey(date)] || []
}

function eventColor(event) {
  return event.color || '#6b7280'
}

function workflowColor(status) {
  const colors = { open: '#9ca3af', in_progress: '#f59e0b', done: '#22c55e' }
  return colors[status] || '#9ca3af'
}

function getEventHour(event) {
  return new Date(event.scheduled_at).getHours()
}

function navigate(delta) {
  const d = new Date(currentDate.value)
  if (props.view === 'month') {
    d.setMonth(d.getMonth() + delta)
  } else if (props.view === 'week') {
    d.setDate(d.getDate() + delta * 7)
  } else {
    d.setDate(d.getDate() + delta)
  }
  currentDate.value = d
  emitRange()
}

function goToday() {
  currentDate.value = new Date()
  emitRange()
}

function emitRange() {
  const d = currentDate.value
  let from, to
  if (props.view === 'month') {
    from = new Date(d.getFullYear(), d.getMonth(), 1)
    to = new Date(d.getFullYear(), d.getMonth() + 1, 0)
    // Include surrounding days visible in grid
    let startOffset = from.getDay() - 1
    if (startOffset < 0) startOffset = 6
    from.setDate(from.getDate() - startOffset)
    to.setDate(to.getDate() + (42 - (to.getDate() + startOffset)))
  } else if (props.view === 'week') {
    from = getWeekStart(d)
    to = new Date(from)
    to.setDate(to.getDate() + 6)
  } else {
    from = new Date(d)
    to = new Date(d)
  }
  emit('range-change', { from: formatDate(from), to: formatDate(to) })
}

function formatDate(d) {
  return d.toISOString().split('T')[0]
}

function onDateClick(date) {
  emit('date-click', formatDate(date))
}

function onEventClick(event) {
  emit('event-click', event)
}

const statusIcon = (status) => {
  if (status === 'completed') return '✓'
  if (status === 'failed') return '✗'
  if (status === 'processing') return '⟳'
  return ''
}

// Emit initial range
watch(() => props.view, () => emitRange(), { immediate: true })
</script>

<template>
  <div class="pim-calendar">
    <!-- Navigation Header -->
    <div class="flex items-center justify-between mb-4 px-1">
      <div class="flex items-center gap-2">
        <button class="pim-btn pim-btn-ghost p-1.5" @click="navigate(-1)">
          <ChevronLeft class="w-4 h-4" />
        </button>
        <button class="pim-btn pim-btn-ghost p-1.5" @click="navigate(1)">
          <ChevronRight class="w-4 h-4" />
        </button>
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)] ml-2">
          <template v-if="view === 'month'">{{ monthLabel }}</template>
          <template v-else-if="view === 'week'">{{ weekLabel }}</template>
          <template v-else>{{ dayLabel }}</template>
        </h3>
      </div>
      <button class="pim-btn pim-btn-ghost text-xs" @click="goToday">Heute</button>
    </div>

    <!-- MONTH VIEW -->
    <div v-if="view === 'month'" class="calendar-month">
      <div class="grid grid-cols-7 border-b border-[var(--color-border)]">
        <div v-for="day in WEEKDAYS" :key="day" class="text-center text-[10px] font-semibold text-[var(--color-text-tertiary)] py-1.5 uppercase tracking-wider">
          {{ day }}
        </div>
      </div>
      <div class="grid grid-cols-7">
        <div
          v-for="day in calendarDays"
          :key="day.date.toISOString()"
          class="calendar-day-cell border-b border-r border-[var(--color-border)] min-h-[90px] p-1 cursor-pointer hover:bg-[var(--color-bg-hover)] transition-colors"
          :class="{
            'bg-[var(--color-bg-secondary)]': !day.isCurrentMonth,
            'opacity-40': !day.isCurrentMonth,
          }"
          @click="onDateClick(day.date)"
        >
          <div class="flex items-center justify-between mb-0.5">
            <span
              class="text-[11px] font-medium w-6 h-6 flex items-center justify-center rounded-full"
              :class="day.isToday ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-text-secondary)]'"
            >
              {{ day.date.getDate() }}
            </span>
          </div>
          <div class="space-y-0.5">
            <div
              v-for="event in getEventsForDate(day.date).slice(0, 3)"
              :key="event.id"
              class="calendar-event text-[10px] leading-tight px-1.5 py-0.5 rounded truncate cursor-pointer flex items-center gap-1"
              :style="{ backgroundColor: eventColor(event) + '20', color: eventColor(event), borderLeft: `3px solid ${eventColor(event)}` }"
              @click.stop="onEventClick(event)"
            >
              <span
                class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                :style="{ backgroundColor: workflowColor(event.workflow_status) }"
              ></span>
              <span v-if="statusIcon(event.status)" class="mr-0.5">{{ statusIcon(event.status) }}</span>
              <span class="truncate">{{ event.title }}</span>
            </div>
            <div
              v-if="getEventsForDate(day.date).length > 3"
              class="text-[10px] text-[var(--color-text-tertiary)] pl-1.5"
            >
              +{{ getEventsForDate(day.date).length - 3 }} weitere
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- WEEK VIEW -->
    <div v-else-if="view === 'week'" class="calendar-week overflow-auto" style="max-height: 600px;">
      <!-- Header -->
      <div class="grid grid-cols-[60px_repeat(7,1fr)] sticky top-0 z-10 bg-[var(--color-bg-primary)] border-b border-[var(--color-border)]">
        <div class="border-r border-[var(--color-border)]"></div>
        <div
          v-for="day in weekDays"
          :key="day.label"
          class="text-center text-[11px] py-2 border-r border-[var(--color-border)]"
          :class="day.isToday ? 'font-bold text-[var(--color-primary)]' : 'text-[var(--color-text-secondary)]'"
        >
          {{ day.label }}
        </div>
      </div>
      <!-- Time grid -->
      <div class="grid grid-cols-[60px_repeat(7,1fr)]">
        <template v-for="hour in HOURS" :key="hour">
          <div class="text-right text-[10px] text-[var(--color-text-tertiary)] pr-2 py-2 border-r border-[var(--color-border)] h-12">
            {{ String(hour).padStart(2, '0') }}:00
          </div>
          <div
            v-for="day in weekDays"
            :key="`${hour}-${day.label}`"
            class="border-r border-b border-[var(--color-border)] h-12 relative cursor-pointer hover:bg-[var(--color-bg-hover)]"
            @click="onDateClick(day.date)"
          >
            <div
              v-for="event in getEventsForDate(day.date).filter(e => getEventHour(e) === hour)"
              :key="event.id"
              class="absolute inset-x-0.5 text-[9px] px-1 py-0.5 rounded truncate cursor-pointer flex items-center gap-0.5"
              :style="{ backgroundColor: eventColor(event) + '30', color: eventColor(event), borderLeft: `2px solid ${eventColor(event)}` }"
              @click.stop="onEventClick(event)"
            >
              <span
                class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                :style="{ backgroundColor: workflowColor(event.workflow_status) }"
              ></span>
              <span class="truncate">{{ event.title }}</span>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- DAY VIEW -->
    <div v-else class="calendar-day overflow-auto" style="max-height: 600px;">
      <div class="grid grid-cols-[60px_1fr]">
        <template v-for="hour in HOURS" :key="hour">
          <div class="text-right text-[10px] text-[var(--color-text-tertiary)] pr-2 py-2 border-r border-[var(--color-border)] h-14">
            {{ String(hour).padStart(2, '0') }}:00
          </div>
          <div
            class="border-b border-[var(--color-border)] h-14 relative cursor-pointer hover:bg-[var(--color-bg-hover)]"
            @click="onDateClick(currentDate)"
          >
            <div
              v-for="event in dayEvents.filter(e => getEventHour(e) === hour)"
              :key="event.id"
              class="flex items-center gap-2 text-xs px-2 py-1 rounded cursor-pointer mb-0.5"
              :style="{ backgroundColor: eventColor(event) + '15', color: eventColor(event), borderLeft: `3px solid ${eventColor(event)}` }"
              @click.stop="onEventClick(event)"
            >
              <span
                class="w-2 h-2 rounded-full flex-shrink-0"
                :style="{ backgroundColor: workflowColor(event.workflow_status) }"
              ></span>
              <span v-if="statusIcon(event.status)" class="font-bold">{{ statusIcon(event.status) }}</span>
              <span class="truncate font-medium">{{ event.title }}</span>
              <span v-if="event.product_name" class="text-[var(--color-text-tertiary)] truncate">— {{ event.product_name }}</span>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
