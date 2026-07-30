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
  tiles: ['products', 'preview', 'media', 'watchlist', 'reports', 'imports'],
  workplace: ['watchlist', 'notes', 'recent', 'tasks'],
  content: ['watchlist-media'],
  kpis: ['watchlist-completeness', 'watchlist-quality', 'completeness', 'quality'],
}

// Rollenspezifische Cockpit-Layouts (Primärrolle entscheidet).
export const ROLE_PROFILES = {
  // Fokus Marketing: Medien, Content, Übersetzungen, Ausspielung.
  Marketing: {
    tiles: ['media', 'preview', 'catalog-templates', 'translation-jobs', 'watchlist', 'reports'],
    workplace: ['watchlist', 'notes', 'recent', 'tasks'],
    content: ['watchlist-media', 'translation-status'],
    kpis: ['watchlist-completeness', 'watchlist-quality', 'completeness', 'quality'],
  },
  // Fokus Produktmanagement: Datenpflege, Struktur, Workflow.
  'Product Manager': {
    tiles: ['products', 'preview', 'search', 'imports', 'hierarchies', 'workflow'],
    workplace: ['tasks', 'recent', 'watchlist', 'notes'],
    content: ['watchlist-media'],
    kpis: ['watchlist-completeness', 'watchlist-quality', 'completeness', 'quality'],
  },
}

/**
 * Prüft, ob ein Layout-Objekt einen echten Override darstellt, d. h. mindestens
 * eine Zone mit Inhalt. Ein vollständig leeres Layout (alle Zonen leer) gilt NICHT
 * als Override → es greift der nächste Fallback (Rolle bzw. System).
 */
export function isLayout(obj) {
  if (!obj) return false
  return ['tiles', 'workplace', 'content', 'kpis']
    .some(k => Array.isArray(obj[k]) && obj[k].length > 0)
}

/**
 * Wirksames Cockpit-Profil ermitteln.
 * Auflösung: persönlich → gespeichertes Rollen-Layout (Admin) → Code-Default → System.
 * @param {string} roleName   Name der Primärrolle.
 * @param {object|null} personal   Persönliches Layout (user_preferences 'cockpit').
 * @param {object|null} savedRole  Vom Admin gepflegtes Rollen-Layout (Backend).
 */
export function resolveCockpitProfile(roleName, personal = null, savedRole = null) {
  if (isLayout(personal)) return personal
  if (isLayout(savedRole)) return savedRole
  return ROLE_PROFILES[roleName] || SYSTEM_DEFAULT_PROFILE
}
