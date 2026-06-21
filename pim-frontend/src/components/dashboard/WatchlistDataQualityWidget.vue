<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { X, ArrowRight, Package } from 'lucide-vue-next'
import watchlistApi from '@/api/watchlist'
import DataQualityWidget from './DataQualityWidget.vue'

/**
 * Datenqualität über die eigene Merkliste inkl. "Nächster Schritt":
 * Klick auf eine Lücke zeigt GENAU die betroffenen Produkte + die konkrete Aktion
 * und springt direkt in den Produkteditor.
 */
const router = useRouter()
const quality = ref(null)

// Ausgewählte Lücke (Dimension) für das Detail-Overlay
const selectedGap = ref(null)

function onAction(dim) {
  selectedGap.value = dim
}

function openProduct(p) {
  selectedGap.value = null
  router.push({ name: 'product-detail', params: { id: p.id } })
}

onMounted(async () => {
  try {
    const { data } = await watchlistApi.dataQuality()
    quality.value = data.data || data
  } catch { /* ignore */ }
})
</script>

<template>
  <div>
    <DataQualityWidget
      :quality="quality"
      title="Datenqualität (Merkliste)"
      empty-text="Lege Produkte auf die Merkliste, um deine Datenqualität zu sehen."
      actionable
      @action="onAction"
    />

    <!-- Detail-Overlay: WELCHE Produkte sind betroffen + WAS ist zu tun -->
    <div
      v-if="selectedGap"
      class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 overflow-y-auto"
      @click.self="selectedGap = null"
    >
      <div class="bg-[var(--color-surface)] rounded-xl shadow-xl w-full max-w-lg my-8 flex flex-col max-h-[80vh]">
        <div class="flex items-start justify-between gap-3 px-5 py-3 border-b border-[var(--color-border)] shrink-0">
          <div>
            <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">
              {{ selectedGap.action }}
            </h3>
            <p class="text-xs text-[var(--color-text-tertiary)] mt-0.5">
              {{ selectedGap.label }} · {{ selectedGap.missing_count }} Produkt(e) zu bearbeiten
            </p>
          </div>
          <button class="p-1 rounded hover:bg-[var(--color-bg)]" @click="selectedGap = null">
            <X class="w-4 h-4" :stroke-width="2" />
          </button>
        </div>

        <div class="p-2 overflow-y-auto">
          <p v-if="!(selectedGap.missing || []).length" class="text-center text-xs text-[var(--color-text-tertiary)] py-6">
            Alles erledigt — keine offenen Produkte. 🎉
          </p>
          <button
            v-for="p in selectedGap.missing"
            :key="p.id"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left hover:bg-[var(--color-bg)] transition-colors group"
            @click="openProduct(p)"
          >
            <Package class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium text-[var(--color-text-primary)] truncate">{{ p.name || p.sku || 'Produkt' }}</p>
              <p class="text-[10px] text-[var(--color-text-tertiary)] font-mono">{{ p.sku }}</p>
            </div>
            <ArrowRight class="w-4 h-4 text-[var(--color-accent)] opacity-0 group-hover:opacity-100 transition-opacity shrink-0" :stroke-width="2" />
          </button>

          <p
            v-if="selectedGap.missing_count > (selectedGap.missing || []).length"
            class="text-center text-[11px] text-[var(--color-text-tertiary)] py-2"
          >
            … und {{ selectedGap.missing_count - selectedGap.missing.length }} weitere
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
