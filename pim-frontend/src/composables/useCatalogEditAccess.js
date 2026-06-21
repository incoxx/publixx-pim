import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Editier-Berechtigung im Katalog.
 *
 * Editieren ist NUR in der internen Vorschau-Route `/preview` erlaubt — niemals im
 * öffentlichen `catalog-embed` (separater UMD-Build ohne diese Route). Zusätzlich
 * müssen Nutzer:innen angemeldet sein und `products.edit` besitzen.
 */
export function useCatalogEditAccess() {
  const authStore = useAuthStore()
  // useRoute() liefert im Embed-Build (ohne vue-router) undefined → Editieren aus.
  const route = useRoute()

  return computed(() =>
    !!route
    && (route.path || '').startsWith('/preview')
    && authStore.isAuthenticated
    && authStore.hasPermission('products.edit')
  )
}
