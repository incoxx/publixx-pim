<script setup>
import { ref, watch, computed } from 'vue'
import { X } from 'lucide-vue-next'
import PimAttributeInput from './PimAttributeInput.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  compositeAttribute: { type: Object, default: null },
  modelValue: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
  mapType: { type: Function, default: (t) => t },
})

const emit = defineEmits(['update:open', 'update:modelValue'])

// Local copy of values for editing — committed on "Übernehmen"
const localValues = ref({})

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    localValues.value = { ...props.modelValue }
  }
})

const children = computed(() => {
  if (!props.compositeAttribute) return []
  return props.compositeAttribute.children || []
})

const title = computed(() => {
  if (!props.compositeAttribute) return ''
  return props.compositeAttribute.name_de || props.compositeAttribute.technical_name || 'Composite'
})

function close() {
  emit('update:open', false)
}

function apply() {
  emit('update:modelValue', { ...localValues.value })
  close()
}

function onKeydown(e) {
  if (e.key === 'Escape') close()
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open && compositeAttribute"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]"
        @keydown="onKeydown"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <!-- Panel -->
        <div class="relative w-full max-w-[600px] bg-[var(--color-surface)] rounded-xl shadow-xl border border-[var(--color-border)] overflow-hidden mx-4">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-[var(--color-border)]">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ title }}</h3>
            <button
              class="p-1 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-tertiary)] transition-colors"
              @click="close"
            >
              <X class="w-4 h-4" :stroke-width="2" />
            </button>
          </div>

          <!-- Body -->
          <div class="px-5 py-4 space-y-3 max-h-[50vh] overflow-y-auto">
            <div v-for="child in children" :key="child.id">
              <label class="block text-[12px] font-medium text-[var(--color-text-secondary)] mb-1">
                {{ child.name_de || child.technical_name }}
                <span v-if="child.is_mandatory" class="text-[var(--color-error)]">*</span>
              </label>
              <PimAttributeInput
                :type="mapType(child.data_type)"
                :modelValue="localValues[child.id]"
                :disabled="disabled"
                @update:modelValue="localValues[child.id] = $event"
              />
            </div>
            <p v-if="children.length === 0" class="text-sm text-[var(--color-text-tertiary)] text-center py-4">
              Keine Kind-Attribute definiert
            </p>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[var(--color-border)]">
            <button class="pim-btn pim-btn-secondary text-xs" @click="close">Abbrechen</button>
            <button class="pim-btn pim-btn-primary text-xs" :disabled="disabled" @click="apply">
              Übernehmen
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
