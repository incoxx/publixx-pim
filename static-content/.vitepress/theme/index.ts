import { h, onMounted, onUpdated } from 'vue'
import DefaultTheme from 'vitepress/theme'
import { inBrowser } from 'vitepress'
import type { Theme } from 'vitepress'
import './style.css'

// Marker-Href fuer den "PIM oeffnen" / "Open PIM"-Link in der Nav (siehe config.ts).
// Die App-Wurzel haengt vom individuellen Installationspfad ab (Root-Modus "/" oder
// Unterverzeichnis-Modus z.B. "/pim/") -- siehe setup.sh/update.sh: Apache-Alias
// "${WEB_PATH}/help" fuer die Doku, "${WEB_PATH}" fuer die App selbst. Der Link wird
// daher client-seitig aus der aktuellen URL berechnet statt fest verdrahtet zu sein
// (weder Domain noch Installationspfad sind hier hardcodiert).
const APP_ROOT_MARKER = '#app-root-link'

function resolveAppRootLinks() {
  if (!inBrowser) return
  const path = window.location.pathname
  const idx = path.indexOf('/help/')
  const appBase = idx >= 0 ? path.slice(0, idx) : ''
  const target = `${appBase}/`
  document.querySelectorAll(`a[href="${APP_ROOT_MARKER}"]`).forEach((el) => {
    el.setAttribute('href', target)
  })
}

export default {
  extends: DefaultTheme,
  Layout() {
    onMounted(resolveAppRootLinks)
    onUpdated(resolveAppRootLinks)
    return h(DefaultTheme.Layout)
  },
} satisfies Theme
