/**
 * Offline Catalog API — client-side virtual API layer.
 *
 * Implements the same interface as catalogApi (api.js) but reads
 * from pre-exported JSON data files instead of hitting a server.
 *
 * Datenstruktur im `dataPath` Verzeichnis:
 * ```
 * data/
 * ├── products/
 * │   ├── index.json          Chunk-Metadaten (totalProducts, chunks[], detailBuckets, relationDetailMap)
 * │   ├── chunk-0.json        Kompakt-Produkte (500 pro Chunk, nur Listenfelder)
 * │   └── chunk-{n}.json
 * ├── products-detail/
 * │   └── {bucket}/{uuid}.json  Vollständige Produktdetails (1 JSON pro Produkt, max 1000 pro Bucket)
 * ├── search-index/
 * │   ├── index.json          Suchindex-Metadaten (Produkte ohne Hierarchie-Zuordnung)
 * │   └── chunk-{n}.json
 * ├── categories.json         Hierarchie-Baum (verschachtelt, mit children[])
 * ├── facets.json             Filter-Konfiguration (Attribute + Wertlisten für Facettierung)
 * ├── attribute-groups.json   Attributgruppen für Detail-Ansicht
 * └── settings.json           Theme-Einstellungen (Farben, Locale, Feature-Flags)
 * ```
 *
 * Alle Such-, Filter-, Sortier- und Paginierungs-Operationen laufen client-seitig in-memory.
 *
 * @module offline-api
 */

/**
 * @typedef {Object} CompactProduct
 * @property {string} id - Produkt-UUID
 * @property {string} sku - Artikelnummer
 * @property {string} [ean] - EAN/GTIN
 * @property {string} name - Produktname (bereits locale-aufgelöst)
 * @property {string} [img] - Bild-URL (Teaser)
 * @property {number} [price] - Hauptpreis (Dezimalwert)
 * @property {string} [cur] - Währungscode (Standard: 'EUR')
 * @property {string} [cat] - Primäre Kategorie-ID
 * @property {string} [cat_name] - Kategorie-Pfad als Breadcrumb-String
 * @property {string[]} [cats] - Alle zugeordneten Kategorie-IDs
 * @property {Object<string, string|number|boolean>} [facets] - Facettenwerte (attrId → Wert)
 * @property {string} [search] - Zusätzlicher Suchtext (konkatenierte Attributwerte)
 * @property {string} [primary] - Primärer Attributwert (für Listenansicht)
 * @property {Array} [attrs] - Karten-Attribute (für Grid-Kacheln)
 * @property {number} [_dd] - Detail-Bucket-Nummer (für products-detail/{_dd}/{id}.json)
 */

/**
 * @typedef {Object} PaginationMeta
 * @property {number} current_page - Aktuelle Seite (1-basiert)
 * @property {number} last_page - Letzte Seite
 * @property {number} per_page - Einträge pro Seite
 * @property {number} total - Gesamtanzahl
 */

/**
 * @typedef {Object} OfflineApiOptions
 * @property {function(number, number): void} [onProgress] - Callback während Chunk-Loading (geladene, gesamt)
 */

/**
 * Erzeugt eine Offline-API-Instanz, die aus vorexportierten JSON-Dateien liest.
 *
 * Die zurückgegebene API implementiert dasselbe Interface wie die Online-catalogApi,
 * sodass der Store (store.js) und alle Widgets ohne Änderung funktionieren.
 *
 * @param {string} dataPath - Pfad zum Datenverzeichnis (z.B. './data/')
 * @param {OfflineApiOptions} [options] - Optionen
 * @returns {Object} API-Objekt mit getProducts, getProduct, getCategories, getSettings, etc.
 */
