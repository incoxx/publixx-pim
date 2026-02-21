<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, Play, Clock, RotateCcw, XCircle, GitCompare } from 'lucide-vue-next'
import productVersionsApi from '@/api/productVersions'
import ProductVersionDiff from './ProductVersionDiff.vue'
import PimConfirmDialog from '@/components/shared/PimConfirmDialog.vue'

const props = defineProps({
  productId: { type: String, required: true },
})

const { t } = useI18n()

// State
const versions = ref([])
const loading = ref(false)
const selectedVersion = ref(null)
const compareLeft = ref(null)
const compareRight = ref(null)
const compareMode = ref(false)
const diffData = ref(null)
const diffLoading = ref(false)

// Create version dialog
const showCreateDialog = ref(false)
const createReason = ref('')
const creating = ref(false)

// Schedule dialog
const showScheduleDialog = ref(false)
const scheduleDate = ref('')
const scheduling = ref(false)
const scheduleTarget = ref(null)

// Confirm dialogs
const activateTarget = ref(null)
const activating = ref(false)
const revertTarget = ref(null)
const reverting = ref(false)
const cancelTarget = ref(null)
const cancelling = ref(false)

// Status config
const statusConfig = {
  current: { label: t('product.version.currentState'), class: 'bg-emerald-100 text-emerald-700' },
  draft: { label: t('product.version.draft'), class: 'bg-[var(--color-bg)] text-[var(--color-text-tertiary)]' },
  scheduled: { label: t('product.version.scheduled'), class: 'bg-blue-100 text-blue-700' },
  active: { label: t('product.version.active'), class: 'bg-[var(--color-success-light)] text-[var(--color-success)]' },
  archived: { label: t('product.version.archived'), class: 'bg-gray-100 text-gray-500' },
}

// Virtual "current state" entry for compare mode
const currentVersionEntry = computed(() => ({
  id: 'current',
  version_number: null,
  status: 'current',
  created_at: null,
  change_reason: null,
  creator: null,
  snapshot: null,
  _isCurrent: true,
}))

const compareableVersions = computed(() => {
  if (!compareMode.value) return versions.value
  return [currentVersionEntry.value, ...versions.value]
})

async function loadVersions() {
  loading.value = true
  try {
    const { data } = await productVersionsApi.list(props.productId, { perPage: 50 })
    versions.value = data.data || data
  } catch { /* silently fail */ }
  finally { loading.value = false }
}

async function createVersion() {
  creating.value = true
  try {
    await productVersionsApi.create(props.productId, {
      change_reason: createReason.value || null,
    })
    showCreateDialog.value = false
    createReason.value = ''
    await loadVersions()
  } catch { /* silently fail */ }
  finally { creating.value = false }
}

async function activateVersion() {
  activating.value = true
  try {
    await productVersionsApi.activate(props.productId, activateTarget.value.id)
    activateTarget.value = null
    await loadVersions()
  } catch { /* silently fail */ }
  finally { activating.value = false }
}

async function scheduleVersion() {
  scheduling.value = true
  try {
    await productVersionsApi.schedule(props.productId, scheduleTarget.value.id, {
      publish_at: scheduleDate.value,
    })
    showScheduleDialog.value = false
    scheduleDate.value = ''
    scheduleTarget.value = null
    await loadVersions()
  } catch { /* silently fail */ }
  finally { scheduling.value = false }
}

async function cancelScheduleVersion() {
  cancelling.value = true
  try {
    await productVersionsApi.cancelSchedule(props.productId, cancelTarget.value.id)
    cancelTarget.value = null
    await loadVersions()
  } catch { /* silently fail */ }
  finally { cancelling.value = false }
}

async function revertVersion() {
  reverting.value = true
  try {
    await productVersionsApi.revert(props.productId, revertTarget.value.id)
    revertTarget.value = null
    await loadVersions()
  } catch { /* silently fail */ }
  finally { reverting.value = false }
}

function toggleCompareMode() {
  compareMode.value = !compareMode.value
  if (!compareMode.value) {
    compareLeft.value = null
    compareRight.value = null
    diffData.value = null
  }
}

function toggleCompareSelection(version) {
  if (!compareLeft.value) {
    compareLeft.value = version
  } else if (compareLeft.value.id === version.id) {
    compareLeft.value = compareRight.value
    compareRight.value = null
    diffData.value = null
  } else if (compareRight.value?.id === version.id) {
    compareRight.value = null
    diffData.value = null
  } else {
    compareRight.value = version
  }
}

function isSelected(version) {
  return compareLeft.value?.id === version.id || compareRight.value?.id === version.id
}

