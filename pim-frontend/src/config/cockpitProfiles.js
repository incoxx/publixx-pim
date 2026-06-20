/**
 * Cockpit-Profile (Phase 2) — rollenabhängige Layouts.
 *
 * Ein Profil beschreibt, welche Schnellaktions-Kacheln (tiles), welche
 * Arbeitsplatz-Widgets (workplace) und welche KPI-Widgets (kpis) im Cockpit
 * erscheinen. Die IDs werden in CockpitView.vue auf konkrete Komponenten/Links
 * gemappt und zusätzlich per Berechtigung gefiltert.
 *
 * Auflösungs-Reihenfolge (wie im Konzept §4.1): persönlich → Rolle → System.
 * Persönliche Layouts und der Admin-Editor folgen in Phase 4; hier liefern wir
 * sinnvolle Rollen-Standards aus Code.
 */

// System-Standard für alle Rollen ohne eigenes Profil.
export const SYSTEM_DEFAULT_PROFILE = {
  tiles: ['products', 'media', 'watchlist', 'reports', 'imports'],
  workplace: ['watchlist', 'notes', 'recent', 'tasks'],
  kpis: ['completeness', 'quality'],
}

// Rollenspezifische Cockpit-Layouts (Primärrolle entscheidet).
export const ROLE_PROFILES = {
  // Fokus Marketing: Medien, Content, Übersetzungen, Ausspielung.
  Marketing: {
    tiles: ['media', 'catalog-templates', 'translation-jobs', 'portals', 'watchlist', 'reports'],
    workplace: ['watchlist', 'notes', 'recent', 'tasks'],
    kpis: ['completeness', 'quality'],
  },
  // Fokus Produktmanagement: Datenpflege, Struktur, Workflow.
  'Product Manager': {
    tiles: ['products', 'search', 'imports', 'hierarchies', 'workflow', 'reports'],
    workplace: ['tasks', 'recent', 'watchlist', 'notes'],
    kpis: ['completeness', 'quality'],
  },
}

/**
 * Wirksames Cockpit-Profil ermitteln.
 * @param {string} roleName  Name der Primärrolle.
 * @param {object|null} personal  Optionales persönliches Profil (Phase 4).
 */
export function resolveCockpitProfile(roleName, personal = null) {
  if (personal && Array.isArray(personal.tiles)) return personal
  return ROLE_PROFILES[roleName] || SYSTEM_DEFAULT_PROFILE
}