export function createOfflineApi(dataPath, options = {}) {
  const basePath = dataPath.replace(/\/+$/, '')

  /** @type {CompactProduct[]|null} Hierarchie-Produkte (eager geladen) */
  let _primaryProducts = null
  /** @type {CompactProduct[]|null} Nicht-Hierarchie-Produkte (lazy geladen bei erster Suche) */
  let _searchProducts = null
  /** @type {{totalProducts: number, chunks: string[], detailBuckets: number, relationDetailMap?: Object<string,number>}|null} */
  let _productIndex = null
  /** @type {Object|null} */
  let _settings = null
  /** @type {Object|null} */
  let _categories = null
  /** @type {Object|null} */
  let _facets = null
  /** @type {Promise<CompactProduct[]>|null} */
  let _primaryPromise = null
  /** @type {Promise<CompactProduct[]>|null} */
  let _searchPromise = null

  /**
   * Lädt eine JSON-Datei relativ zum dataPath.
   *
   * @param {string} path - Relativer Pfad (z.B. 'products/chunk-0.json')
   * @returns {Promise<any>} Geparstes JSON
   * @throws {Error} Bei HTTP-Fehlern (404, 500, etc.)
   */
  async function fetchJson(path) {
    const resp = await fetch(`${basePath}/${path}`)
    if (!resp.ok) throw new Error(`Failed to load ${path}: HTTP ${resp.status}`)
    return resp.json()
  }

  /**
   * Lädt alle Hierarchie-Produkt-Chunks in den Speicher.
   *
   * Wird einmalig aufgerufen — alle weiteren Aufrufe liefern das gecachte Ergebnis.
   * Chunks werden in 4er-Batches parallel geladen, um HTTP/2-Multiplexing zu nutzen
   * ohne den Browser mit zu vielen gleichzeitigen Requests zu überlasten.
   *
   * @returns {Promise<CompactProduct[]>} Alle Hierarchie-Produkte
   */
  async function loadPrimaryProducts() {
    if (_primaryProducts) return _primaryProducts
    if (_primaryPromise) return _primaryPromise

    _primaryPromise = (async () => {
      _productIndex = await fetchJson('products/index.json')
      const { chunks, totalProducts } = _productIndex
      const products = []

      const batchSize = 4
      for (let i = 0; i < chunks.length; i += batchSize) {
        const batch = chunks.slice(i, i + batchSize)
        const results = await Promise.all(
          batch.map(chunkFile => fetchJson(`products/${chunkFile}`))
        )
        for (const chunkData of results) {
          products.push(...chunkData)
        }
        if (options.onProgress) {
          options.onProgress(products.length, totalProducts)
        }
      }

      _primaryProducts = products
      return _primaryProducts
    })()

    return _primaryPromise
  }

  /**
   * Lädt Nicht-Hierarchie-Produkte aus dem search-index.
   *
   * Wird lazy beim ersten Suchvorgang geladen. Diese Produkte haben keine
   * direkte Hierarchie-Zuordnung und tauchen nur in Suchergebnissen auf.
   *
   * @returns {Promise<CompactProduct[]>} Alle Nicht-Hierarchie-Produkte
   */
  async function loadSearchProducts() {
    if (_searchProducts) return _searchProducts
    if (_searchPromise) return _searchPromise

    _searchPromise = (async () => {
      try {
        const searchIndex = await fetchJson('search-index/index.json')
        const { chunks } = searchIndex
        if (!chunks || chunks.length === 0) {
          _searchProducts = []
          return _searchProducts
        }

        const products = []
        const batchSize = 4
        for (let i = 0; i < chunks.length; i += batchSize) {
          const batch = chunks.slice(i, i + batchSize)
          const results = await Promise.all(
            batch.map(chunkFile => fetchJson(`search-index/${chunkFile}`))
          )
          for (const chunkData of results) {
            products.push(...chunkData)
          }
        }

        _searchProducts = products
        return _searchProducts
      } catch {
        // search-index may not exist in older exports
        _searchProducts = []
        return _searchProducts
      }
    })()

    return _searchPromise
  }

  /**
   * Vereinigt Hierarchie- und Nicht-Hierarchie-Produkte für die Volltextsuche.
   *
   * @returns {Promise<CompactProduct[]>} Alle Produkte (primary + search-index)
   */
  async function loadAllProducts() {
    const primary = await loadPrimaryProducts()
    const search = await loadSearchProducts()
    return primary.concat(search)
  }

  /** @type {Map<string, CompactProduct>|null} ID → Produkt Lookup (lazy aufgebaut) */
  let _productMap = null

  /**
   * Baut eine ID→Produkt Map für schnellen Zugriff (O(1) statt O(n)).
   *
   * @param {CompactProduct[]} products - Produktliste
   * @returns {Map<string, CompactProduct>}
   */
  function getProductMap(products) {
    if (!_productMap) {
      _productMap = new Map()
      for (const p of products) _productMap.set(p.id, p)
    }
    return _productMap
  }

  /**
   * Sucht ein Produkt nach ID in den Hierarchie-Produkten.
   *
   * Gibt null zurück falls nicht gefunden — das Produkt kann trotzdem
   * ein Detail-JSON haben (via relationDetailMap für Relationsprodukte).
   *
   * @param {string} id - Produkt-UUID
   * @returns {Promise<CompactProduct|null>}
   */
  async function findProductById(id) {
    const primary = await loadPrimaryProducts()
    const map = getProductMap(primary)
    return map.get(id) || null
  }

  /**
   * Filtert Produkte nach Suchbegriff (Substring-Match auf name, sku, ean, search).
   *
   * @param {CompactProduct[]} products - Eingabeliste
   * @param {string} search - Suchbegriff
   * @returns {CompactProduct[]} Gefilterte Produkte
   */
  function filterBySearch(products, search) {
    if (!search || !search.trim()) return products
    const term = search.trim().toLowerCase()
    return products.filter(p => {
      const name = (p.name || '').toLowerCase()
      const sku = (p.sku || '').toLowerCase()
      const ean = (p.ean || '').toLowerCase()
      const extra = (p.search || '').toLowerCase()
      return name.includes(term) || sku.includes(term)
        || ean.includes(term) || extra.includes(term)
    })
  }

  /**
   * Filtert Produkte nach Kategorie-ID (prüft cats[]-Array oder cat-Feld).
   *
   * Da der Export Produkte bereits mit allen Eltern-Kategorie-IDs in cats[] exportiert,
   * reicht ein einfacher includes-Check — keine Baum-Traversierung nötig.
   *
   * @param {CompactProduct[]} products - Eingabeliste
   * @param {string|null} categoryId - Kategorie-UUID oder null für alle
   * @returns {CompactProduct[]} Gefilterte Produkte
   */
  function filterByCategory(products, categoryId) {
    if (!categoryId) return products
    return products.filter(p => {
      if (!p.cats) return p.cat === categoryId
      return p.cats.includes(categoryId)
    })
  }

  /**
   * Filtert Produkte nach Facetten-Filtern.
   *
   * Unterstützte Filter-Formate (identisch zur Server-API):
   * - Werteliste: `"value1,value2"` — prüft ob p.facets[attrId] in der Liste
   * - Range:      `"min:max"` — numerischer Bereichsfilter
   * - Boolean:    `"0"` oder `"1"` — true/false Vergleich
   *
   * @param {CompactProduct[]} products - Eingabeliste
   * @param {Object<string, string>} filters - Filter-Map (Attribut-ID → Filterwert)
   * @returns {CompactProduct[]} Gefilterte Produkte
   */
  function filterByFacets(products, filters) {
    if (!filters || Object.keys(filters).length === 0) return products

    return products.filter(p => {
      const fv = p.facets || {}
      for (const [attrId, filterValue] of Object.entries(filters)) {
        const val = fv[attrId]
        if (val == null) return false

        if (filterValue.includes(':')) {
          // Range filter (numeric)
          const [minStr, maxStr] = filterValue.split(':')
          const numVal = typeof val === 'number' ? val : parseFloat(val)
          if (isNaN(numVal)) return false
          const minVal = parseFloat(minStr)
          const maxVal = parseFloat(maxStr)
          if (minStr !== '' && !isNaN(minVal) && numVal < minVal) return false
          if (maxStr !== '' && !isNaN(maxVal) && numVal > maxVal) return false
        } else if (filterValue === '0' || filterValue === '1') {
          // Boolean filter
          const expected = filterValue === '1'
          if (val !== expected) return false
        } else {
          // Value list filter (comma-separated IDs)
          const selectedIds = filterValue.split(',').filter(Boolean).map(v => decodeURIComponent(v))
          if (selectedIds.length === 0) continue
          if (!selectedIds.includes(String(val))) return false
        }
      }
      return true
    })
  }

  /**
   * Sortiert Produkte nach Feld und Richtung.
   *
   * @param {CompactProduct[]} products - Eingabeliste (wird nicht mutiert)
   * @param {('name'|'sku'|'price')} sortField - Sortierfeld
   * @param {('asc'|'desc')} sortOrder - Sortierrichtung
   * @returns {CompactProduct[]} Neue sortierte Liste
   */
  function sortProducts(products, sortField, sortOrder) {
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
        default: // name (already locale-resolved by export)
          va = (a.name || '').toLowerCase()
          vb = (b.name || '').toLowerCase()
      }

      if (va < vb) return asc ? -1 : 1
      if (va > vb) return asc ? 1 : -1
      return 0
    })

    return sorted
  }

  /**
   * Paginiert ein Array (client-seitig).
   *
   * @param {any[]} items - Gesamtliste
   * @param {number} page - Gewünschte Seite (1-basiert)
   * @param {number} perPage - Einträge pro Seite
   * @returns {{items: any[], meta: PaginationMeta}} Seiteninhalt + Metadaten
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

  // ── Public API (spiegelt catalogApi Interface) ─────────────────

  const api = {
    /**
     * Produkte laden mit Suche, Filterung, Sortierung und Paginierung.
     *
     * Verhält sich wie `GET /api/v1/catalog/products` — gleiche Parameter,
     * gleiches Rückgabeformat. Bei aktiver Suche werden alle Produkte
     * durchsucht (inkl. search-index), bei Navigation nur Hierarchie-Produkte.
     *
     * @param {Object} [opts]
     * @param {number} [opts.page=1] - Seite
     * @param {number} [opts.perPage=24] - Einträge pro Seite
     * @param {string} [opts.sort='name'] - Sortierfeld (name, sku, price)
     * @param {string} [opts.order='asc'] - Sortierrichtung (asc, desc)
     * @param {string} [opts.search] - Suchbegriff (durchsucht name, sku, ean, search)
     * @param {string} [opts.category] - Kategorie-UUID (wird bei Suche ignoriert)
     * @param {Object<string, string>} [opts.filters] - Facetten-Filter
     * @returns {Promise<{products: Object[], meta: PaginationMeta}>}
     */
    async getProducts(opts = {}) {
      const isSearching = opts.search && opts.search.trim().length > 0
      let filtered

      if (isSearching) {
        // Search across ALL products (hierarchy + search-index, lazy-loads search index on first use)
        const allProducts = await loadAllProducts()
        filtered = filterBySearch(allProducts, opts.search)
      } else {
        // Browse/filter: only primary (hierarchy) products
        const primaryProducts = await loadPrimaryProducts()
        filtered = filterByCategory(primaryProducts, opts.category)
        filtered = filterByFacets(filtered, opts.filters)
      }

      filtered = sortProducts(filtered, opts.sort || 'name', opts.order || 'asc')

      const page = opts.page || 1
      const perPage = opts.perPage || 24
      const result = paginate(filtered, page, perPage)

      // Map compact format to full API format for the current page only
      const products = result.items.map(p => ({
        id: p.id,
        sku: p.sku,
        ean: p.ean || null,
        name: p.name,
        description: null, // only in detail
        category_path: p.cat_name || null,
        image_url: p.img,
        price: p.price ?? null,
        currency: p.cur || 'EUR',
        product_type: null,
        primary_attribute_value: p.primary || null,
        card_attributes: p.attrs || [],
        match_sources: null,
      }))

      // Dynamische Kategorie-Counts wenn Facetten aktiv (ohne Kategorie-Einschränkung)
      let categoryCounts = null
      if (!isSearching && opts.filters && Object.keys(opts.filters).length > 0) {
        const primaryProducts = await loadPrimaryProducts()
        const facetFiltered = filterByFacets(primaryProducts, opts.filters)
        categoryCounts = {}
        for (const p of facetFiltered) {
          const catIds = p.cats || (p.cat ? [p.cat] : [])
          for (const catId of catIds) {
            categoryCounts[catId] = (categoryCounts[catId] || 0) + 1
          }
        }
      }

      return { products, meta: result.meta, category_counts: categoryCounts }
    },

    /**
     * Vollständiges Produktdetail laden (aus products-detail/{bucket}/{id}.json).
     *
     * Sucht das Produkt in 3 Stufen:
     * 1. Hierarchie-Produkte (primary) → Bucket aus _dd Feld
     * 2. Such-Index-Produkte → Bucket aus _dd Feld
     * 3. relationDetailMap → für Relationsprodukte ohne eigene Hierarchie-Zuordnung
     *
     * @param {string} id - Produkt-UUID
     * @param {Object} [opts]
     * @param {string} [opts.lang] - Locale (wird im Offline-Modus ignoriert, da vorberechnet)
     * @returns {Promise<Object>} Vollständiges Produktdetail-Objekt
     * @throws {Error} 'Produkt nicht gefunden' wenn keine Detail-Datei existiert
     */
    async getProduct(id, opts = {}) {
      try {
        // 1. Check primary (hierarchy) products
        const product = await findProductById(id)
        if (product) {
          const bucket = product._dd ?? 0
          return await fetchJson(`products-detail/${bucket}/${id}.json`)
        }

        // 2. Check search index products (may have _dd from export)
        if (_searchProducts) {
          const searchProduct = _searchProducts.find(p => p.id === id)
          if (searchProduct && searchProduct._dd != null) {
            return await fetchJson(`products-detail/${searchProduct._dd}/${id}.json`)
          }
        }

        // 3. Fall back to relationDetailMap (for relation-only products)
        if (!_productIndex) {
          _productIndex = await fetchJson('products/index.json')
        }
        const bucket = _productIndex.relationDetailMap?.[id]
        if (bucket != null) {
          return await fetchJson(`products-detail/${bucket}/${id}.json`)
        }

        throw new Error('Produkt nicht gefunden')
      } catch {
        throw new Error('Produkt nicht gefunden')
      }
    },

    /**
     * Kategorie-Baum laden (aus categories.json).
     *
     * @param {Object} [opts]
     * @returns {Promise<Object>} Kategorie-Daten mit nodes[], hierarchy_id, hierarchy_name
     */
    async getCategories(opts = {}) {
      if (!_categories) {
        const data = await fetchJson('categories.json')
        _categories = data.data || data
      }
      return _categories
    },

    /**
     * Katalog-Einstellungen laden (aus settings.json).
     *
     * Enthält Theme-Farben, Feature-Flags, Locale, Hierarchie-Info etc.
     *
     * @returns {Promise<Object>} Settings-Objekt
     */
    async getSettings() {
      if (!_settings) {
        const data = await fetchJson('settings.json')
        _settings = data.data || data
      }
      return _settings
    },

    /**
     * Facetten-Konfiguration laden (aus facets.json).
     *
     * @param {Object} [opts]
     * @returns {Promise<{facets: Array}>} Facetten-Definition mit dynamisch berechneten Counts
     */
    async getFacets(opts = {}) {
      if (!_facets) {
        _facets = await fetchJson('facets.json')
      }

      // Ohne Kategorie/Filter: statische Counts aus facets.json
      if (!opts.category && (!opts.filters || Object.keys(opts.filters).length === 0)) {
        return _facets
      }

      // Dynamische Counts: Produkte nach Kategorie filtern, dann Facetten-Werte zählen
      const primaryProducts = await loadPrimaryProducts()
      let products = filterByCategory(primaryProducts, opts.category)

      const baseFacets = (_facets.facets || _facets)
      const result = []

      for (const facet of baseFacets) {
        const attrId = facet.attribute_id

        // "Exclude-this-facet" Logik: alle ANDEREN Filter anwenden
        let filtered = products
        if (opts.filters && Object.keys(opts.filters).length > 0) {
          const otherFilters = {}
          for (const [k, v] of Object.entries(opts.filters)) {
            if (k !== attrId) otherFilters[k] = v
          }
          if (Object.keys(otherFilters).length > 0) {
            filtered = filterByFacets(filtered, otherFilters)
          }
        }

        if (facet.data_type === 'ValueList' || facet.data_type === 'Text') {
          // Counts pro Wert neu berechnen
          const counts = {}
          for (const p of filtered) {
            const val = p.facets?.[attrId]
            if (val == null) continue
            // MultiSelection: Array von IDs
            if (Array.isArray(val)) {
              for (const v of val) counts[String(v)] = (counts[String(v)] || 0) + 1
            } else {
              counts[String(val)] = (counts[String(val)] || 0) + 1
            }
          }
          const values = (facet.values || [])
            .map(v => ({ ...v, count: counts[String(v.value_id ?? v.value)] || 0 }))
            .filter(v => v.count > 0)
          if (values.length > 0) {
            result.push({ ...facet, values })
          }
        } else if (facet.data_type === 'Boolean') {
          // Zähle true-Werte
          let count = 0
          for (const p of filtered) {
            if (p.facets?.[attrId] === true) count++
          }
          if (count > 0) {
            result.push({ ...facet })
          }
        } else if (facet.data_type === 'Decimal' || facet.data_type === 'Integer') {
          // Min/Max aus den gefilterten Produkten berechnen
          let min = Infinity, max = -Infinity, hasValues = false
          for (const p of filtered) {
            const val = p.facets?.[attrId]
            if (val != null && typeof val === 'number') {
              if (val < min) min = val
              if (val > max) max = val
              hasValues = true
            }
          }
          if (hasValues) {
            result.push({ ...facet, min, max })
          }
        } else {
          result.push(facet)
        }
      }

      return { facets: result }
    },

    /**
     * Attributgruppen laden (aus attribute-groups.json).
     *
     * Wird für die Gruppierung von Attributen in der Produktdetail-Ansicht genutzt.
     *
     * @param {Object} [opts]
     * @returns {Promise<{data: Array}>} Attributgruppen
     */
    async getAttributeGroups(opts = {}) {
      try {
        return await fetchJson('attribute-groups.json')
      } catch {
        return { data: [] }
      }
    },

    /** @throws {Error} Immer — PDF ist im Offline-Bundle nicht verfügbar */
    async downloadProductPdf() {
      throw new Error('PDF-Generierung ist im Offline-Katalog nicht verfügbar.')
    },

    /** @throws {Error} Immer — PDF ist im Offline-Bundle nicht verfügbar */
    async downloadWishlistPdf() {
      throw new Error('PDF-Generierung ist im Offline-Katalog nicht verfügbar.')
    },

    /** @throws {Error} Immer — Excel ist im Offline-Modus nicht verfügbar */
    async downloadWishlistExcel() {
      throw new Error('Excel-Export ist im Offline-Katalog nicht verfügbar.')
    },

    /**
     * Produktvergleich: Lädt Details für alle Produkte und baut eine Vergleichstabelle.
     *
     * Sammelt alle Attribute über alle Produkte und erstellt Zeilen mit
     * is_different-Flag, sodass die UI Unterschiede hervorheben kann.
     *
     * @param {string[]} productIds - Zu vergleichende Produkt-UUIDs
     * @param {string} [lang='de'] - Locale
     * @returns {Promise<{products: Object[], rows: Object[], total_differences: number, total_attributes: number}>}
     */
    async compareProducts(productIds, lang) {
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
 * Media-URL-Resolver für den Offline-Modus.
 *
 * Im Offline-Katalog sind Bild-URLs bereits absolut (externe CDN-URLs)
 * oder relativ zum Datenverzeichnis — keine Origin-Auflösung nötig.
 *
 * @param {string|null} path - Medien-Pfad
 * @returns {string|null} Unveränderte URL oder null
 */
export function offlineResolveMediaUrl(path) {
  if (!path) return null
  return path
}
