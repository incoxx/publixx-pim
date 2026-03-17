<script setup>
import { watch, ref, computed } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'

const { state, actions, getters } = useStore()
const selectedImageIdx = ref(0)

// Body scroll lock when detail modal is open
watch(() => state.detailOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})

const images = computed(() => {
  if (!state.currentProduct?.media) return []
  return state.currentProduct.media.filter(m => m.media_type === 'image')
})

const currentImage = computed(() => images.value[selectedImageIdx.value])

// Reset image index when product changes
watch(() => state.currentProduct, () => { selectedImageIdx.value = 0 })

function prev() {
  selectedImageIdx.value = selectedImageIdx.value > 0
    ? selectedImageIdx.value - 1
    : images.value.length - 1
}

function next() {
  selectedImageIdx.value = selectedImageIdx.value < images.value.length - 1
    ? selectedImageIdx.value + 1
    : 0
}

function formatPrice(price, currency) {
  if (!price) return null
  return new Intl.NumberFormat(state.locale === 'de' ? 'de-DE' : 'en-US', {
    style: 'currency', currency: currency || 'EUR',
  }).format(price)
}

async function downloadPdf() {
  if (!state.currentProduct) return
  await actions.downloadProductPdf(state.currentProduct.id)
}
</script>

<template>
  <!-- Modal overlay — only shows when detailOpen is true -->
  <Teleport to="body">
    <transition name="pxc-fade">
      <div v-if="state.detailOpen" class="pxc-detail-overlay" @click.self="actions.closeDetail()">
        <div class="pxc-detail-modal">
          <!-- Close button -->
          <button class="pxc-detail-modal__close" @click="actions.closeDetail()" v-html="icons.x"></button>

          <!-- Loading -->
          <div v-if="state.productLoading" class="pxc-detail-modal__loading">
            <span v-html="icons.loader" style="width:32px;height:32px"></span>
            <p>Lade Produktdetails...</p>
          </div>

          <!-- Content -->
          <div v-else-if="state.currentProduct" class="pxc-detail">
            <div class="pxc-detail__layout">
              <!-- Left: Gallery -->
              <div class="pxc-detail__gallery">
                <div class="pxc-detail__main-image">
                  <img v-if="currentImage" :src="currentImage.url" :alt="currentImage.alt || ''" />
                  <div v-else class="pxc-detail__no-image">
                    <span v-html="icons.package" style="width:64px;height:64px;opacity:0.1"></span>
                  </div>
                  <template v-if="images.length > 1">
                    <button class="pxc-detail__nav pxc-detail__nav--prev" @click="prev" v-html="icons.chevronLeft"></button>
                    <button class="pxc-detail__nav pxc-detail__nav--next" @click="next" v-html="icons.chevronRight"></button>
                  </template>
                </div>
                <div v-if="images.length > 1" class="pxc-detail__thumbs">
                  <button
                    v-for="(img, idx) in images"
                    :key="img.url"
                    class="pxc-detail__thumb"
                    :class="{ 'pxc-detail__thumb--active': idx === selectedImageIdx }"
                    @click="selectedImageIdx = idx"
                  >
                    <img :src="img.url" :alt="img.alt || ''" />
                  </button>
                </div>
              </div>

              <!-- Right: Info -->
              <div class="pxc-detail__info">
                <!-- Breadcrumb -->
                <p v-if="state.currentProduct.breadcrumbs?.length" class="pxc-detail__breadcrumb">
                  <span v-for="(bc, i) in state.currentProduct.breadcrumbs" :key="i">
                    {{ bc.name }}<template v-if="i < state.currentProduct.breadcrumbs.length - 1"> / </template>
                  </span>
                </p>

                <h2 class="pxc-detail__title">{{ state.currentProduct.name }}</h2>

                <div class="pxc-detail__meta">
                  <span v-if="state.currentProduct.sku">SKU: {{ state.currentProduct.sku }}</span>
                  <span v-if="state.currentProduct.ean">EAN: {{ state.currentProduct.ean }}</span>
                </div>

                <!-- Prices -->
                <div v-if="state.currentProduct.prices?.length" class="pxc-detail__prices">
                  <div v-for="(price, idx) in state.currentProduct.prices" :key="idx" class="pxc-detail__price">
                    <span class="pxc-detail__price-label">{{ price.type_name || 'Preis' }}</span>
                    <span class="pxc-detail__price-value">{{ formatPrice(price.value, price.currency) }}</span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="pxc-detail__actions">
                  <button
                    class="pxc-btn"
                    :class="getters.isInWishlist(state.currentProduct.id) ? 'pxc-btn--accent' : 'pxc-btn--outline'"
                    @click="actions.toggleWishlist(state.currentProduct.id)"
                  >
                    <span v-html="getters.isInWishlist(state.currentProduct.id) ? icons.heartFilled : icons.heart"></span>
                    {{ getters.isInWishlist(state.currentProduct.id) ? 'Auf Merkliste' : 'Zur Merkliste' }}
                  </button>
                  <button
                    v-if="state.settings.catalog_pdf_enabled"
                    class="pxc-btn pxc-btn--outline"
                    @click="downloadPdf"
                  >
                    <span v-html="icons.fileDown"></span>
                    PDF
                  </button>
                </div>

                <!-- Attribute sections -->
                <div v-if="state.currentProduct.attribute_sections?.length" class="pxc-detail__sections">
                  <div v-for="section in state.currentProduct.attribute_sections" :key="section.name" class="pxc-detail__section">
                    <h3 class="pxc-detail__section-title">{{ section.name }}</h3>
                    <table class="pxc-detail__table">
                      <tr v-for="attr in section.attributes" :key="attr.attribute_id">
                        <td class="pxc-detail__table-label">{{ attr.label }}</td>
                        <td class="pxc-detail__table-value">
                          <template v-if="attr.type === 'Hyperlink'">
                            <a :href="attr.value" target="_blank" rel="noopener">{{ attr.value }}</a>
                          </template>
                          <template v-else>{{ attr.display_value || attr.value || '—' }}</template>
                          <span v-if="attr.unit" class="pxc-text-muted"> {{ attr.unit }}</span>
                        </td>
                      </tr>
                    </table>
                  </div>
                </div>

                <!-- Relations -->
                <div v-if="state.currentProduct.relations?.length" class="pxc-detail__relations">
                  <div v-for="rel in state.currentProduct.relations" :key="rel.type_id" class="pxc-detail__relation-group">
                    <h3 class="pxc-detail__section-title">{{ rel.type_name }}</h3>
                    <div class="pxc-detail__relation-items">
                      <div
                        v-for="item in rel.products"
                        :key="item.id"
                        class="pxc-detail__relation-card"
                        @click="actions.openDetail(item.id)"
                      >
                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" />
                        <span v-else v-html="icons.package"></span>
                        <p>{{ item.name }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Error -->
          <div v-else-if="state.error" class="pxc-detail-modal__error">
            <p>{{ state.error }}</p>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
