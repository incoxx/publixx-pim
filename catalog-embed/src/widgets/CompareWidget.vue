<script setup>
import { ref, computed } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions } = useStore()
const showDiffsOnly = ref(false)

const rows = computed(() => {
  if (!state.compareData?.rows) return []
  if (showDiffsOnly.value) return state.compareData.rows.filter(r => r.is_different)
  return state.compareData.rows
})
</script>

<template>
  <Teleport to="body">
    <transition name="pxc-fade">
      <div v-if="state.compareOpen" class="pxc-compare-overlay" @click.self="actions.closeCompare()">
        <div class="pxc-compare-modal">
          <!-- Header -->
          <div class="pxc-compare-modal__header">
            <span v-html="icons.compare"></span>
            <span>Produktvergleich</span>
            <span v-if="state.compareData" class="pxc-text-muted">
              {{ state.compareData.total_differences }} Unterschiede von {{ state.compareData.total_attributes }} Feldern
            </span>
            <div style="flex:1"></div>
            <label class="pxc-compare-modal__filter">
              <input type="checkbox" v-model="showDiffsOnly" />
              Nur Unterschiede
            </label>
            <button class="pxc-btn pxc-btn--ghost" @click="actions.closeCompare()" v-html="icons.x"></button>
          </div>

          <!-- Body -->
          <div class="pxc-compare-modal__body">
            <div v-if="state.compareLoading" class="pxc-compare-modal__loading">
              <div v-for="i in 8" :key="i" class="pxc-skeleton" style="height:32px;margin-bottom:4px"></div>
            </div>

            <table v-else-if="state.compareData" class="pxc-compare-table">
              <thead>
                <tr>
                  <th>Attribut</th>
                  <th v-for="prod in state.compareData.products" :key="prod.id">
                    {{ prod.sku }} <span class="pxc-text-muted">{{ prod.name }}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in rows" :key="i" :class="{ 'pxc-compare-table__diff': row.is_different }">
                  <td>{{ row.attribute_name }}</td>
                  <td v-for="(val, idx) in row.values" :key="idx">{{ val ?? '—' }}</td>
                </tr>
                <tr v-if="rows.length === 0">
                  <td :colspan="1 + (state.compareData?.products?.length || 0)" style="text-align:center;padding:32px">
                    {{ showDiffsOnly ? 'Keine Unterschiede' : 'Keine Attribute' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
