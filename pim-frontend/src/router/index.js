import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTabStore } from '@/stores/tabs'
import catalogApi from '@/api/catalog'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    redirect: '/dashboard',
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
    meta: { title: 'Profisuche' },
  },
  {
    path: '/quick-search',
    name: 'quick-search',
    component: () => import('@/views/search/QuickSearchView.vue'),
    meta: { title: 'Schnellsuche' },
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
    meta: { title: 'Produktdetail', tabable: true, tabTitle: 'Produkt' },
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
    path: '/manufacturers',
    name: 'manufacturers',
    component: () => import('@/views/manufacturers/ManufacturerView.vue'),
    meta: { title: 'Hersteller' },
  },
  {
    path: '/product-types',
    name: 'product-types',
    component: () => import('@/views/productTypes/ProductTypeView.vue'),
    meta: { title: 'Produkttypen' },
  },
  {
    path: '/relation-types',
    name: 'relation-types',
    component: () => import('@/views/relationTypes/RelationTypeView.vue'),
    meta: { title: 'Produktbeziehungen' },
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
    path: '/comparison-operators',
    name: 'comparison-operators',
    component: () => import('@/views/comparisonOperators/ComparisonOperatorGroupView.vue'),
    meta: { title: 'Vergleichsoperatoren' },
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
    path: '/bmecat-import-export',
    name: 'bmecat-import-export',
    component: () => import('@/views/imports/BmecatImportExportView.vue'),
    meta: { title: 'BMEcat Import/Export' },
  },
  {
    path: '/export-jobs',
    name: 'export-jobs',
    component: () => import('@/views/exports/ExportJobView.vue'),
    meta: { title: 'Export-Jobs' },
  },
  {
    path: '/attribute-mappings',
    name: 'attribute-mappings',
    component: () => import('@/views/exports/AttributeMappingView.vue'),
    meta: { title: 'Attribut-Mapping' },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: () => import('@/views/calendar/CalendarView.vue'),
    meta: { title: 'Planungskalender' },
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
    meta: { title: 'Bericht-Designer', tabable: true, tabTitle: 'Bericht' },
  },
  {
    path: '/plugin-settings',
    name: 'plugin-settings',
    component: () => import('@/views/connectors/PluginSettingsView.vue'),
    meta: { title: 'Plugin-Einstellungen' },
  },
  {
    path: '/oauth/:provider/callback',
    name: 'oauth-callback',
    component: () => import('@/views/connectors/OAuthCallbackView.vue'),
    meta: { title: 'OAuth Callback', guest: true },
  },
  {
    path: '/connectors/canva',
    name: 'connector-canva',
    component: () => import('@/views/connectors/ConnectorCanvaView.vue'),
    meta: { title: 'Canva' },
  },
  {
    path: '/connectors/canva/export',
    name: 'connector-canva-export',
    component: () => import('@/views/connectors/CanvaExportView.vue'),
    meta: { title: 'Canva Export' },
  },
  {
    path: '/connectors/deepl',
    name: 'connector-deepl',
    component: () => import('@/views/connectors/ConnectorDeepLView.vue'),
    meta: { title: 'DeepL' },
  },
  {
    path: '/connectors/shopware',
    name: 'connector-shopware',
    component: () => import('@/views/connectors/ConnectorShopwareView.vue'),
    meta: { title: 'Shopware' },
  },
  {
    path: '/connectors/shopify',
    name: 'connector-shopify',
    component: () => import('@/views/connectors/ConnectorShopifyView.vue'),
    meta: { title: 'Shopify' },
  },
  {
    path: '/connectors/salesforce-commerce',
    name: 'connector-salesforce-commerce',
    component: () => import('@/views/connectors/ConnectorSalesforceCommerceView.vue'),
    meta: { title: 'Salesforce Commerce Cloud' },
  },
  {
    path: '/connectors/cloudinary',
    name: 'connector-cloudinary',
    component: () => import('@/views/connectors/ConnectorCloudinaryView.vue'),
    meta: { title: 'Cloudinary' },
  },
  {
    path: '/connectors/claude-ai',
    name: 'connector-claude-ai',
    component: () => import('@/views/connectors/ConnectorClaudeAIView.vue'),
    meta: { title: 'Claude AI' },
  },
  {
    path: '/connectors/anypim',
    name: 'connector-anypim',
    component: () => import('@/views/connectors/ConnectorAnyPimView.vue'),
    meta: { title: 'anyPIM Sync' },
  },
  {
    path: '/connectors/api-keys',
    name: 'api-keys',
    component: () => import('@/views/connectors/UserApiKeysView.vue'),
    meta: { title: 'API-Keys' },
  },
  {
    path: '/connectors/:id',
    name: 'connector-connection',
    component: () => import('@/views/connectors/ConnectorConnectionView.vue'),
    meta: { title: 'Connector-Verbindung', tabable: true, tabTitle: 'Connector' },
  },
  {
    path: '/api-designer',
    name: 'api-designer-list',
    component: () => import('@/views/apiDesigner/ApiDesignerListView.vue'),
    meta: { title: 'API-Designer' },
  },
  {
    path: '/api-designer/:id',
    name: 'api-designer-edit',
    component: () => import('@/views/apiDesigner/ApiDesignerView.vue'),
    meta: { title: 'API-Designer', tabable: true, tabTitle: 'API' },
  },
  {
    path: '/excel-designer',
    name: 'excel-designer-list',
    component: () => import('@/views/excelDesigner/ExcelDesignerListView.vue'),
    meta: { title: 'Sheet Designer' },
  },
  {
    path: '/excel-designer/:id',
    name: 'excel-designer-edit',
    component: () => import('@/views/excelDesigner/ExcelDesignerView.vue'),
    meta: { title: 'Sheet Designer', tabable: true, tabTitle: 'Excel' },
  },
  {
    path: '/pdf-templates',
    name: 'pdf-templates',
    component: () => import('@/views/pdf-templates/PdfTemplateListView.vue'),
    meta: { title: 'PDF-Vorlagen' },
  },
  {
    path: '/pdf-templates/:id',
    name: 'pdf-template-designer',
    component: () => import('@/views/pdf-templates/PdfTemplateDesignerView.vue'),
    meta: { title: 'PDF-Vorlage Designer', tabable: true, tabTitle: 'PDF-Vorlage' },
  },
  {
    path: '/catalog-templates',
    name: 'catalog-templates',
    component: () => import('@/views/catalog-templates/CatalogTemplateListView.vue'),
    meta: { title: 'Katalog-Vorlagen' },
  },
  {
    path: '/catalog-templates/:id',
    name: 'catalog-template-designer',
    component: () => import('@/views/catalog-templates/CatalogTemplateDesignerView.vue'),
    meta: { title: 'Katalog-Vorlage Designer', tabable: true, tabTitle: 'Katalog-Vorlage' },
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
    path: '/price-regions',
    name: 'price-regions',
    component: () => import('@/views/priceRegions/PriceRegionView.vue'),
    meta: { title: 'Preisregionen' },
  },
  {
    path: '/translations',
    name: 'translations',
    component: () => import('@/views/translations/TranslationView.vue'),
    meta: { title: 'Translation Memory' },
  },
  {
    path: '/translation-jobs',
    name: 'translation-jobs',
    component: () => import('@/views/translations/TranslationJobsView.vue'),
    meta: { title: 'Übersetzungsjobs' },
  },
  {
    path: '/translation-jobs/create',
    name: 'translation-job-create',
    component: () => import('@/views/translations/TranslationJobCreateView.vue'),
    meta: { title: 'Übersetzungsjob erstellen' },
  },
  {
    path: '/translation-jobs/:id',
    name: 'translation-job-detail',
    component: () => import('@/views/translations/TranslationJobDetailView.vue'),
    meta: { title: 'Übersetzungsjob' },
  },
  {
    path: '/users/audit',
    name: 'user-audit',
    component: () => import('@/views/users/UserAuditView.vue'),
    meta: { title: 'Benutzer-Audit-Trail' },
  },
  {
    path: '/users',
    name: 'users',
    component: () => import('@/views/users/UserView.vue'),
    meta: { title: 'Benutzer' },
  },
  {
    path: '/roles',
    name: 'roles',
    component: () => import('@/views/roles/RoleView.vue'),
    meta: { title: 'Rollen' },
  },
  {
    path: '/access-links',
    name: 'access-links',
    component: () => import('@/views/accessLinks/AccessLinkView.vue'),
    meta: { title: 'Zugangslinks' },
  },
  {
    path: '/access/:token',
    name: 'access-redeem',
    component: () => import('@/views/accessLinks/AccessLinkRedeemView.vue'),
    meta: { guest: true, title: 'Zugang' },
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
    path: '/test-runner',
    name: 'test-runner',
    component: () => import('@/views/admin/TestRunnerView.vue'),
    meta: { title: 'Test anyPIM' },
  },
  {
    path: '/api-tester',
    name: 'api-tester',
    component: () => import('@/views/admin/ApiTesterView.vue'),
    meta: { title: 'API Tester' },
  },
  {
    path: '/db',
    name: 'database-viewer',
    component: () => import('@/views/admin/DatabaseViewerView.vue'),
    meta: { title: 'Datenbank' },
  },
  {
    path: '/db-consistency',
    name: 'database-consistency',
    component: () => import('@/views/admin/DatabaseConsistencyView.vue'),
    meta: { title: 'Datenkonsistenz' },
  },
  {
    path: '/logs',
    name: 'log-viewer',
    component: () => import('@/views/admin/LogViewerView.vue'),
    meta: { title: 'Log Viewer' },
  },
  {
    path: '/admin/license-generator',
    name: 'license-generator',
    component: () => import('@/views/admin/LicenseGeneratorView.vue'),
    meta: { title: 'License Generator' },
  },
  {
    path: '/journal',
    name: 'journal',
    component: () => import('@/views/journal/JournalView.vue'),
    meta: { title: 'Änderungsprotokoll' },
  },
  {
    path: '/workflow',
    name: 'workflow',
    component: () => import('@/views/workflow/WorkflowTaskView.vue'),
    meta: { title: 'Workflow-Aufgaben' },
  },
  {
    path: '/workflow-statuses',
    name: 'workflow-statuses',
    component: () => import('@/views/workflow/WorkflowStatusView.vue'),
    meta: { title: 'Workflow-Status' },
  },
  {
    path: '/workflows',
    name: 'workflows',
    component: () => import('@/views/workflow/WorkflowListView.vue'),
    meta: { title: 'Workflows' },
  },
  {
    path: '/teams',
    name: 'teams',
    component: () => import('@/views/teams/TeamView.vue'),
    meta: { title: 'Teams' },
  },
  {
    path: '/projects',
    name: 'projects',
    component: () => import('@/views/projects/ProjectView.vue'),
    meta: { title: 'Projekte' },
  },
  {
    path: '/project-dashboard',
    name: 'project-dashboard',
    component: () => import('@/views/projects/ProjectDashboardView.vue'),
    meta: { title: 'Projekt-Dashboard' },
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
  // --- Public Document Portal ---
  {
    path: '/documentportal',
    component: () => import('@/views/documentPortal/DocumentPortalLayout.vue'),
    meta: { public: true, title: 'Dokumentenportal' },
    children: [
      {
        path: '',
        name: 'document-portal-landing',
        component: () => import('@/views/documentPortal/DocumentPortalLanding.vue'),
      },
      {
        path: 'search',
        name: 'document-portal-search',
        component: () => import('@/views/documentPortal/DocumentPortalSearch.vue'),
      },
      {
        path: 'product/:id',
        name: 'document-portal-result',
        component: () => import('@/views/documentPortal/DocumentPortalResult.vue'),
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

// Cache catalog access mode to avoid fetching on every navigation
let catalogAccessMode = null

// Auth guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.guest) {
    return next()
  }

  // For public catalog/asset routes, check if login is required
  if (to.meta.public && (to.path.startsWith('/preview') || to.path.startsWith('/assetpreview') || to.path.startsWith('/documentportal'))) {
    if (catalogAccessMode === null) {
      try {
        const { data } = await catalogApi.getSettings()
        catalogAccessMode = data.data?.catalog_access_mode || 'public'
      } catch {
        catalogAccessMode = 'public'
      }
    }
    if (catalogAccessMode === 'login' && !authStore.isAuthenticated) {
      return next({ name: 'login', query: { redirect: to.fullPath } })
    }
    return next()
  }

  if (to.meta.public) {
    return next()
  }

  if (!authStore.isAuthenticated && to.name !== 'login') {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  next()
})

// Reset cached access mode when settings might change (e.g., after login/logout)
router.afterEach((to) => {
  if (to.name === 'settings' || to.name === 'login') {
    catalogAccessMode = null
  }
})

// Open tabable routes as tabs
router.afterEach((to) => {
  const tabStore = useTabStore()
  if (to.meta.tabable && to.params.id) {
    tabStore.openTab(to)
  } else {
    tabStore.setActiveByRoute(to)
  }
})

// Document title
router.afterEach((to) => {
  const appName = import.meta.env.VITE_APP_NAME || 'anyPIM'
  document.title = to.meta.title ? `${to.meta.title} — ${appName}` : appName
})

export default router
