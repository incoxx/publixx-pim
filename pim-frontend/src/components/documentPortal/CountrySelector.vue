<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  countriesByRegion: { type: Object, default: () => ({}) },
  selectedCountry: { type: Object, default: null },
})
const emit = defineEmits(['select'])

const searchTerm = ref('')

const filteredRegions = computed(() => {
  const term = searchTerm.value.toLowerCase()
  if (!term) return props.countriesByRegion

  const result = {}
  for (const [region, countries] of Object.entries(props.countriesByRegion)) {
    const filtered = countries.filter(c =>
      c.name.toLowerCase().includes(term) || c.code.toLowerCase().includes(term)
    )
    if (filtered.length) result[region] = filtered
  }
  return result
})

// Flaggen-Emoji aus Laendercode ableiten
function countryFlag(code) {
  if (code === 'EU') return '\uD83C\uDDEA\uD83C\uDDFA'
  if (code === 'ROW') return '\uD83C\uDF0D'
  if (code.length !== 2) return '\uD83C\uDF10'
  return String.fromCodePoint(
    ...[...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65)
  )
}
</script>

<template>
  <div class="cs-root">
    <div class="cs-header">
      <h3>Region waehlen</h3>
      <p>Waehlen Sie Ihr Land, um passende Dokumente anzuzeigen</p>
    </div>

    <!-- Suche -->
    <div class="cs-search">
      <svg class="cs-search__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
      <input
        v-model="searchTerm"
        type="text"
        placeholder="Land suchen..."
        class="cs-search__input"
      />
    </div>

    <!-- Laenderliste -->
    <div class="cs-list">
      <div v-for="(countries, region) in filteredRegions" :key="region" class="cs-region">
        <div class="cs-region__label">{{ region }}</div>
        <button
          v-for="country in countries"
          :key="country.code"
          class="cs-country"
          :class="{ 'cs-country--active': selectedCountry?.code === country.code }"
          @click="emit('select', country)"
        >
          <span class="cs-country__flag">{{ countryFlag(country.code) }}</span>
          <span class="cs-country__name">{{ country.name }}</span>
          <svg v-if="selectedCountry?.code === country.code" class="cs-country__check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
          <svg v-else class="cs-country__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>
        </button>
      </div>

      <div v-if="Object.keys(filteredRegions).length === 0" class="cs-empty">
        Kein Land gefunden
      </div>
    </div>
  </div>
</template>

<style scoped>
.cs-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 4px;
  color: var(--dp-dark, #0c4a6e);
}
.cs-header p {
  color: #64748b;
  font-size: 0.875rem;
  margin-bottom: 20px;
}
.cs-search {
  position: relative;
  margin-bottom: 16px;
}
.cs-search__icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: var(--dp-primary, #0891b2);
}
.cs-search__input {
  width: 100%;
  padding: 10px 12px 10px 40px;
  border: 1px solid var(--dp-border, #bae6fd);
  border-radius: var(--dp-card-radius, 12px);
  font-size: 0.875rem;
  outline: none;
  background: rgba(240, 249, 255, 0.5);
}
.cs-search__input:focus {
  border-color: var(--dp-primary, #0891b2);
  box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.15);
}
.cs-list {
  overflow-y: auto;
  max-height: 340px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.cs-region__label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--dp-primary, #0891b2);
  padding: 0 8px;
  margin-bottom: 4px;
}
.cs-country {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--dp-border, #bae6fd);
  border-radius: var(--dp-card-radius, 12px);
  background: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.15s ease;
  text-align: left;
  font-size: 0.9375rem;
  margin-bottom: 6px;
}
.cs-country:hover {
  border-color: var(--dp-primary, #0891b2);
  background: var(--dp-surface, #f0f9ff);
}
.cs-country--active {
  border-color: var(--dp-primary, #0891b2);
  background: #e0f2fe;
}
.cs-country__flag {
  font-size: 1.5rem;
}
.cs-country__name {
  flex: 1;
  color: var(--dp-dark, #0c4a6e);
}
.cs-country__check {
  width: 20px;
  height: 20px;
  color: var(--dp-primary, #0891b2);
}
.cs-country__arrow {
  width: 18px;
  height: 18px;
  color: var(--dp-primary, #0891b2);
  opacity: 0.3;
}
.cs-country:hover .cs-country__arrow {
  opacity: 1;
}
.cs-empty {
  text-align: center;
  color: #94a3b8;
  padding: 24px;
}
</style>
