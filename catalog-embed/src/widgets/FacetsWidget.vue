<script setup>
import { ref, onMounted, watch, defineProps } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const props = defineProps({
  mode: { type: String, default: 'inline' }, // 'inline' or 'drawer'
})

const { state, actions, getters } = useStore()

const collapsed = ref({})
const expanded = ref({})
const searchText = ref({})
const rangeLocal = ref({})
let rangeTimers = {}

const VISIBLE_COUNT = 5

const drawerOpen = ref(false)
function openDrawer() { drawerOpen.value = true }
function closeDrawer() { drawerOpen.value = false }
function applyAndClose() { drawerOpen.value = false }

onMounted(() => {
  if (state.facets.length === 0) actions.fetchFacets()
})

watch(() => state.locale, () => actions.fetchFacets())
watch(() => state.selectedCategoryId, () => actions.fetchFacets())

function toggle(id) { collapsed.value[id] = !collapsed.value[id] }
function isCollapsed(id) { return !!collapsed.value[id] }
function toggleExpand(id) { expanded.value[id] = !expanded.value[id] }
function isExpanded(id) { return !!expanded.value[id] }

// ValueList helpers
function getSelected(attrId) {
  const raw = state.activeFilters[attrId]
  if (!raw) return []
  return String(raw).split(',').filter(Boolean)
}

function toggleValue(attrId, valId) {
  const current = getSelected(attrId)
  const idx = current.indexOf(String(valId))
  if (idx === -1) current.push(String(valId))
  else current.splice(idx, 1)
  if (current.length === 0) actions.clearFilter(attrId)
  else actions.setFilter(attrId, current.join(','))
  actions.fetchProducts()
  actions.fetchFacets()
}

function isSelected(attrId, valId) {
  return getSelected(attrId).includes(String(valId))
}

// Boolean
function toggleBool(attrId) {
  if (state.activeFilters[attrId] === '1') actions.clearFilter(attrId)
  else actions.setFilter(attrId, '1')
  actions.fetchProducts()
  actions.fetchFacets()
}

// Range
function getRangeVal(attrId) {
  const raw = state.activeFilters[attrId]
  if (!raw) return { min: '', max: '' }
  const parts = String(raw).split(':')
  return { min: parts[0] || '', max: parts[1] || '' }
}

function applyRange(attrId, range) {
  if (!range.min && !range.max) actions.clearFilter(attrId)
  else actions.setFilter(attrId, `${range.min}:${range.max}`)
  actions.fetchProducts()
  actions.fetchFacets()
}

function setMin(attrId, val) { applyRange(attrId, { ...getRangeVal(attrId), min: val }) }
function setMax(attrId, val) { applyRange(attrId, { ...getRangeVal(attrId), max: val }) }

// Filtered/visible values
function filteredValues(facet) {
  const vals = facet.values || []
  const q = (searchText.value[facet.attribute_id] || '').toLowerCase()
  if (!q) return vals
  return vals.filter(v => v.value.toLowerCase().includes(q))
}

function visibleValues(facet) {
  const filtered = filteredValues(facet)
  if (isExpanded(facet.attribute_id)) return filtered
  return filtered.slice(0, VISIBLE_COUNT)
}

function hiddenCount(facet) {
  return filteredValues(facet).length - VISIBLE_COUNT
}

function facetCount(attrId) {
  return getSelected(attrId).length
}

function clearAll() {
  actions.clearAllFilters()
  actions.fetchProducts()
  actions.fetchFacets()
}
</script>

