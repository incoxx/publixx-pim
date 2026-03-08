<script setup>
import { ref, watch } from 'vue'
import client from '@/api/client'
import { AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Löschen bestätigen' },
  message: { type: String, default: '' },
  entityType: { type: String, default: '' },
  entityId: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Löschen' },
  cancelLabel: { type: String, default: 'Abbrechen' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const dependencies = ref(null)
const loadingDeps = ref(false)
const hasDependencies = ref(false)
const fetchError = ref(false)

watch(() => props.open, async (isOpen) => {
  if (isOpen && props.entityType && props.entityId) {
    await fetchDependencies()
  } else if (!isOpen) {
    dependencies.value = null
    hasDependencies.value = false
    fetchError.value = false
  }
})

async function fetchDependencies() {
  loadingDeps.value = true
  fetchError.value = false
  try {
    const { data } = await client.get(`/${props.entityType}/${props.entityId}/dependencies`)
    dependencies.value = data.data
    hasDependencies.value = data.data?.has_dependencies || false
  } catch {
    dependencies.value = null
    hasDependencies.value = false
    fetchError.value = true
  } finally {
    loadingDeps.value = false
  }
}

function handleConfirm() {
  emit('confirm', { force: hasDependencies.value })
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="$emit('cancel')" />
        <div class="relative w-full max-w-[460px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl p-6 space-y-4 shadow-xl mx-4">
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ title }}</h3>
          <p class="text-xs text-[var(--color-text-tertiary)] leading-relaxed">{{ message }}</p>

          <!-- Loading dependencies -->
          <div v-if="loadingDeps" class="flex items-center gap-2 py-3">
            <div class="w-4 h-4 border-2 border-[var(--color-border)] border-t-[var(--color-primary)] rounded-full animate-spin" />
            <span class="text-xs text-[var(--color-text-tertiary)]">Abhängigkeiten werden geprüft…</span>
          </div>

          <!-- Fetch error -->
          <div v-else-if="fetchError" class="text-xs text-[var(--color-text-tertiary)] py-2">
            Abhängigkeiten konnten nicht geprüft werden. Sie können trotzdem fortfahren.
          </div>

          <!-- Dependencies warning -->
          <div
            v-else-if="hasDependencies && dependencies"
            class="bg-[var(--color-warning)]/8 border border-[var(--color-warning)]/30 rounded-lg p-3 space-y-2"
          >
            <div class="flex items-center gap-2">
              <AlertTriangle class="w-4 h-4 text-[var(--color-warning)] shrink-0" :stroke-width="2" />
              <span class="text-xs font-medium text-[var(--color-text-primary)]">
                {{ dependencies.total_count }} abhängige Datensätze gefunden
              </span>
            </div>
            <ul class="ml-6 space-y-1">
              <li
                v-for="(dep, key) in dependencies.dependencies"
                :key="key"
                class="text-xs text-[var(--color-text-secondary)] flex items-center gap-1.5"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--color-warning)] shrink-0" />
                <span>{{ dep.count }}× {{ dep.label }}</span>
              </li>
            </ul>
            <p class="text-[11px] text-[var(--color-text-tertiary)] ml-6">
              Beim Löschen werden diese Daten entfernt oder die Verknüpfung aufgehoben.
            </p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              class="pim-btn pim-btn-secondary text-xs"
              :disabled="loading || loadingDeps"
              @click="$emit('cancel')"
            >
              {{ cancelLabel }}
            </button>
            <button
              class="pim-btn text-xs bg-[var(--color-error)] text-white hover:bg-[var(--color-error)]/90"
              :disabled="loading || loadingDeps"
              @click="handleConfirm"
            >
              <span v-if="loading">Bitte warten…</span>
              <span v-else-if="hasDependencies">Trotzdem löschen</span>
              <span v-else>{{ confirmLabel }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