async function loadDiff() {
  if (!compareLeft.value || !compareRight.value) return
  diffLoading.value = true
  try {
    let left = compareLeft.value
    let right = compareRight.value

    // Current state always goes on the right side
    if (left._isCurrent) {
      ;[left, right] = [right, left]
    } else if (!right._isCurrent && left.version_number > right.version_number) {
      // For two real versions, put the older one on the left
      ;[left, right] = [right, left]
    }

    const fromId = left._isCurrent ? 'current' : left.id
    const toId = right._isCurrent ? 'current' : right.id

    const { data } = await productVersionsApi.compare(props.productId, fromId, toId)
    diffData.value = data.data || data
  } catch { /* silently fail */ }
  finally { diffLoading.value = false }
}

watch([compareLeft, compareRight], () => {
  if (compareLeft.value && compareRight.value) {
    loadDiff()
  }
})

function openScheduleDialog(version) {
  scheduleTarget.value = version
  scheduleDate.value = ''
  showScheduleDialog.value = true
}

function selectVersion(version) {
  if (compareMode.value) {
    toggleCompareSelection(version)
  } else {
    selectedVersion.value = selectedVersion.value?.id === version.id ? null : version
  }
}

// Load on mount
loadVersions()
</script>

<template>
  <div class="space-y-3">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-medium text-[var(--color-text-primary)]">{{ t('product.versions') }}</h3>
      <div class="flex gap-2">
        <button
          :class="[
            'pim-btn text-xs',
            compareMode ? 'pim-btn-primary' : 'pim-btn-secondary',
          ]"
          @click="toggleCompareMode"
        >
          <GitCompare class="w-3.5 h-3.5" :stroke-width="1.75" />
          {{ t('product.version.compare') }}
        </button>
        <button class="pim-btn pim-btn-primary text-xs" @click="showCreateDialog = true">
          <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          {{ t('product.version.create') }}
        </button>
      </div>
    </div>

    <!-- Compare hint -->
    <div v-if="compareMode && (!compareLeft || !compareRight)" class="pim-card p-3 text-center">
      <p class="text-xs text-[var(--color-text-tertiary)]">
        {{ t('product.version.selectTwo') }}
        <span v-if="compareLeft" class="font-medium text-[var(--color-accent)]">
          ({{ compareLeft._isCurrent ? t('product.version.currentState') : 'Version ' + compareLeft.version_number }} ausgewählt — noch eine wählen)
        </span>
      </p>
    </div>

    <!-- Diff View -->
    <div v-if="compareMode && diffData && !diffLoading" class="pim-card p-4">
      <ProductVersionDiff :diffData="diffData" />
    </div>
    <div v-else-if="compareMode && diffLoading" class="pim-card p-6">
      <div class="pim-skeleton h-32 w-full rounded" />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="pim-skeleton h-16 w-full rounded-lg" />
    </div>

    <!-- Version Timeline -->
    <div v-else-if="versions.length > 0" class="space-y-2">
      <div
        v-for="version in (compareMode ? compareableVersions : versions)"
        :key="version.id"
        :class="[
          'pim-card p-3 cursor-pointer transition-all',
          (selectedVersion?.id === version.id || isSelected(version))
            ? 'ring-2 ring-[var(--color-accent)]'
            : 'hover:bg-[var(--color-bg)]',
        ]"
        @click="selectVersion(version)"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <!-- Version number -->
            <div class="flex items-center justify-center w-7 h-7 rounded-full bg-[var(--color-bg)] text-[11px] font-bold text-[var(--color-text-secondary)] shrink-0">
              <template v-if="version._isCurrent">
                <span class="text-emerald-600">&#9679;</span>
              </template>
              <template v-else>
                {{ version.version_number }}
              </template>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span :class="['pim-badge text-[10px] px-1.5 py-0.5', statusConfig[version.status]?.class]">
                  {{ statusConfig[version.status]?.label || version.status }}
                </span>
                <span class="text-[11px] text-[var(--color-text-tertiary)]">
                  {{ version.created_at ? new Date(version.created_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }}
                </span>
                <span v-if="version.creator?.name" class="text-[11px] text-[var(--color-text-tertiary)]">
                  · {{ version.creator.name }}
                </span>
              </div>
              <p v-if="version.change_reason" class="text-[12px] text-[var(--color-text-secondary)] mt-0.5 truncate">
                {{ version.change_reason }}
              </p>
              <p v-if="version.status === 'scheduled' && version.publish_at" class="text-[11px] text-blue-600 mt-0.5">
                {{ t('product.version.publishAt') }}: {{ new Date(version.publish_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
              </p>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="!compareMode" class="flex items-center gap-1 shrink-0" @click.stop>
            <button
              v-if="version.status === 'draft' || version.status === 'scheduled'"
              class="p-1 rounded hover:bg-[var(--color-success-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-success)] transition-colors"
              :title="t('product.version.activate')"
              @click="activateTarget = version"
            >
              <Play class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <button
              v-if="version.status === 'draft'"
              class="p-1 rounded hover:bg-blue-50 text-[var(--color-text-tertiary)] hover:text-blue-600 transition-colors"
              :title="t('product.version.schedule')"
              @click="openScheduleDialog(version)"
            >
              <Clock class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <button
              v-if="version.status === 'scheduled'"
              class="p-1 rounded hover:bg-[var(--color-error-light)] text-[var(--color-text-tertiary)] hover:text-[var(--color-error)] transition-colors"
              :title="t('product.version.cancelSchedule')"
              @click="cancelTarget = version"
            >
              <XCircle class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
            <button
              v-if="version.status === 'archived'"
              class="p-1 rounded hover:bg-amber-50 text-[var(--color-text-tertiary)] hover:text-amber-600 transition-colors"
              :title="t('product.version.revert')"
              @click="revertTarget = version"
            >
              <RotateCcw class="w-3.5 h-3.5" :stroke-width="2" />
            </button>
          </div>
        </div>

        <!-- Selected version detail -->
        <div v-if="selectedVersion?.id === version.id && !compareMode && !version._isCurrent" class="mt-3 pt-3 border-t border-[var(--color-border)]">
          <!-- Base fields -->
          <div class="grid grid-cols-2 gap-x-6 gap-y-2">
            <div v-for="[key, value] in Object.entries(version.snapshot || {}).filter(([k]) => k !== 'attributes')" :key="key">
              <span class="block text-[11px] text-[var(--color-text-tertiary)]">{{ key }}</span>
              <p class="text-[12px] text-[var(--color-text-primary)]" :class="key === 'sku' || key === 'ean' ? 'font-mono' : ''">
                {{ value ?? '—' }}
              </p>
            </div>
          </div>
          <!-- Attribute values -->
          <template v-if="version.snapshot?.attributes && Object.keys(version.snapshot.attributes).length > 0">
            <div class="mt-3 pt-2 border-t border-[var(--color-border)]">
              <span class="block text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wider mb-2">Attributwerte</span>
              <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                <div v-for="[attrKey, attrData] in Object.entries(version.snapshot.attributes)" :key="attrKey">
                  <span class="block text-[11px] text-[var(--color-text-tertiary)]">{{ attrData.label || attrKey }}</span>
                  <p class="text-[12px] text-[var(--color-text-primary)]">
                    {{ attrData.value ?? '—' }}
                  </p>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="pim-card p-12 text-center">
      <p class="text-sm text-[var(--color-text-tertiary)]">{{ t('product.version.noVersions') }}</p>
    </div>

    <!-- Create Version Dialog -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showCreateDialog" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showCreateDialog = false" />
          <div class="relative w-full max-w-[440px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 p-5">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">{{ t('product.version.create') }}</h3>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                {{ t('product.version.changeReason') }}
              </label>
              <textarea
                v-model="createReason"
                class="pim-input min-h-[80px]"
                :placeholder="t('product.version.changeReason') + '…'"
              />
            </div>
            <div class="flex justify-end gap-2 mt-4">
              <button class="pim-btn pim-btn-secondary text-xs" @click="showCreateDialog = false">
                {{ t('common.cancel') }}
              </button>
              <button class="pim-btn pim-btn-primary text-xs" :disabled="creating" @click="createVersion">
                {{ creating ? '…' : t('common.create') }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Schedule Dialog -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showScheduleDialog" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showScheduleDialog = false" />
          <div class="relative w-full max-w-[440px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl mx-4 p-5">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-3">{{ t('product.version.schedule') }}</h3>
            <div>
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                {{ t('product.version.publishAt') }}
              </label>
              <input type="datetime-local" class="pim-input" v-model="scheduleDate" />
            </div>
            <div class="flex justify-end gap-2 mt-4">
              <button class="pim-btn pim-btn-secondary text-xs" @click="showScheduleDialog = false">
                {{ t('common.cancel') }}
              </button>
              <button class="pim-btn pim-btn-primary text-xs" :disabled="scheduling || !scheduleDate" @click="scheduleVersion">
                {{ scheduling ? '…' : t('product.version.schedule') }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Confirm Dialogs -->
    <PimConfirmDialog
      :open="!!activateTarget"
      :title="t('product.version.activate') + '?'"
      :message="t('product.version.confirmActivate')"
      :loading="activating"
      @confirm="activateVersion"
      @cancel="activateTarget = null"
    />

    <PimConfirmDialog
      :open="!!revertTarget"
      :title="t('product.version.revert') + '?'"
      :message="t('product.version.confirmRevert')"
      :loading="reverting"
      @confirm="revertVersion"
      @cancel="revertTarget = null"
    />

    <PimConfirmDialog
      :open="!!cancelTarget"
      :title="t('product.version.cancelSchedule') + '?'"
      message="Die geplante Freigabe wird aufgehoben."
      :loading="cancelling"
      @confirm="cancelScheduleVersion"
      @cancel="cancelTarget = null"
    />
  </div>
</template>