<template>
  <!-- ===== DRAWER MODE ===== -->
  <template v-if="props.mode === 'drawer'">
    <!-- Trigger button -->
    <button v-if="state.facets.length" class="pxc-facets-trigger" @click="openDrawer">
      <span v-html="icons.filter || icons.search"></span>
      <span>Filtern</span>
      <span v-if="getters.activeFilterCount.value > 0" class="pxc-facets-trigger__badge">{{ getters.activeFilterCount.value }}</span>
    </button>

    <!-- Overlay + Drawer -->
    <Teleport to="body">
      <transition name="pxc-fade">
        <div v-if="drawerOpen" class="pxc-facets-drawer__overlay" @click="closeDrawer"></div>
      </transition>
      <div class="pxc-facets-drawer" :class="{ 'pxc-facets-drawer--open': drawerOpen }">
        <!-- Drawer header -->
        <div class="pxc-facets-drawer__header">
          <span class="pxc-facets-drawer__title">Produktfilter</span>
          <button class="pxc-facets-drawer__close" @click="closeDrawer">
            <span v-html="icons.close"></span>
          </button>
        </div>

        <!-- Drawer body: facet groups -->
        <div class="pxc-facets-drawer__body">
          <div v-if="state.facets.length" class="pxc-facets">
            <div v-for="facet in state.facets" :key="facet.attribute_id" class="pxc-facets__group">
              <button class="pxc-facets__group-header" @click="toggle(facet.attribute_id)">
                <span v-html="isCollapsed(facet.attribute_id) ? icons.chevronRight : icons.chevronDown"></span>
                <span class="pxc-facets__group-label">{{ facet.label }}</span>
                <span v-if="facetCount(facet.attribute_id) > 0" class="pxc-facets__badge">{{ facetCount(facet.attribute_id) }}</span>
              </button>

              <div v-show="!isCollapsed(facet.attribute_id)" class="pxc-facets__body">
                <!-- ValueList / Text -->
                <template v-if="facet.data_type === 'ValueList' || facet.data_type === 'Text'">
                  <div v-if="(facet.values || []).length > 8" class="pxc-facets__search">
                    <input
                      v-model="searchText[facet.attribute_id]"
                      type="text"
                      placeholder="Suchen..."
                      class="pxc-facets__search-input"
                    />
                  </div>
                  <label
                    v-for="val in visibleValues(facet)"
                    :key="val.value_id || val.value"
                    class="pxc-facets__checkbox"
                  >
                    <input
                      type="checkbox"
                      :checked="isSelected(facet.attribute_id, val.value_id || val.value)"
                      @change="toggleValue(facet.attribute_id, val.value_id || val.value)"
                    />
                    <span class="pxc-facets__checkbox-label">{{ val.value }}</span>
                    <span class="pxc-facets__checkbox-count">{{ val.count }}</span>
                  </label>
                  <button
                    v-if="hiddenCount(facet) > 0 && !isExpanded(facet.attribute_id)"
                    class="pxc-facets__show-more"
                    @click="toggleExpand(facet.attribute_id)"
                  >Mehr anzeigen (+{{ hiddenCount(facet) }})</button>
                  <button
                    v-else-if="isExpanded(facet.attribute_id) && (facet.values || []).length > VISIBLE_COUNT"
                    class="pxc-facets__show-more"
                    @click="toggleExpand(facet.attribute_id)"
                  >Weniger anzeigen</button>
                </template>

                <!-- Boolean -->
                <template v-else-if="facet.data_type === 'Boolean'">
                  <label class="pxc-facets__toggle">
                    <input
                      type="checkbox"
                      :checked="state.activeFilters[facet.attribute_id] === '1'"
                      @change="toggleBool(facet.attribute_id)"
                    />
                    <span>{{ facet.label }}</span>
                  </label>
                </template>

                <!-- Decimal / Integer -->
                <template v-else-if="facet.data_type === 'Decimal' || facet.data_type === 'Integer'">
                  <div class="pxc-facets__range">
                    <div class="pxc-facets__range-field">
                      <label>Von</label>
                      <input
                        type="number"
                        :placeholder="facet.min != null ? String(facet.min) : ''"
                        :value="getRangeVal(facet.attribute_id).min"
                        @change="setMin(facet.attribute_id, $event.target.value)"
                      />
                    </div>
                    <span class="pxc-facets__range-sep">&ndash;</span>
                    <div class="pxc-facets__range-field">
                      <label>Bis</label>
                      <input
                        type="number"
                        :placeholder="facet.max != null ? String(facet.max) : ''"
                        :value="getRangeVal(facet.attribute_id).max"
                        @change="setMax(facet.attribute_id, $event.target.value)"
                      />
                    </div>
                    <span v-if="facet.unit" class="pxc-facets__range-unit">{{ facet.unit }}</span>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Drawer footer -->
        <div class="pxc-facets-drawer__footer">
          <button class="pxc-btn pxc-btn--outline" @click="clearAll(); closeDrawer()">Abbrechen</button>
          <button class="pxc-btn pxc-btn--primary" @click="applyAndClose">Anwenden</button>
        </div>
      </div>
    </Teleport>
  </template>

  <!-- ===== INLINE MODE (default) ===== -->
  <div v-else-if="state.facets.length" class="pxc-facets">
    <div class="pxc-facets__header">
      <span class="pxc-facets__title">Filter</span>
      <button
        v-if="getters.activeFilterCount.value > 0"
        class="pxc-facets__clear-all"
        @click="clearAll"
      >Alle zurücksetzen</button>
    </div>

    <div v-for="facet in state.facets" :key="facet.attribute_id" class="pxc-facets__group">
      <!-- Facet header -->
      <button class="pxc-facets__group-header" @click="toggle(facet.attribute_id)">
        <span v-html="isCollapsed(facet.attribute_id) ? icons.chevronRight : icons.chevronDown"></span>
        <span class="pxc-facets__group-label">{{ facet.label }}</span>
        <span v-if="facetCount(facet.attribute_id) > 0" class="pxc-facets__badge">{{ facetCount(facet.attribute_id) }}</span>
      </button>

      <div v-show="!isCollapsed(facet.attribute_id)" class="pxc-facets__body">
        <!-- ValueList / Text -->
        <template v-if="facet.data_type === 'ValueList' || facet.data_type === 'Text'">
          <div v-if="(facet.values || []).length > 8" class="pxc-facets__search">
            <input
              v-model="searchText[facet.attribute_id]"
              type="text"
              placeholder="Suchen..."
              class="pxc-facets__search-input"
            />
          </div>
          <label
            v-for="val in visibleValues(facet)"
            :key="val.value_id || val.value"
            class="pxc-facets__checkbox"
          >
            <input
              type="checkbox"
              :checked="isSelected(facet.attribute_id, val.value_id || val.value)"
              @change="toggleValue(facet.attribute_id, val.value_id || val.value)"
            />
            <span class="pxc-facets__checkbox-label">{{ val.value }}</span>
            <span class="pxc-facets__checkbox-count">{{ val.count }}</span>
          </label>
          <button
            v-if="hiddenCount(facet) > 0 && !isExpanded(facet.attribute_id)"
            class="pxc-facets__show-more"
            @click="toggleExpand(facet.attribute_id)"
          >Mehr anzeigen (+{{ hiddenCount(facet) }})</button>
          <button
            v-else-if="isExpanded(facet.attribute_id) && (facet.values || []).length > VISIBLE_COUNT"
            class="pxc-facets__show-more"
            @click="toggleExpand(facet.attribute_id)"
          >Weniger anzeigen</button>
        </template>

        <!-- Boolean -->
        <template v-else-if="facet.data_type === 'Boolean'">
          <label class="pxc-facets__toggle">
            <input
              type="checkbox"
              :checked="state.activeFilters[facet.attribute_id] === '1'"
              @change="toggleBool(facet.attribute_id)"
            />
            <span>{{ facet.label }}</span>
          </label>
        </template>

        <!-- Decimal / Integer -->
        <template v-else-if="facet.data_type === 'Decimal' || facet.data_type === 'Integer'">
          <div class="pxc-facets__range">
            <div class="pxc-facets__range-field">
              <label>Von</label>
              <input
                type="number"
                :placeholder="facet.min != null ? String(facet.min) : ''"
                :value="getRangeVal(facet.attribute_id).min"
                @change="setMin(facet.attribute_id, $event.target.value)"
              />
            </div>
            <span class="pxc-facets__range-sep">&ndash;</span>
            <div class="pxc-facets__range-field">
              <label>Bis</label>
              <input
                type="number"
                :placeholder="facet.max != null ? String(facet.max) : ''"
                :value="getRangeVal(facet.attribute_id).max"
                @change="setMax(facet.attribute_id, $event.target.value)"
              />
            </div>
            <span v-if="facet.unit" class="pxc-facets__range-unit">{{ facet.unit }}</span>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
