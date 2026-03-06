<script setup>
import { useReportDesignerStore } from '@/stores/reportDesigner'
import { Plus, Trash2, ChevronDown, ChevronRight } from 'lucide-vue-next'
import { ref } from 'vue'
import GroupSection from './GroupSection.vue'

const store = useReportDesignerStore()
const expandedGroups = ref({})
const expandedSections = ref({})

function toggleGroup(id) {
  expandedGroups.value[id] = !expandedGroups.value[id]
}

function toggleSection(key) {
  expandedSections.value[key] = !expandedSections.value[key]
}

function isExpanded(id) {
  return expandedGroups.value[id] !== false // default expanded
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
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between mb-2">
      <span class="text-xs font-semibold text-[var(--color-text-primary)]">Gruppen & Sektionen</span>
      <button
        class="pim-btn pim-btn-secondary text-[11px] px-2 py-1"
        @click="store.addGroup()"
      >
        <Plus class="w-3 h-3" :stroke-width="2" />
        Gruppe
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!store.templateJson.groups?.length" class="pim-card p-6 text-center">
      <p class="text-xs text-[var(--color-text-tertiary)]">
        Keine Gruppen definiert. Füge eine Gruppe hinzu, um den Report zu strukturieren.
      </p>
    </div>

    <!-- Group Tree -->
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
          <!-- Header Section -->
          <div>
            <button
              class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide w-full text-left py-0.5"
              @click="toggleSection(`${group.id}-header`)"
            >
              {{ isSectionExpanded(`${group.id}-header`) ? '▾' : '▸' }} Header
              <span class="text-[var(--color-text-tertiary)] font-normal">({{ group.header?.elements?.length || 0 }})</span>
            </button>
            <GroupSection
              v-if="isSectionExpanded(`${group.id}-header`)"
              :group-id="group.id"
              section="header"
              :elements="group.header?.elements || []"
            />
          </div>

          <!-- Detail Section -->
          <div>
            <button
              class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide w-full text-left py-0.5"
              @click="toggleSection(`${group.id}-detail`)"
            >
              {{ isSectionExpanded(`${group.id}-detail`) ? '▾' : '▸' }} Detail (pro Produkt)
              <span class="text-[var(--color-text-tertiary)] font-normal">({{ group.detail?.elements?.length || 0 }})</span>
            </button>
            <GroupSection
              v-if="isSectionExpanded(`${group.id}-detail`)"
              :group-id="group.id"
              section="detail"
              :elements="group.detail?.elements || []"
            />
          </div>

          <!-- Footer Section -->
          <div>
            <button
              class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide w-full text-left py-0.5"
              @click="toggleSection(`${group.id}-footer`)"
            >
              {{ isSectionExpanded(`${group.id}-footer`) ? '▾' : '▸' }} Footer
              <span class="text-[var(--color-text-tertiary)] font-normal">({{ group.footer?.elements?.length || 0 }})</span>
            </button>
            <GroupSection
              v-if="isSectionExpanded(`${group.id}-footer`)"
              :group-id="group.id"
              section="footer"
              :elements="group.footer?.elements || []"
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
                        {{ isSectionExpanded(`${sub.id}-${sec}`) ? '▾' : '▸' }} {{ sec === 'detail' ? 'Detail (pro Produkt)' : sec.charAt(0).toUpperCase() + sec.slice(1) }}
                        <span class="text-[var(--color-text-tertiary)] font-normal">({{ sub[sec]?.elements?.length || 0 }})</span>
                      </button>
                      <GroupSection
                        v-if="isSectionExpanded(`${sub.id}-${sec}`)"
                        :group-id="sub.id"
                        :section="sec"
                        :elements="sub[sec]?.elements || []"
                      />
                    </div>
                    <!-- Recursive subgroups -->
                    <template v-if="sub.groups?.length">
                      <div class="pl-4 border-l-2 border-[var(--color-border)] space-y-2 mt-2">
                        <template v-for="sub2 in sub.groups" :key="sub2.id">
                          <div class="pim-card overflow-hidden" :class="store.selectedGroupId === sub2.id ? 'ring-2 ring-[var(--color-accent)]' : ''">
                            <div
                              class="flex items-center gap-2 px-3 py-1.5 bg-[var(--color-surface)] cursor-pointer hover:bg-[var(--color-bg)]"
                              @click="store.selectGroup(sub2.id)"
                            >
                              <button @click.stop="toggleGroup(sub2.id)" class="shrink-0">
                                <ChevronDown v-if="isExpanded(sub2.id)" class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                                <ChevronRight v-else class="w-3 h-3 text-[var(--color-text-tertiary)]" :stroke-width="2" />
                              </button>
                              <span class="text-[11px] font-medium text-[var(--color-text-primary)] flex-1">{{ sub2.label || 'Untergruppe' }}</span>
                              <span class="text-[10px] text-[var(--color-text-tertiary)]">{{ getGroupFieldLabel(sub2.field) }}</span>
                              <button
                                class="text-[var(--color-text-tertiary)] hover:text-[var(--color-error)]"
                                @click.stop="store.removeGroup(sub2.id)"
                              >
                                <Trash2 class="w-3 h-3" :stroke-width="2" />
                              </button>
                            </div>
                            <div v-if="isExpanded(sub2.id)" class="px-3 py-2 space-y-2 border-t border-[var(--color-border)]">
                              <div v-for="sec in ['header', 'detail', 'footer']" :key="sec">
                                <div class="text-[10px] font-semibold text-[var(--color-text-tertiary)] uppercase tracking-wide py-0.5">
                                  {{ sec === 'detail' ? 'Detail (pro Produkt)' : sec.charAt(0).toUpperCase() + sec.slice(1) }}
                                </div>
                                <GroupSection :group-id="sub2.id" :section="sec" :elements="sub2[sec]?.elements || []" />
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
        </div>
      </div>
    </template>
  </div>
</template>
