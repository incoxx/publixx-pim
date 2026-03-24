<script setup>
import { watch, ref, computed } from 'vue'
import { useStore } from '../store.js'
import { icons } from '../icons.js'
import { resolveMediaUrl } from '../api.js'

const { state, actions, getters } = useStore()
const selectedImageIdx = ref(0)
const activeTab = ref('attributes')

// Body scroll lock when detail modal is open
watch(() => state.detailOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
  if (open) {
    activeTab.value = 'attributes'
    selectedImageIdx.value = 0
    // Lazy-load attribute groups if not loaded yet
    if (state.attributeGroups.length === 0) {
      actions.fetchAttributeGroups()
    }
  }
})

const images = computed(() => {
  if (!state.currentProduct?.media) return []
  return state.currentProduct.media.filter(m => m.media_type === 'image')
})

const documents = computed(() => {
  if (!state.currentProduct?.media) return []
  return state.currentProduct.media.filter(m => m.media_type !== 'image')
})

const currentImage = computed(() => images.value[selectedImageIdx.value])

// Reset image index when product changes
watch(() => state.currentProduct, () => { selectedImageIdx.value = 0 })

// Group attributes by parent (composite) or as flat list
const flatAttributes = computed(() => {
  const attrs = state.currentProduct?.attributes
  if (!attrs?.length) return []

  // Filter out child attributes of composites — they are shown inline
  const childIds = new Set()
  attrs.forEach(a => {
    if (a.parent_attribute_id) childIds.add(a.attribute_id)
  })

  return attrs.filter(a => !a.parent_attribute_id || !childIds.has(a.attribute_id))
})

// Group attributes by attribute group (AttributeType) — MediaMarkt style
const attributeSections = computed(() => {
  const attrs = flatAttributes.value
  if (!attrs.length) return []

  const groups = state.attributeGroups || []
  const groupMap = {}
  for (const g of groups) {
    groupMap[g.id] = g.name
  }

  // Check if there are multiple groups
  const uniqueGroups = new Set(attrs.map(a => a.group_id).filter(Boolean))
  const hasMultipleGroups = uniqueGroups.size > 1

  if (!hasMultipleGroups) {
    // Single or no group — flat list, no headers
    return [{ name: null, attrs }]
  }

  // Build ordered sections: grouped attributes first (by group sort_order), ungrouped last
  const groupOrder = {}
  groups.forEach((g, i) => { groupOrder[g.id] = i })

  const sections = {}
  const ungrouped = []

  for (const attr of attrs) {
    if (attr.group_id && groupMap[attr.group_id]) {
      if (!sections[attr.group_id]) {
        sections[attr.group_id] = {
          name: groupMap[attr.group_id],
          sortOrder: groupOrder[attr.group_id] ?? 999,
          attrs: [],
        }
      }
      sections[attr.group_id].attrs.push(attr)
    } else {
      ungrouped.push(attr)
    }
  }

  const result = Object.values(sections).sort((a, b) => a.sortOrder - b.sortOrder)
  if (ungrouped.length) {
    result.push({ name: state.locale === 'de' ? 'Sonstige' : 'Other', attrs: ungrouped })
  }

  return result
})

// Group relations by type
const groupedRelations = computed(() => {
  const rels = state.currentProduct?.relations
  if (!rels?.length) return []

  const groups = {}
  for (const rel of rels) {
    const typeId = rel.relation_type_id || 'default'
    if (!groups[typeId]) {
      groups[typeId] = {
        type_id: typeId,
        type_name: rel.relation_type || (state.locale === 'de' ? 'Verwandte Produkte' : 'Related Products'),
        products: [],
      }
    }
    groups[typeId].products.push({
      id: rel.target_product_id,
      name: rel.name,
      sku: rel.sku,
      image_url: rel.image_url ? resolveMediaUrl(rel.image_url) : null,
    })
  }
  return Object.values(groups)
})

const hasRelations = computed(() => groupedRelations.value.length > 0)
const hasDocuments = computed(() => documents.value.length > 0)

// Available tabs
const tabs = computed(() => {
  const t = [{ key: 'attributes', label: state.locale === 'de' ? 'Eigenschaften' : 'Attributes' }]
  if (hasDocuments.value) {
    t.push({ key: 'media', label: state.locale === 'de' ? 'Dokumente' : 'Documents' })
  }
  if (hasRelations.value) {
    t.push({ key: 'relations', label: state.locale === 'de' ? 'Beziehungen' : 'Relations' })
  }
  return t
})

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

