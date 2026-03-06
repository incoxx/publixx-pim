import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    redirect: '/products',
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/dashboard/DashboardView.vue'),
    meta: { title: 'Dashboard' },
  },
  {
    path: '/search',
    name: 'search',
    component: () => import('@/views/search/SearchWizardView.vue'),
    meta: { title: 'Suche' },
  },
  {
    path: '/products',
    name: 'products',
    component: () => import('@/views/products/ProductListView.vue'),
    meta: { title: 'Produkte' },
  },
  {
    path: '/products/bulk-edit',
    name: 'bulk-editor',
    component: () => import('@/views/products/BulkEditorView.vue'),
    meta: { title: 'Bulk-Editor' },
  },
  {
    path: '/products/bulk-update',
    name: 'bulk-update',
    component: () => import('@/views/products/BulkUpdateView.vue'),
    meta: { title: 'Massendatenpflege' },
  },
  {
    path: '/products/:id',
    name: 'product-detail',
    component: () => import('@/views/products/ProductDetailView.vue'),
    meta: { title: 'Produktdetail' },
  },
  {
    path: '/hierarchies',
    name: 'hierarchies',
    component: () => import('@/views/hierarchies/HierarchyView.vue'),
    meta: { title: 'Hierarchien' },
  },
  {
    path: '/attributes',
    name: 'attributes',
    component: () => import('@/views/attributes/AttributeAdminView.vue'),
    meta: { title: 'Attribute' },
  },
  {
    path: '/product-types',
    name: 'product-types',
    component: () => import('@/views/productTypes/ProductTypeView.vue'),
    meta: { title: 'Produkttypen' },
  },
  {
    path: '/attribute-types',
    name: 'attribute-types',
    component: () => import('@/views/attributeTypes/AttributeTypeView.vue'),
    meta: { title: 'Attributgruppen' },
  },
  {
    path: '/attribute-views',
    name: 'attribute-views',
    component: () => import('@/views/attributeViews/AttributeViewAdminView.vue'),
    meta: { title: 'Attribut-Sichten' },
  },
  {
    path: '/value-lists',
    name: 'value-lists',
    component: () => import('@/views/valueLists/ValueListView.vue'),
    meta: { title: 'Wertelisten' },
  },
  {
    path: '/dictionary',
    name: 'dictionary',
    component: () => import('@/views/dictionary/DictionaryAdminView.vue'),
    meta: { title: 'Wörterbuch' },
  },
  {
    path: '/units',
    name: 'units',
    component: () => import('@/views/units/UnitGroupView.vue'),
    meta: { title: 'Einheiten' },
  },
  {
    path: '/imports',
    name: 'imports',
    component: () => import('@/views/imports/ImportView.vue'),
    meta: { title: 'Import' },
  },
  {
    path: '/exports',
    name: 'exports',
    component: () => import('@/views/exports/ExportView.vue'),
    meta: { title: 'Export' },
  },
  {
    path: '/json-export-import',
    name: 'json-export-import',
    component: () => import('@/views/exports/JsonExportImportView.vue'),
    meta: { title: 'JSON Export/Import' },
  },
  {
    path: '/export-jobs',
    name: 'export-jobs',
    component: () => import('@/views/exports/ExportJobView.vue'),
    meta: { title: 'Export-Jobs' },
  },
  {
    path: '/reports',
    name: 'reports',
    component: () => import('@/views/reports/ReportListView.vue'),
    meta: { title: 'Berichte' },
  },
  {
    path: '/reports/:id',
    name: 'report-designer',
    component: () => import('@/views/reports/ReportDesignerView.vue'),
    meta: { title: 'Bericht-Designer' },
  },
  {
    path: '/media',
    name: 'media',
    component: () => import('@/views/media/MediaView.vue'),
    meta: { title: 'Medien' },
  },
  {
    path: '/media-usage-types',
    name: 'media-usage-types',
    component: () => import('@/views/mediaUsageTypes/MediaUsageTypeView.vue'),
    meta: { title: 'Bildtypen' },
  },
  {
    path: '/prices',
    name: 'prices',
    component: () => import('@/views/prices/PriceView.vue'),
    meta: { title: 'Preise' },
  },
  {
    path: '/users',
    name: 'users',
    component: () => import('@/views/users/UserView.vue'),
    meta: { title: 'Benutzer' },
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/settings/SettingsView.vue'),
    meta: { title: 'Einstellungen' },
  },
  {
    path: '/watchlist',
    name: 'watchlist',
    component: () => import('@/views/watchlist/WatchlistView.vue'),
    meta: { title: 'Merkliste' },
  },
  {
    path: '/help',
    name: 'help',
    component: () => import('@/views/HelpView.vue'),
    meta: { title: 'Hilfe' },
  },
  // --- Public Catalog Preview ---
  {
    path: '/preview',
    component: () => import('@/views/catalog/CatalogLayout.vue'),
    meta: { public: true, title: 'Produktkatalog' },
    children: [
      {
        path: '',
        name: 'catalog',
        component: () => import('@/views/catalog/CatalogView.vue'),
      },
      {
        path: 'product/:id',
        name: 'catalog-product',
        component: () => import('@/views/catalog/CatalogProductView.vue'),
        meta: { public: true, title: 'Produktdetail' },
      },
      {
        path: 'impressum',
        name: 'catalog-impressum',
        component: () => import('@/views/catalog/CatalogLegalPage.vue'),
        meta: { public: true, title: 'Impressum' },
      },
      {
        path: 'kontakt',
        name: 'catalog-kontakt',
        component: () => import('@/views/catalog/CatalogLegalPage.vue'),
        meta: { public: true, title: 'Kontakt' },
      },
    ],
  },
  // --- Public Asset Preview ---
  {
    path: '/assetpreview',
    component: () => import('@/views/assetCatalog/AssetCatalogLayout.vue'),
    meta: { public: true, title: 'Asset-Katalog' },
    children: [
      {
        path: '',
        name: 'asset-catalog',
        component: () => import('@/views/assetCatalog/AssetCatalogView.vue'),
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.VITE_BASE_PATH || '/'),
  routes,
})

// Auth guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.guest || to.meta.public) {
    return next()
  }

  if (!authStore.isAuthenticated && to.name !== 'login') {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  next()
})

// Document title
router.afterEach((to) => {
  const appName = import.meta.env.VITE_APP_NAME || 'anyPIM'
  document.title = to.meta.title ? `${to.meta.title} — ${appName}` : appName
})

export default router
