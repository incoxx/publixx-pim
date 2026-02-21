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
    path: '/value-lists',
    name: 'value-lists',
    component: () => import('@/views/valueLists/ValueListView.vue'),
    meta: { title: 'Wertelisten' },
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
    path: '/media',
    name: 'media',
    component: () => import('@/views/media/MediaView.vue'),
    meta: { title: 'Medien' },
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
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
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
  const appName = import.meta.env.VITE_APP_NAME || 'Publixx PIM'
  document.title = to.meta.title ? `${to.meta.title} — ${appName}` : appName
})

export default router