function getDocIcon(mimeType) {
  if (mimeType?.includes('pdf')) return icons.fileDown
  if (mimeType?.includes('sheet') || mimeType?.includes('excel')) return icons.sheet
  return icons.fileDown
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
            <p>{{ state.locale === 'de' ? 'Lade Produktdetails…' : 'Loading product…' }}</p>
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
                <p v-if="state.currentProduct.category_breadcrumb?.length" class="pxc-detail__breadcrumb">
                  <span v-for="(bc, i) in state.currentProduct.category_breadcrumb" :key="i">
                    {{ bc.name }}<template v-if="i < state.currentProduct.category_breadcrumb.length - 1"> / </template>
                  </span>
                </p>

                <h2 class="pxc-detail__title">{{ state.currentProduct.name }}</h2>

                <div class="pxc-detail__meta">
                  <span v-if="state.currentProduct.sku">SKU: {{ state.currentProduct.sku }}</span>
                  <span v-if="state.currentProduct.ean">EAN: {{ state.currentProduct.ean }}</span>
                </div>

                <!-- Description attributes -->
                <div v-if="state.currentProduct.description_attributes?.length" class="pxc-detail__description">
                  <div v-for="da in state.currentProduct.description_attributes" :key="da.attribute_id"
                    :class="'pxc-detail__desc-' + (da.typography || 'base')">
                    {{ da.value }}
                  </div>
                </div>

                <!-- Prices -->
                <div v-if="state.currentProduct.prices?.length" class="pxc-detail__prices">
                  <div v-for="(price, idx) in state.currentProduct.prices" :key="idx" class="pxc-detail__price">
                    <span class="pxc-detail__price-label">{{ price.type_name || 'Preis' }}</span>
                    <span class="pxc-detail__price-value">{{ formatPrice(price.amount, price.currency) }}</span>
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
                    {{ getters.isInWishlist(state.currentProduct.id)
                      ? (state.locale === 'de' ? 'Auf Merkliste' : 'On Wishlist')
                      : (state.locale === 'de' ? 'Zur Merkliste' : 'Add to Wishlist') }}
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

                <!-- Tabs -->
                <div class="pxc-detail__tabs">
                  <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="pxc-detail__tab"
                    :class="{ 'pxc-detail__tab--active': activeTab === tab.key }"
                    @click="activeTab = tab.key"
                  >
                    {{ tab.label }}
                  </button>
                </div>

                <!-- Tab: Attributes -->
                <div v-if="activeTab === 'attributes'" class="pxc-detail__tab-content">
                  <template v-if="attributeSections.length">
                    <div v-for="(section, sIdx) in attributeSections" :key="sIdx" :class="sIdx > 0 ? 'pxc-mt-4' : ''">
                      <h4 v-if="section.name" class="pxc-detail__group-header">{{ section.name }}</h4>
                      <table class="pxc-detail__table">
                        <tbody>
                          <tr v-for="attr in section.attrs" :key="attr.attribute_id">
                            <td class="pxc-detail__table-label">{{ attr.label }}</td>
                            <td class="pxc-detail__table-value">
                              <template v-if="attr.link_data">
                                <a :href="attr.link_data.url" target="_blank" rel="noopener">
                                  {{ attr.link_data.title || attr.link_data.url }}
                                </a>
                              </template>
                              <template v-else-if="attr.data_type === 'Hyperlink'">
                                <a :href="attr.value" target="_blank" rel="noopener">{{ attr.value }}</a>
                              </template>
                              <template v-else-if="attr.value && attr.value.includes('\n')">
                                <div v-for="(line, li) in attr.value.split('\n')" :key="li">{{ line }}</div>
                              </template>
                              <template v-else>{{ attr.value || '—' }}</template>
                              <span v-if="attr.unit" class="pxc-text-muted"> {{ attr.unit }}</span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </template>
                  <p v-else class="pxc-detail__empty">
                    {{ state.locale === 'de' ? 'Keine Eigenschaften vorhanden.' : 'No attributes available.' }}
                  </p>
                </div>

                <!-- Tab: Documents/Media -->
                <div v-if="activeTab === 'media'" class="pxc-detail__tab-content">
                  <div v-if="documents.length" class="pxc-detail__documents">
                    <a
                      v-for="doc in documents"
                      :key="doc.file_name"
                      :href="doc.url"
                      target="_blank"
                      rel="noopener"
                      class="pxc-detail__doc-item"
                    >
                      <span class="pxc-detail__doc-icon" v-html="getDocIcon(doc.mime_type)"></span>
                      <div class="pxc-detail__doc-info">
                        <span class="pxc-detail__doc-name">{{ doc.description || doc.file_name }}</span>
                        <span class="pxc-detail__doc-type">{{ doc.mime_type }}</span>
                      </div>
                    </a>
                  </div>
                  <p v-else class="pxc-detail__empty">
                    {{ state.locale === 'de' ? 'Keine Dokumente vorhanden.' : 'No documents available.' }}
                  </p>
                </div>

                <!-- Tab: Relations -->
                <div v-if="activeTab === 'relations'" class="pxc-detail__tab-content">
                  <div v-for="group in groupedRelations" :key="group.type_id" class="pxc-detail__relation-group">
                    <h4 class="pxc-detail__relation-type">{{ group.type_name }}</h4>
                    <div class="pxc-detail__relation-items">
                      <div
                        v-for="item in group.products"
                        :key="item.id"
                        class="pxc-detail__relation-card"
                        @click="actions.openDetail(item.id)"
                      >
                        <div class="pxc-detail__relation-img">
                          <img v-if="item.image_url" :src="item.image_url" :alt="item.name" />
                          <span v-else v-html="icons.package" class="pxc-detail__relation-placeholder"></span>
                        </div>
                        <div class="pxc-detail__relation-info">
                          <p class="pxc-detail__relation-name">{{ item.name }}</p>
                          <span v-if="item.sku" class="pxc-detail__relation-sku">{{ item.sku }}</span>
                        </div>
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
