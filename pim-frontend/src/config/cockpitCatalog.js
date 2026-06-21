import { markRaw } from 'vue'
import {
  Package, Image as ImageIcon, Star, FileBarChart, Upload, Languages,
  LayoutTemplate, Globe, GitBranch, ClipboardList, Search, LayoutGrid,
} from 'lucide-vue-next'
import NotesWidget from '@/components/dashboard/NotesWidget.vue'
import WatchlistWidget from '@/components/dashboard/WatchlistWidget.vue'
import RecentlyEditedWidget from '@/components/dashboard/RecentlyEditedWidget.vue'
import MyTasksWidget from '@/components/dashboard/MyTasksWidget.vue'
import CompletenessWidget from '@/components/dashboard/CompletenessWidget.vue'
import DataQualityWidget from '@/components/dashboard/DataQualityWidget.vue'
import MediaSpotlightWidget from '@/components/dashboard/MediaSpotlightWidget.vue'
import TranslationStatusWidget from '@/components/dashboard/TranslationStatusWidget.vue'
import WatchlistCompletenessWidget from '@/components/dashboard/WatchlistCompletenessWidget.vue'
import WatchlistDataQualityWidget from '@/components/dashboard/WatchlistDataQualityWidget.vue'

/**
 * Gemeinsamer Katalog für Cockpit-Bausteine.
 * Wird vom Cockpit (Rendering) und vom Admin-Editor (Auswahl/Labels) genutzt.
 */

// Schnellaktions-Kacheln: id → { label, to, icon, permission }
export const TILE_CATALOG = {
  products:            { label: 'Produkte',      to: '/products',          icon: markRaw(Package),        permission: 'products.view' },
  preview:             { label: 'Vorschau-Katalog', to: '/preview',        icon: markRaw(LayoutGrid),     permission: 'products.view' },
  media:               { label: 'Medien',        to: '/media',             icon: markRaw(ImageIcon),      permission: 'media.view' },
  watchlist:           { label: 'Merkliste',     to: '/watchlist',         icon: markRaw(Star),           permission: null },
  reports:             { label: 'Berichte',      to: '/reports',           icon: markRaw(FileBarChart),   permission: 'reports.view' },
  imports:             { label: 'Import',        to: '/imports',           icon: markRaw(Upload),         permission: 'imports.view' },
  'translation-jobs':  { label: 'Übersetzungen', to: '/translation-jobs',  icon: markRaw(Languages),      permission: 'translations.view' },
  'catalog-templates': { label: 'Kataloge',      to: '/catalog-templates', icon: markRaw(LayoutTemplate), permission: 'catalog-templates.view' },
  portals:             { label: 'Portale',       to: '/portal-config',     icon: markRaw(Globe),          permission: 'portals.view' },
  search:              { label: 'Profisuche',    to: '/search',            icon: markRaw(Search),         permission: 'search.view' },
  hierarchies:         { label: 'Hierarchien',   to: '/hierarchies',       icon: markRaw(GitBranch),      permission: 'hierarchies.view' },
  workflow:            { label: 'Workflow',      to: '/workflow',          icon: markRaw(ClipboardList),  permission: 'workflow.view' },
}

// Widgets: id → { label, component, permission, zone }
// zone steuert, in welcher Editor-Sektion das Widget angeboten wird.
export const WIDGET_CATALOG = {
  watchlist:            { label: 'Merkliste',            component: markRaw(WatchlistWidget),         permission: null,               zone: 'workplace' },
  notes:                { label: 'Notizen',              component: markRaw(NotesWidget),             permission: null,               zone: 'workplace' },
  recent:               { label: 'Zuletzt bearbeitet',   component: markRaw(RecentlyEditedWidget),    permission: null,               zone: 'workplace' },
  tasks:                { label: 'Meine Aufgaben',       component: markRaw(MyTasksWidget),           permission: null,               zone: 'workplace' },
  'media-spotlight':    { label: 'Medien-Spotlight (alle)', component: markRaw(MediaSpotlightWidget),  permission: 'media.view',       zone: 'content' },
  'watchlist-media':    { label: 'Medien (Merkliste)',    component: markRaw(MediaSpotlightWidget),    permission: 'watchlist.view',   zone: 'content' },
  'translation-status': { label: 'Übersetzungs-Status',  component: markRaw(TranslationStatusWidget), permission: 'translations.view', zone: 'content' },
  completeness:         { label: 'Produkt-Füllstand',    component: markRaw(CompletenessWidget),      permission: null,               zone: 'kpis' },
  'watchlist-completeness': { label: 'Füllstand (Merkliste)', component: markRaw(WatchlistCompletenessWidget), permission: 'watchlist.view', zone: 'kpis' },
  'watchlist-quality':  { label: 'Datenqualität (Merkliste)', component: markRaw(WatchlistDataQualityWidget), permission: 'watchlist.view', zone: 'kpis' },
  quality:              { label: 'Datenqualität',        component: markRaw(DataQualityWidget),       permission: null,               zone: 'kpis' },
}

// Widget-IDs einer Zone (für den Editor)
export function widgetsForZone(zone) {
  return Object.entries(WIDGET_CATALOG)
    .filter(([, w]) => w.zone === zone)
    .map(([id, w]) => ({ id, label: w.label }))
}

// Kachel-IDs (für den Editor)
export function allTiles() {
  return Object.entries(TILE_CATALOG).map(([id, t]) => ({ id, label: t.label }))
}
