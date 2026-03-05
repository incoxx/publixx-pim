<script setup>
import { ref, computed } from 'vue'
import { Save, Trash2, FolderOpen, Share2, Pencil, Plus } from 'lucide-vue-next'

const props = defineProps({
  profiles: { type: Array, default: () => [] },
  modelValue: { type: String, default: null },
  canSave: { type: Boolean, default: true },
  canDelete: { type: Boolean, default: true },
  label: { type: String, default: 'Profil' },
})

const emit = defineEmits(['update:modelValue', 'load', 'save', 'update', 'delete'])

const showSaveDialog = ref(false)
const saveName = ref('')
const saveShared = ref(false)
const saveMode = ref('create') // 'create' | 'update'

const selectedProfile = computed(() =>
  props.profiles.find(p => p.id === props.modelValue) || null
)

function onSelect(e) {
  const id = e.target.value
  emit('update:modelValue', id || null)
  if (id) {
    emit('load', id)
  }
}

function openSaveAsNew() {
  saveMode.value = 'create'
  saveName.value = ''
  saveShared.value = false
  showSaveDialog.value = true
}

function openUpdateDialog() {
  saveMode.value = 'update'
  saveName.value = selectedProfile.value?.name || ''
  saveShared.value = selectedProfile.value?.is_shared || false
  showSaveDialog.value = true
}

function confirmSave() {
  if (!saveName.value.trim()) return
  if (saveMode.value === 'update' && props.modelValue) {
    emit('update', { id: props.modelValue, name: saveName.value.trim(), is_shared: saveShared.value })
  } else {
    emit('save', { name: saveName.value.trim(), is_shared: saveShared.value })
  }
  showSaveDialog.value = false
}

function deleteProfile() {
  if (!props.modelValue) return
  emit('delete', props.modelValue)
}
</script>

<template>
  <div class="flex items-center gap-2">
    <div class="relative flex-1 min-w-0">
      <FolderOpen class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
      <select
        class="pim-input text-xs pl-8 pr-3 py-1.5 w-full"
        :value="modelValue"
        @change="onSelect"
      >
        <option value="">— {{ label }} wählen —</option>
        <option
          v-for="p in profiles"
          :key="p.id"
          :value="p.id"
        >
          {{ p.name }}{{ p.is_shared ? ' (geteilt)' : '' }}
        </option>
      </select>
    </div>

    <!-- Update existing profile -->
    <button
      v-if="canSave && modelValue"
      class="pim-btn pim-btn-secondary text-xs px-2 py-1.5"
      title="Profil aktualisieren"
      @click="openUpdateDialog"
    >
      <Save class="w-3.5 h-3.5" :stroke-width="1.75" />
    </button>

    <!-- Save as new -->
    <button
      v-if="canSave"
      class="pim-btn pim-btn-secondary text-xs px-2 py-1.5"
      title="Als neues Profil speichern"
      @click="openSaveAsNew"
    >
      <Plus class="w-3.5 h-3.5" :stroke-width="1.75" />
    </button>

    <button
      v-if="canDelete && modelValue"
      class="pim-btn pim-btn-secondary text-xs px-2 py-1.5 text-[var(--color-error)]"
      title="Profil löschen"
      @click="deleteProfile"
    >
      <Trash2 class="w-3.5 h-3.5" :stroke-width="1.75" />
    </button>

    <!-- Save Dialog -->
    <Teleport to="body">
      <transition name="fade">
        <div v-if="showSaveDialog" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showSaveDialog = false" />
          <div class="relative bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl shadow-xl p-5 w-80 space-y-4">
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
              {{ saveMode === 'update' ? `${label} aktualisieren` : `${label} speichern` }}
            </h3>
            <div>
              <label class="block text-[11px] font-medium text-[var(--color-text-secondary)] mb-1">Name</label>
              <input
                v-model="saveName"
                class="pim-input text-xs w-full"
                :placeholder="saveMode === 'update' ? 'Profilname ändern...' : 'Profilname eingeben...'"
                @keydown.enter="confirmSave"
                autofocus
              />
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="saveShared" class="rounded border-[var(--color-border-strong)]" />
              <Share2 class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="1.75" />
              <span class="text-xs text-[var(--color-text-secondary)]">Für alle Benutzer freigeben</span>
            </label>
            <div class="flex justify-end gap-2">
              <button class="pim-btn pim-btn-secondary text-xs" @click="showSaveDialog = false">Abbrechen</button>
              <button class="pim-btn pim-btn-primary text-xs" :disabled="!saveName.trim()" @click="confirmSave">
                {{ saveMode === 'update' ? 'Aktualisieren' : 'Speichern' }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>
