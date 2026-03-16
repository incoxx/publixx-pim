<script setup>
import { useApiDesignerStore } from '@/stores/apiDesigner'
import { Plus, Trash2, ChevronDown, ChevronRight, GripVertical } from 'lucide-vue-next'
import { ref } from 'vue'
import ApiTreeSection from './ApiTreeSection.vue'

const emit = defineEmits(['show-field-picker'])
const store = useApiDesignerStore()
const expandedGroups = ref({})
const expandedSections = ref({})

function toggleGroup(id) {
  expandedGroups.value[id] = !expandedGroups.value[id]
}

function toggleSection(key) {
  expandedSections.value[key] = !expandedSections.value[key]
}

function isExpanded(id) {
  return expandedGroups.value[id] !== false
}

function isSectionExpanded(key) {
  return expandedSections.value[key] !== false
}

function getGroupFieldLabel(field) {
  const labels = {
    product_type: 'Produkttyp',
    master_hierarchy_node: 'Hierarchieknoten',
    status: 'Status',
    none: 'Keine Gruppierung',
  }
  if (field?.startsWith('attribute:')) return 'Attribut-Gruppierung'
  return labels[field] || field || '—'
}

function getGroupDepth(groups, id, depth = 0) {
  for (const g of groups) {
    if (g.id === id) return depth
    if (g.groups?.length) {
      const d = getGroupDepth(g.groups, id, depth + 1)
      if (d >= 0) return d
    }
  }
  return -1
}

function canAddSubgroup(groupId) {
  return getGroupDepth(store.templateJson.groups, groupId) < 2
}

function getSectionLabel(sec) {
  return { header: 'Header (Meta)', detail: 'Detail (pro Produkt)', footer: 'Footer (Zusammenfassung)' }[sec] || sec
}

