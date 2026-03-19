/**
 * Offline Catalog API — client-side virtual API layer.
 *
 * Implements the same interface as catalogApi (api.js) but reads
 * from pre-exported JSON data files. All search, filtering, sorting,
 * and pagination happen in-memory.
 */

/**
 * Creates an offline API instance that reads from the given data path.
 *
 * @param {string} dataPath - Relative path to the data directory (e.g. './data/')
 * @param {object} [options] - Additional options
 * @param {function} [options.onProgress] - Progress callback (loaded, total)
 * @returns {object} API object with the same interface as catalogApi
 */
export function createOfflineApi(dataPath, options = {}) {
  const basePath = dataPath.replace(/\/+$/, '')

  // In-memory product store
  let _allProducts = null
  let _productIndex = null // { totalProducts, chunkSize, chunks }
  let _settings = null
  let _categories = null
  let _facets = null
  let _loadingPromise = null

  async function fetchJson(path) {
    const resp = await fetch(`${basePath}/${path}`)
    if (!resp.ok) throw new Error(`Failed to load ${path}: HTTP ${resp.status}`)
    return resp.json()
  }

  /**
   * Load all product chunks into memory. Called once, returns cached result.
   */
  async function loadAllProducts() {
    if (_allProducts) return _allProducts
    if (_loadingPromise) return _loadingPromise

    _loadingPromise = (async () => {
      _productIndex = await fetchJson('products/index.json')
      const { chunks, totalProducts } = _productIndex
      const allProducts = []

      // Load chunks in parallel batches of 4
      const batchSize = 4
      for (let i = 0; i < chunks.length; i += batchSize) {
        const batch = chunks.slice(i, i + batchSize)
        const results = await Promise.all(
          batch.map(chunkFile => fetchJson(`products/${chunkFile}`))
        )
        for (const chunkData of results) {
          allProducts.push(...chunkData)
        }
        if (options.onProgress) {
          options.onProgress(allProducts.length, totalProducts)
        }
      }

      _allProducts = allProducts
      return _allProducts
    })()

    return _loadingPromise
  }

  /**
   * Filter products by search term (substring match on searchable_text, sku, ean, name).
   */
  function filterBySearch(products, search) {
    if (!search || !search.trim()) return products
    const term = search.trim().toLowerCase()
    return products.filter(p => {
      const text = (p.searchable_text || '').toLowerCase()
      const name = (p.name || '').toLowerCase()
      const nameDe = (p.name_de || '').toLowerCase()
      const nameEn = (p.name_en || '').toLowerCase()
      const sku = (p.sku || '').toLowerCase()
      const ean = (p.ean || '').toLowerCase()
      return text.includes(term) || name.includes(term)
        || nameDe.includes(term) || nameEn.includes(term)
        || sku.includes(term) || ean.includes(term)
    })
  }

  /**
   * Filter products by category (checks category_ids array for the selected node or descendants).
   */
  function filterByCategory(products, categoryId) {
    if (!categoryId) return products
    return products.filter(p => {
      if (!p.category_ids) return p.category_id === categoryId
      return p.category_ids.includes(categoryId)
    })
  }

  /**
   * Filter products by facet filters.
   * filters = { attrId: "value1,value2" | "min:max" | "0" | "1" }
   */
  function filterByFacets(products, filters) {
    if (!filters || Object.keys(filters).length === 0) return products

    return products.filter(p => {
      const fv = p.facet_values || {}
      for (const [attrId, filterValue] of Object.entries(filters)) {
        const facet = fv[attrId]
        if (!facet) return false

        if (filterValue.includes(':')) {
          // Range filter
          const [min, max] = filterValue.split(':')
          const val = facet.value
          if (val == null) return false
          if (min !== '' && val < parseFloat(min)) return false
          if (max !== '' && val > parseFloat(max)) return false
        } else if (filterValue === '0' || filterValue === '1') {
          // Boolean filter
          const expected = filterValue === '1'
          if (facet.value !== expected) return false
        } else {
          // Value list filter (comma-separated IDs or strings)
          const selectedIds = filterValue.split(',').filter(Boolean)
          if (selectedIds.length === 0) continue
          const matchId = facet.value_id || facet.value
          if (!selectedIds.includes(String(matchId))) return false
        }
      }
      return true
    })
  }

  /**
   * Sort products by field and order.
   */
  function sortProducts(products, sortField, sortOrder, lang) {
    const sorted = [...products]
    const asc = sortOrder !== 'desc'

    sorted.sort((a, b) => {
      let va, vb
      switch (sortField) {
        case 'price':
          va = a.price ?? 0
          vb = b.price ?? 0
          break
        case 'sku':
          va = (a.sku || '').toLowerCase()
          vb = (b.sku || '').toLowerCase()
          break
        default: // name
          if (lang === 'en') {
            va = (a.name_en || a.name || '').toLowerCase()
            vb = (b.name_en || b.name || '').toLowerCase()
          } else {
            va = (a.name_de || a.name || '').toLowerCase()
            vb = (b.name_de || b.name || '').toLowerCase()
          }
      }

      if (va < vb) return asc ? -1 : 1
      if (va > vb) return asc ? 1 : -1
      return 0
    })

    return sorted
  }

  /**
   * Paginate an array.
   */
  function paginate(items, page, perPage) {
    const total = items.length
    const lastPage = Math.max(1, Math.ceil(total / perPage))
    const currentPage = Math.min(Math.max(1, page), lastPage)
    const offset = (currentPage - 1) * perPage
    return {
      items: items.slice(offset, offset + perPage),
      meta: {
        current_page: currentPage,
        last_page: lastPage,
        per_page: perPage,
        total,
      },
    }
  }

  /**
   * Resolve product name based on locale.
   */
  function resolveProductName(product, lang) {
    if (lang === 'en') {
      return product.name_en || product.name_de || product.name || ''
    }
    return product.name_de || product.name || ''
  }

  // ── Public API (mirrors catalogApi interface) ─────────────────

  const api = {
    async getProducts(opts = {}) {
      const allProducts = await loadAllProducts()
      const lang = opts.lang || 'de'
      const isSearching = opts.search && opts.search.trim().length > 0

      let filtered = allProducts

      if (isSearching) {
        // Search overrides category and facet filters (same as online API)
        filtered = filterBySearch(filtered, opts.search)
      } else {
        filtered = filterByCategory(filtered, opts.category)
        filtered = filterByFacets(filtered, opts.filters)
      }

      filtered = sortProducts(filtered, opts.sort || 'name', opts.order || 'asc', lang)

      const page = opts.page || 1
      const perPage = opts.perPage || 24
      const result = paginate(filtered, page, perPage)

      // Resolve locale-specific name for each product on the page
      const products = result.items.map(p => ({
        id: p.id,
        sku: p.sku,
        ean: p.ean,
        name: resolveProductName(p, lang),
        description: p.description,
        category_path: p.category_path,
        image_url: p.image_url,
        price: p.price,
        currency: p.currency || 'EUR',
        product_type: p.product_type,
        primary_attribute_value: p.primary_attribute_value || null,
        card_attributes: p.card_attributes || [],
        match_sources: null,
      }))

      return { products, meta: result.meta }
    },

    async getProduct(id, opts = {}) {
      try {
        // Look up detail subdirectory from in-memory product list
        const allProducts = await loadAllProducts()
        const product = allProducts.find(p => p.id === id)
        const bucket = product?._detail_dir ?? 0
        return await fetchJson(`products-detail/${bucket}/${id}.json`)
      } catch {
        throw new Error('Produkt nicht gefunden')
      }
    },

    async getCategories(opts = {}) {
      if (!_categories) {
        const data = await fetchJson('categories.json')
        _categories = data.data || data
      }
      return _categories
    },

    async getSettings() {
      if (!_settings) {
        const data = await fetchJson('settings.json')
        _settings = data.data || data
      }
      return _settings
    },

    async getFacets(opts = {}) {
      if (!_facets) {
        _facets = await fetchJson('facets.json')
      }
      return _facets
    },

    async downloadProductPdf() {
      throw new Error('PDF-Download ist im Offline-Katalog nicht verfügbar.')
    },

    async downloadWishlistPdf() {
      throw new Error('PDF-Download ist im Offline-Katalog nicht verfügbar.')
    },

    async downloadWishlistExcel() {
      throw new Error('Excel-Export ist im Offline-Katalog nicht verfügbar.')
    },

    async compareProducts(productIds, lang) {
      // Load detail data for each product and build comparison
      const products = await Promise.all(
        productIds.map(id => api.getProduct(id, { lang }))
      )

      const productSummaries = products.map(p => ({
        id: p.id,
        sku: p.sku,
        name: p.name,
      }))

      // Merge all attribute keys
      const allAttrKeys = {}
      for (const p of products) {
        if (p.attributes) {
          for (const attr of p.attributes) {
            allAttrKeys[attr.attribute_id] = attr
          }
        }
      }

      const rows = []

      // Base fields
      const baseFields = [
        { field: 'sku', label: 'SKU' },
        { field: 'name', label: 'Name' },
        { field: 'ean', label: 'EAN' },
      ]

      for (const bf of baseFields) {
        const values = products.map(p => String(p[bf.field] || ''))
        rows.push({
          attribute_name: bf.label,
          technical_name: bf.field,
          data_type: 'base',
          values,
          is_different: new Set(values).size > 1,
        })
      }

      // Attribute values
      for (const [attrId, attrInfo] of Object.entries(allAttrKeys)) {
        const values = products.map(p => {
          const attr = (p.attributes || []).find(a => a.attribute_id === attrId)
          return attr ? attr.value : null
        })
        const stringValues = values.map(v => String(v ?? ''))
        rows.push({
          attribute_id: attrId,
          attribute_name: attrInfo.label,
          technical_name: '',
          data_type: attrInfo.data_type || '',
          values,
          is_different: new Set(stringValues).size > 1,
        })
      }

      return {
        products: productSummaries,
        rows,
        total_differences: rows.filter(r => r.is_different).length,
        total_attributes: rows.length,
      }
    },
  }

  return api
}

/**
 * Media URL resolver for offline mode.
 * In offline mode, image URLs are already absolute (external) or relative.
 * No origin resolution needed.
 */
export function offlineResolveMediaUrl(path) {
  if (!path) return null
  return path
}
