<script setup>
/**
 * FilterStepsWidget — rendert ALLE konfigurierten Filter-Steps automatisch.
 *
 * Verwendung im HTML-Template:
 *   <div data-portal="filter-steps"></div>
 *
 * Iteriert ueber state.filterValues in der konfigurierten Reihenfolge
 * und rendert je nach widget-Typ die passende Komponente.
 */
import { computed } from 'vue'
import { useStore } from '../store.js'

import CountrySelectWidget from './CountrySelectWidget.vue'
import LanguageSelectWidget from './LanguageSelectWidget.vue'
import FilterDropdownWidget from './FilterDropdownWidget.vue'
import FilterCardsWidget from './FilterCardsWidget.vue'

const WIDGET_COMPONENTS = {
  'country-select': CountrySelectWidget,
  'language-select': LanguageSelectWidget,
  'filter-dropdown': FilterDropdownWidget,
  'filter-cards': FilterCardsWidget,
}

const { state } = useStore()

const steps = computed(() =>
  (state.filterValues || []).map(step => ({
    ...step,
    component: WIDGET_COMPONENTS[step.widget] || FilterDropdownWidget,
  }))
)
</script>

<template>
  <!-- Loading -->
  <div v-if="state.loading" class="pe-filter-steps">
    <div class="pe-skeleton" style="height:48px;margin-bottom:12px"></div>
    <div class="pe-skeleton" style="height:48px;margin-bottom:12px"></div>
  </div>
  <!-- Content -->
  <div v-else class="pe-filter-steps">
    <component
      v-for="step in steps"
      :key="step.attribute_id || step.key"
      :is="step.component"
      :attribute="step.attribute_id"
      class="pe-filter-steps__item"
    />
  </div>
</template>