function renderGroupTree(groups, depth = 0) {
  return groups
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between mb-2">
      <span class="text-xs font-semibold text-[var(--color-text-primary)]">JSON-Struktur</span>
      <div class="flex items-center gap-1.5">
        <button
          class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
          @click="emit('show-field-picker')"
        >
          <Plus class="w-3 h-3" :stroke-width="2" />
          Feld
        </button>
        <button
          class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
          @click="store.addGroup()"
        >
          <Plus class="w-3 h-3" :stroke-width="2" />
          Gruppe
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!store.templateJson.groups?.length" class="pim-card p-6 text-center">
      <p class="text-xs text-[var(--color-text-tertiary)]">
        Keine Gruppen definiert. Füge eine Gruppe hinzu, um die JSON-Struktur zu gestalten.
      </p>
    </div>

    <!-- Group Tree (recursive rendering) -->
    <template v-for="group in store.templateJson.groups" :key="group.id">
      <div class="pim-card overflow-hidden" :class="store.selectedGroupId === group.id ? 'ring-2 ring-[var(--color-accent)]' : ''">
        <!-- Group Header Bar -->
        <div
          class="flex items-center gap-2 px-3 py-2 bg-[var(--color-surface)] cursor-pointer hover:bg-[var(--color-bg)]"
          @click="store.selectGroup(group.id)"
        >
          <button @click.stop="toggleGroup(group.id)" class="shrink-0">
            <ChevronDown v-if="isExpanded(group.id)" class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
            <ChevronRight v-else class="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" :stroke-width="2" />
          </button>

          <span class="text-xs font-medium text-[var(--color-text-primary)] flex-1">
            {{ group.label || 'Gruppe' }}
          </span>
          <span class="text-[10px] text-[var(--color-text-tertiary)] bg-[var(--color-bg)] px-1.5 py-0.5 rounded">
            {{ getGroupFieldLabel(group.field) }}
          </span>

          <!-- Inline edit: Group label -->
          <input
            v-if="store.selectedGroupId === group.id"
            :value="group.label"
            class="pim-input text-[11px] w-28"
            placeholder="Label"
            @click.stop
            @input="store.updateGroup(group.id, { label: $event.target.value })"
          />

          <!-- Inline edit: Group field -->
          <select
            v-if="store.selectedGroupId === group.id"
            :value="group.field"
            class="pim-input text-[11px] w-28"
            @click.stop
            @change="store.updateGroup(group.id, { field: $event.target.value })"
          >
            <option v-for="gf in (store.availableFields?.group_fields || [])" :key="gf.field" :value="gf.field">{{ gf.label_de }}</option>
          </select>

          <button
            v-if="canAddSubgroup(group.id)"
            class="text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)]"
            @click.stop="store.addGroup(group.id)"
            title="Untergruppe hinzufügen"
          >
            <Plus class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
          <button
            class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
            @click.stop="store.removeGroup(group.id)"
            title="Gruppe löschen"
          >
            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
          </button>
        </div>

        <!-- Group Content (Sections) -->
        <div v-if="isExpanded(group.id)" class="px-3 py-2 space-y-2 border-t border-[var(--color-border)]">
          <div v-for="sec in ['header', 'detail', 'footer']" :key="sec">
            <button
              class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide w-full text-left py-0.5"
              @click="toggleSection(`${group.id}-${sec}`)"
            >
              {{ isSectionExpanded(`${group.id}-${sec}`) ? '▾' : '▸' }} {{ getSectionLabel(sec) }}
              <span class="text-[var(--color-text-tertiary)] font-normal">({{ group[sec]?.elements?.length || 0 }})</span>
            </button>
            <ApiTreeSection
              v-if="isSectionExpanded(`${group.id}-${sec}`)"
              :group-id="group.id"
              :section="sec"
              :elements="group[sec]?.elements || []"
            />
          </div>

          <!-- Subgroups (recursive) -->
          <template v-if="group.groups?.length">
            <div class="pl-4 border-l-2 border-[var(--color-border)] space-y-2 mt-2">
              <template v-for="sub in group.groups" :key="sub.id">
                <div class="pim-card overflow-hidden" :class="store.selectedGroupId === sub.id ? 'ring-2 ring-[var(--color-accent)]' : ''">
                  <div
                    class="flex items-center gap-2 px-3 py-1.5 bg-[var(--color-surface)] cursor-pointer hover:bg-[var(--color-bg)]"
                    @click="store.selectGroup(sub.id)"
                  >
                    <button @click.stop="toggleGroup(sub.id)" class="shrink-0">
                      <ChevronDown v-if="isExpanded(sub.id)" class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                      <ChevronRight v-else class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                    </button>
                    <span class="text-[11px] font-medium text-[var(--color-text-primary)] flex-1">{{ sub.label || 'Untergruppe' }}</span>
                    <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ getGroupFieldLabel(sub.field) }}</span>
                    <button
                      v-if="canAddSubgroup(sub.id)"
                      class="text-[var(--color-text-tertiary)] hover:text-[var(--color-accent)]"
                      @click.stop="store.addGroup(sub.id)"
                      title="Untergruppe hinzufügen"
                    >
                      <Plus class="w-3 h-3" :stroke-width="2" />
                    </button>
                    <button
                      class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
                      @click.stop="store.removeGroup(sub.id)"
                    >
                      <Trash2 class="w-3 h-3" :stroke-width="2" />
                    </button>
                  </div>
                  <div v-if="isExpanded(sub.id)" class="px-3 py-2 space-y-2 border-t border-[var(--color-border)]">
                    <div v-for="sec in ['header', 'detail', 'footer']" :key="sec">
                      <button
                        class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide w-full text-left py-0.5"
                        @click="toggleSection(`${sub.id}-${sec}`)"
                      >
                        {{ isSectionExpanded(`${sub.id}-${sec}`) ? '▾' : '▸' }} {{ getSectionLabel(sec) }}
                        <span class="text-[var(--color-text-tertiary)] font-normal">({{ sub[sec]?.elements?.length || 0 }})</span>
                      </button>
                      <ApiTreeSection
                        v-if="isSectionExpanded(`${sub.id}-${sec}`)"
                        :group-id="sub.id"
                        :section="sec"
                        :elements="sub[sec]?.elements || []"
                      />
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>
