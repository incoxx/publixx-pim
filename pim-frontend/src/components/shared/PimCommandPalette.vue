<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import Fuse from 'fuse.js'
import {
  Search, Package, GitBranch, Sliders, Database, Layers, FolderTree,
  Upload, Download, Image, Tags, DollarSign, Users, Settings, Shield,
  HelpCircle, Star, LayoutGrid, Ruler, Plus,
  FileJson, FileCode, PlayCircle, FileBarChart, FileText, BookOpen, Link2, Zap, Languages, LayoutTemplate,
  Factory, CalendarDays, ScrollText, Globe, ExternalLink,
  LayoutDashboard, ClipboardList, Code, ArrowRight, ArrowRightLeft,
  FlaskConical, FileSpreadsheet, Plug, Wand2, Fingerprint,
} from 'lucide-vue-next'

const router = useRouter()
const { t } = useI18n()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const inputRef = ref(null)
const query = ref('')
const selectedIndex = ref(0)
const listRef = ref(null)

// ─── Recent items (localStorage) ──────────────────────
const RECENT_KEY = 'pim_cmd_recent'
const MAX_RECENT = 5

function getRecentIds() {
  try {
    return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]')
  } catch { return [] }
}

function pushRecent(id) {
  const ids = getRecentIds().filter(i => i !== id)
  ids.unshift(id)
  localStorage.setItem(RECENT_KEY, JSON.stringify(ids.slice(0, MAX_RECENT)))
}

// ─── Full search index ────────────────────────────────
const allItems = computed(() => {
  const items = [
    // ── Navigation: Täglich ──
    {
      id: 'nav-dashboard', icon: LayoutDashboard,
      label: t('Dashboard'),
      description: t('cmd.desc.dashboard'),
      keywords: ['dashboard', 'startseite', 'übersicht', 'home', 'start', 'statistik', 'overview'],
      section: 'navigation', action: () => router.push('/dashboard'),
      permission: 'dashboard.view',
    },
    {
      id: 'nav-cockpit', icon: LayoutDashboard,
      label: t('Cockpit'),
      description: t('cmd.desc.cockpit'),
      keywords: ['cockpit', 'fokus', 'fokus-modus', 'arbeitsplatz', 'startseite', 'übersicht', 'workspace'],
      section: 'navigation', action: () => router.push('/cockpit'),
      permission: 'dashboard.view',
    },
    {
      id: 'nav-cockpit-editor', icon: Settings,
      label: t('Cockpit-Layouts'),
      description: t('cmd.desc.cockpitLayouts'),
      keywords: ['cockpit', 'cockpit konfiguration', 'cockpit-konfiguration', 'layout', 'layouts', 'kacheln', 'widgets', 'rollen', 'konfigurieren', 'einstellungen', 'editor', 'anpassen'],
      section: 'navigation', action: () => router.push('/cockpit-editor'),
      permission: 'cockpit-layouts.view',
    },
    {
      id: 'nav-preview', icon: LayoutGrid,
      label: t('Katalog-Vorschau'),
      description: t('cmd.desc.catalogPreview'),
      keywords: ['preview', 'vorschau', 'katalog', 'katalog-vorschau', 'catalog', 'ansehen', 'demo'],
      section: 'navigation', action: () => router.push('/preview'),
      permission: 'preview.view',
    },
    {
      id: 'nav-quick-search', icon: Zap,
      label: t('nav.quickSearch'),
      description: t('cmd.desc.quickSearch'),
      keywords: ['schnellsuche', 'quick search', 'instant', 'sofort', 'schnell', 'fast', 'google', 'finden', 'suche'],
      section: 'navigation', action: () => router.push('/quick-search'),
      permission: 'search.view',
    },
    {
      id: 'nav-search', icon: Search,
      label: t('nav.search'),
      description: t('cmd.desc.search'),
      keywords: ['profisuche', 'suche', 'search', 'finden', 'find', 'pql', 'query', 'abfrage', 'filtern', 'erweitert'],
      section: 'navigation', action: () => router.push('/search'),
      permission: 'search.view',
    },
    {
      id: 'nav-products', icon: Package,
      label: t('nav.products'),
      description: t('cmd.desc.products'),
      keywords: ['produkt', 'product', 'artikel', 'item', 'ware', 'produkte', 'produktliste', 'sku'],
      section: 'navigation', action: () => router.push('/products'),
      permission: 'products.view',
    },
    {
      id: 'nav-watchlist', icon: Star,
      label: t('Merkliste'),
      description: t('cmd.desc.watchlist'),
      keywords: ['merkliste', 'watchlist', 'favorit', 'bookmark', 'gemerkt', 'vorgemerkt'],
      section: 'navigation', action: () => router.push('/watchlist'),
      permission: 'watchlist.view',
    },
    {
      id: 'nav-workflow', icon: ClipboardList,
      label: t('Workflow'),
      description: t('cmd.desc.workflow'),
      keywords: ['workflow', 'aufgabe', 'task', 'freigabe', 'approval', 'todo', 'genehmigung'],
      section: 'navigation', action: () => router.push('/workflow'),
      permission: 'workflow.view',
    },
    {
      id: 'nav-hierarchies', icon: GitBranch,
      label: t('nav.hierarchies'),
      description: t('cmd.desc.hierarchies'),
      keywords: ['hierarchie', 'hierarchy', 'kategorie', 'category', 'baum', 'tree', 'struktur', 'navigation', 'zuordnung'],
      section: 'navigation', action: () => router.push('/hierarchies'),
      permission: 'hierarchies.view',
    },
    {
      id: 'nav-media', icon: Image,
      label: t('nav.media'),
      description: t('cmd.desc.media'),
      keywords: ['medien', 'media', 'bild', 'image', 'foto', 'photo', 'datei', 'file', 'dokument', 'document', 'asset', 'upload'],
      section: 'navigation', action: () => router.push('/media'),
      permission: 'media.view',
    },
    {
      id: 'nav-media-usage-types', icon: Tags,
      label: t('nav.mediaUsageTypes'),
      description: t('cmd.desc.mediaUsageTypes'),
      keywords: ['bildtyp', 'image type', 'usage', 'verwendung', 'kanal', 'channel', 'web', 'print'],
      section: 'navigation', action: () => router.push('/media-usage-types'),
      permission: 'media-usage-types.view',
    },
    {
      id: 'nav-reports', icon: FileBarChart,
      label: t('Berichte'),
      description: t('cmd.desc.reports'),
      keywords: ['bericht', 'report', 'auswertung', 'analyse', 'analysis', 'statistik', 'chart'],
      section: 'navigation', action: () => router.push('/reports'),
      module: 'reports', permission: 'reports.view',
    },
    {
      id: 'nav-pdf-templates', icon: FileText,
      label: t('PDF-Vorlagen'),
      description: t('cmd.desc.pdfTemplates'),
      keywords: ['pdf', 'vorlage', 'template', 'datenblatt', 'datasheet', 'drucken', 'print'],
      section: 'navigation', action: () => router.push('/pdf-templates'),
      module: 'pdf_templates', permission: 'pdf-templates.view',
    },
    {
      id: 'nav-catalog-templates', icon: LayoutTemplate,
      label: t('Katalog-Vorlagen'),
      description: t('cmd.desc.catalogTemplates'),
      keywords: ['katalog', 'catalog', 'vorlage', 'template', 'layout', 'design'],
      section: 'navigation', action: () => router.push('/catalog-templates'),
    },
    {
      id: 'nav-api-designer', icon: Code,
      label: t('API-Designer'),
      description: t('cmd.desc.apiDesigner'),
      keywords: ['api', 'designer', 'endpoint', 'schnittstelle', 'rest', 'json'],
      section: 'navigation', action: () => router.push('/api-designer'),
      module: 'api_designer', permission: 'api-templates.view',
    },
    {
      id: 'nav-calendar', icon: CalendarDays,
      label: t('Planungskalender'),
      description: t('cmd.desc.calendar'),
      keywords: ['kalender', 'calendar', 'planung', 'schedule', 'termin', 'datum', 'zeitplan'],
      section: 'navigation', action: () => router.push('/calendar'),
      permission: 'calendar.view',
    },

    // ── Navigation: Projektmanagement ──
    {
      id: 'nav-project-dashboard', icon: LayoutDashboard,
      label: t('Projekt-Dashboard'),
      description: t('cmd.desc.projectDashboard'),
      keywords: ['projekt', 'project', 'dashboard', 'übersicht', 'fortschritt', 'progress'],
      section: 'navigation', action: () => router.push('/project-dashboard'),
      permission: 'dashboard.view',
    },
    {
      id: 'nav-workflows', icon: GitBranch,
      label: t('Workflows'),
      description: t('cmd.desc.workflows'),
      keywords: ['workflow', 'prozess', 'process', 'regel', 'rule', 'automatisierung', 'automation'],
      section: 'navigation', action: () => router.push('/workflows'),
      permission: 'workflows.view',
    },
    {
      id: 'nav-workflow-statuses', icon: Zap,
      label: t('Workflow-Status'),
      description: t('cmd.desc.workflowStatuses'),
      keywords: ['workflow', 'status', 'zustand', 'state', 'phase'],
      section: 'navigation', action: () => router.push('/workflow-statuses'),
      permission: 'workflow-statuses.view',
    },
    {
      id: 'nav-teams', icon: Users,
      label: t('Teams'),
      description: t('cmd.desc.teams'),
      keywords: ['team', 'gruppe', 'group', 'mitarbeiter', 'member'],
      section: 'navigation', action: () => router.push('/teams'),
      permission: 'teams.view',
    },
    {
      id: 'nav-projects', icon: FolderTree,
      label: t('Projekte'),
      description: t('cmd.desc.projects'),
      keywords: ['projekt', 'project', 'vorhaben', 'aufgabe'],
      section: 'navigation', action: () => router.push('/projects'),
      permission: 'projects.view',
    },

    // ── Plugins & Connectoren ──
    {
      id: 'nav-plugin-settings', icon: Settings,
      label: t('Plugin-Einstellungen'),
      description: t('cmd.desc.pluginSettings'),
      keywords: ['plugin', 'einstellung', 'setting', 'erweiterung', 'extension', 'addon'],
      section: 'config', action: () => router.push('/plugin-settings'),
      permission: 'users.view',
    },
    {
      id: 'nav-connector-shopware', icon: Package,
      label: t('Shopware'),
      description: t('cmd.desc.connectorShopware'),
      keywords: ['shopware', 'shop', 'connector', 'ecommerce', 'onlineshop'],
      section: 'config', action: () => router.push('/connectors/shopware'),
      module: 'connectors',
    },
    {
      id: 'nav-connector-claude-ai', icon: Zap,
      label: t('Claude AI'),
      description: t('cmd.desc.connectorClaudeAi'),
      keywords: ['claude', 'ai', 'ki', 'anthropic', 'connector', 'text', 'generierung', 'künstliche intelligenz'],
      section: 'config', action: () => router.push('/connectors/claude-ai'),
      module: 'connectors',
    },
    {
      id: 'nav-connector-deepl', icon: Languages,
      label: t('DeepL Einstellungen'),
      description: t('cmd.desc.connectorDeepl'),
      keywords: ['deepl', 'übersetzen', 'translate', 'connector', 'sprache', 'automatisch'],
      section: 'config', action: () => router.push('/connectors/deepl'),
      module: 'connectors',
    },

    // ── Konfiguration ──
    {
      id: 'cfg-manufacturers', icon: Factory,
      label: t('nav.manufacturers'),
      description: t('cmd.desc.manufacturers'),
      keywords: ['hersteller', 'manufacturer', 'marke', 'brand', 'lieferant', 'supplier'],
      section: 'config', action: () => router.push('/manufacturers'),
      permission: 'manufacturers.view',
    },
    {
      id: 'cfg-product-types', icon: Layers,
      label: t('nav.productTypes'),
      description: t('cmd.desc.productTypes'),
      keywords: ['produkttyp', 'product type', 'schablone', 'template', 'typ', 'type', 'kategorie'],
      section: 'config', action: () => router.push('/product-types'),
      permission: 'product-types.view',
    },
    {
      id: 'cfg-relation-types', icon: Link2,
      label: t('nav.relationTypes'),
      description: t('cmd.desc.relationTypes'),
      keywords: ['beziehung', 'relation', 'verknüpfung', 'link', 'cross-sell', 'upsell', 'zubehör', 'accessory'],
      section: 'config', action: () => router.push('/relation-types'),
      permission: 'relation-types.view',
    },
    {
      id: 'cfg-attribute-views', icon: LayoutGrid,
      label: t('nav.attributeViews'),
      description: t('cmd.desc.attributeViews'),
      keywords: ['attribut-sicht', 'attribute view', 'sicht', 'view', 'ansicht', 'rolle', 'role'],
      section: 'config', action: () => router.push('/attribute-views'),
      permission: 'attribute-views.view',
    },
    {
      id: 'cfg-attribute-types', icon: FolderTree,
      label: t('nav.attributeTypes'),
      description: t('cmd.desc.attributeTypes'),
      keywords: ['attributgruppe', 'attribute group', 'gruppe', 'group', 'kategorie', 'strukturierung'],
      section: 'config', action: () => router.push('/attribute-types'),
      permission: 'attribute-types.view',
    },
    {
      id: 'cfg-attributes', icon: Sliders,
      label: t('nav.attributes'),
      description: t('cmd.desc.attributes'),
      keywords: ['attribut', 'attribute', 'eigenschaft', 'property', 'merkmal', 'feld', 'field', 'stammdaten', 'master data'],
      section: 'config', action: () => router.push('/attributes'),
      permission: 'attributes.view',
    },
    {
      id: 'cfg-value-lists', icon: Database,
      label: t('nav.valueLists'),
      description: t('cmd.desc.valueLists'),
      keywords: ['werteliste', 'value list', 'auswahl', 'dropdown', 'select', 'option', 'liste', 'list', 'enum'],
      section: 'config', action: () => router.push('/value-lists'),
      permission: 'value-lists.view',
    },
    {
      id: 'cfg-formatting-rules', icon: Wand2,
      label: t('nav.formattingRules'),
      description: t('cmd.desc.formattingRules'),
      keywords: ['formatierung', 'formatting', 'uppercase', 'regex', 'zahlenformat', 'number format', 'großbuchstaben'],
      section: 'config', action: () => router.push('/formatting-rules'),
      permission: 'attribute-formatting-rules.view',
    },
    {
      id: 'cfg-attribute-metadata', icon: Fingerprint,
      label: t('nav.attributeMetadata'),
      description: t('cmd.desc.attributeMetadata'),
      keywords: ['metadaten', 'metadata', 'datenherkunft', 'data origin', 'dateneigentümer', 'owner', 'ownership', 'governance', 'datenqualität', 'data quality', 'datenverbindung'],
      section: 'config', action: () => router.push('/attribute-metadata'),
      permission: 'attribute-metadata.view',
    },
    {
      id: 'cfg-dictionary', icon: BookOpen,
      label: t('nav.dictionary'),
      description: t('cmd.desc.dictionary'),
      keywords: ['wörterbuch', 'dictionary', 'glossar', 'glossary', 'begriff', 'term', 'übersetzung', 'translation'],
      section: 'config', action: () => router.push('/dictionary'),
      permission: 'dictionary.view',
    },
    {
      id: 'cfg-units', icon: Ruler,
      label: t('Einheiten'),
      description: t('cmd.desc.units'),
      keywords: ['einheit', 'unit', 'maß', 'measurement', 'kg', 'mm', 'cm', 'liter', 'meter', 'stück'],
      section: 'config', action: () => router.push('/units'),
      permission: 'units.view',
    },
    {
      id: 'cfg-comparison-operators', icon: ArrowRightLeft,
      label: t('Vergleichsoperatoren'),
      description: t('cmd.desc.comparisonOperators'),
      keywords: ['vergleich', 'comparison', 'operator', 'größer', 'kleiner', 'gleich', 'filter'],
      section: 'config', action: () => router.push('/comparison-operators'),
      permission: 'units.view',
    },
    {
      id: 'cfg-prices', icon: DollarSign,
      label: t('nav.prices'),
      description: t('cmd.desc.prices'),
      keywords: ['preis', 'price', 'kosten', 'cost', 'betrag', 'amount', 'währung', 'currency', 'eur', 'euro'],
      section: 'config', action: () => router.push('/prices'),
      permission: 'prices.view',
    },
    {
      id: 'cfg-price-regions', icon: Globe,
      label: t('nav.priceRegions'),
      description: t('cmd.desc.priceRegions'),
      keywords: ['preisregion', 'price region', 'region', 'zone', 'markt', 'market', 'land', 'country'],
      section: 'config', action: () => router.push('/price-regions'),
      permission: 'price-regions.view',
    },
    {
      id: 'cfg-translations', icon: Languages,
      label: t('Translation Memory'),
      description: t('cmd.desc.translations'),
      keywords: ['übersetzung', 'translation', 'sprache', 'language', 'i18n', 'mehrsprachig', 'multilingual', 'lokalisierung', 'tms', 'memory'],
      section: 'config', action: () => router.push('/translations'),
      permission: 'translations.view',
    },
    {
      id: 'cfg-translation-jobs', icon: Languages,
      label: t('Übersetzungsjobs'),
      description: t('cmd.desc.translationJobs'),
      keywords: ['übersetzungsjob', 'translation job', 'deepl', 'übersetzen', 'translate', 'batch'],
      section: 'config', action: () => router.push('/translation-jobs'),
      permission: 'translations.view',
    },

    // ── Administration ──
    {
      id: 'adm-imports', icon: Upload,
      label: t('nav.imports'),
      description: t('cmd.desc.imports'),
      keywords: ['import', 'hochladen', 'upload', 'einlesen', 'csv', 'excel', 'xlsx', 'daten laden', 'datei'],
      section: 'admin', action: () => router.push('/imports'),
      permission: 'imports.view',
    },
    {
      id: 'adm-exports', icon: Download,
      label: t('nav.exports'),
      description: t('cmd.desc.exports'),
      keywords: ['export', 'herunterladen', 'download', 'ausgeben', 'csv', 'excel', 'xlsx', 'daten'],
      section: 'admin', action: () => router.push('/exports'),
      permission: 'export.view',
    },
    {
      id: 'adm-json-export-import', icon: FileJson,
      label: t('JSON Export/Import'),
      description: t('cmd.desc.jsonExportImport'),
      keywords: ['json', 'backup', 'sicherung', 'restore', 'wiederherstellen', 'konfiguration', 'config'],
      section: 'admin', action: () => router.push('/json-export-import'),
      permission: 'json-export-import.view',
    },
    {
      id: 'adm-bmecat', icon: FileCode,
      label: t('BMEcat Import/Export'),
      description: t('cmd.desc.bmecatExportImport'),
      keywords: ['bmecat', 'bme', 'standard', 'etim', 'eclass', 'klassifikation'],
      section: 'admin', action: () => router.push('/bmecat-import-export'),
      module: 'bmecat', permission: 'bmecat.view',
    },
    {
      id: 'adm-excel-designer', icon: FileSpreadsheet,
      label: t('Sheet Designer'),
      description: t('cmd.desc.excelDesigner'),
      keywords: ['excel', 'sheet', 'designer', 'vorlage', 'template', 'spalte', 'column', 'xlsx', 'tabelle'],
      section: 'admin', action: () => router.push('/excel-designer'),
      module: 'excel_designer', permission: 'export.view',
    },
    {
      id: 'adm-export-jobs', icon: PlayCircle,
      label: t('Export-Jobs'),
      description: t('cmd.desc.exportJobs'),
      keywords: ['export-job', 'export job', 'automatisch', 'automatic', 'zeitgesteuert', 'scheduled', 'cron'],
      section: 'admin', action: () => router.push('/export-jobs'),
      module: 'advanced_export', permission: 'export-jobs.view',
    },
    {
      id: 'adm-attribute-mappings', icon: ArrowRightLeft,
      label: t('Attribut-Mapping'),
      description: t('cmd.desc.attributeMappings'),
      keywords: ['attribut-mapping', 'mapping', 'zuordnung', 'quelle', 'ziel', 'etim', 'eclass', 'transformation'],
      section: 'admin', action: () => router.push('/attribute-mappings'),
      permission: 'attribute-mappings.view',
    },
    {
      id: 'adm-catalog-demo', icon: ExternalLink,
      label: t('Katalog-Demo'),
      description: t('cmd.desc.catalogDemo'),
      keywords: ['katalog', 'catalog', 'demo', 'vorschau', 'preview', 'öffentlich', 'public', 'shop'],
      section: 'admin', action: () => { window.open('/catalog-embed', '_blank') },
    },
    {
      id: 'adm-settings', icon: Settings,
      label: t('nav.settings'),
      description: t('cmd.desc.settings'),
      keywords: ['einstellung', 'setting', 'config', 'konfiguration', 'option', 'sprache', 'language', 'darstellung', 'theme', 'system'],
      section: 'admin', action: () => router.push('/settings'),
      permission: 'settings.view',
    },
    {
      id: 'adm-users', icon: Users,
      label: t('nav.users'),
      description: t('cmd.desc.users'),
      keywords: ['benutzer', 'user', 'konto', 'account', 'zugang', 'access', 'login', 'passwort', 'password'],
      section: 'admin', action: () => router.push('/users'),
      permission: 'users.view',
    },
    {
      id: 'adm-user-audit', icon: ScrollText,
      label: t('Benutzer-Audit'),
      description: t('cmd.desc.userAudit'),
      keywords: ['audit', 'aktivität', 'activity', 'login', 'protokoll', 'log', 'sicherheit', 'security'],
      section: 'admin', action: () => router.push('/users/audit'),
      permission: 'users.view',
    },
    {
      id: 'adm-roles', icon: Shield,
      label: t('Rollen'),
      description: t('cmd.desc.roles'),
      keywords: ['rolle', 'role', 'berechtigung', 'permission', 'recht', 'right', 'zugriff', 'access'],
      section: 'admin', action: () => router.push('/roles'),
      permission: 'roles.view',
    },
    {
      id: 'adm-access-links', icon: Link2,
      label: t('Zugangslinks'),
      description: t('cmd.desc.accessLinks'),
      keywords: ['zugangslink', 'access link', 'einladung', 'invitation', 'extern', 'external', 'teilen', 'share'],
      section: 'admin', action: () => router.push('/access-links'),
      permission: 'access-links.manage',
    },
    {
      id: 'adm-api-tester', icon: Zap,
      label: t('API Tester'),
      description: t('cmd.desc.apiTester'),
      keywords: ['api', 'tester', 'test', 'endpoint', 'request', 'debug'],
      section: 'admin', action: () => router.push('/api-tester'),
      permission: 'users.view',
    },
    {
      id: 'adm-test-runner', icon: FlaskConical,
      label: t('Test anyPIM'),
      description: t('cmd.desc.testRunner'),
      keywords: ['test', 'testen', 'prüfen', 'check', 'validierung', 'integration', 'unit'],
      section: 'admin', action: () => router.push('/test-runner'),
      permission: 'users.view',
    },
    {
      id: 'adm-database', icon: Database,
      label: t('Datenbank'),
      description: t('cmd.desc.database'),
      keywords: ['datenbank', 'database', 'db', 'tabelle', 'table', 'sql', 'schema'],
      section: 'admin', action: () => router.push('/db'),
      permission: 'users.view',
    },
    {
      id: 'adm-db-consistency', icon: Shield,
      label: t('Datenkonsistenz'),
      description: t('cmd.desc.dbConsistency'),
      keywords: ['konsistenz', 'consistency', 'prüfung', 'check', 'reparatur', 'repair', 'integrität', 'integrity', 'orphan'],
      section: 'admin', action: () => router.push('/db-consistency'),
      permission: 'users.view',
    },
    {
      id: 'adm-logs', icon: FileText,
      label: t('Log Viewer'),
      description: t('cmd.desc.logs'),
      keywords: ['log', 'logs', 'fehler', 'error', 'protokoll', 'debug', 'viewer', 'laravel'],
      section: 'admin', action: () => router.push('/logs'),
      permission: 'users.view',
    },
    {
      id: 'adm-journal', icon: ScrollText,
      label: t('Journal'),
      description: t('cmd.desc.journal'),
      keywords: ['journal', 'änderung', 'change', 'protokoll', 'log', 'history', 'historie', 'verlauf'],
      section: 'admin', action: () => router.push('/journal'),
      permission: 'journal.view',
    },
    {
      id: 'nav-help', icon: HelpCircle,
      label: t('nav.help'),
      description: t('cmd.desc.help'),
      keywords: ['hilfe', 'help', 'dokumentation', 'documentation', 'anleitung', 'guide', 'faq', 'support'],
      section: 'navigation', action: () => router.push('/help'),
    },

    // ── Aktionen ──
    {
      id: 'act-new-product', icon: Plus,
      label: t('product.newProduct'),
      description: t('cmd.desc.newProduct'),
      keywords: ['neues produkt', 'new product', 'anlegen', 'create', 'erstellen', 'hinzufügen', 'add'],
      section: 'actions', action: () => router.push('/products?new=1'),
    },
    {
      id: 'act-new-attribute', icon: Plus,
      label: t('attribute.newAttribute'),
      description: t('cmd.desc.newAttribute'),
      keywords: ['neues attribut', 'new attribute', 'anlegen', 'create', 'erstellen', 'eigenschaft', 'merkmal'],
      section: 'actions', action: () => router.push('/attributes?new=1'),
    },
    {
      id: 'act-upload', icon: Upload,
      label: t('import.uploadFile'),
      description: t('cmd.desc.uploadFile'),
      keywords: ['hochladen', 'upload', 'datei', 'file', 'import', 'csv', 'excel'],
      section: 'actions', action: () => router.push('/imports'),
    },
  ]

  // Filter by permission and module license
  return items.filter(item =>
    (!item.permission || authStore.hasPermission(item.permission))
    && (!item.module || licenseStore.isModuleActive(item.module))
  )
})

// ─── Fuse.js instance ─────────────────────────────────
const fuse = computed(() => new Fuse(allItems.value, {
  keys: [
    { name: 'label', weight: 1.0 },
    { name: 'keywords', weight: 0.8 },
    { name: 'description', weight: 0.4 },
  ],
  threshold: 0.4,
  ignoreLocation: true,
  includeMatches: true,
  minMatchCharLength: 2,
}))

// ─── Filtered results ─────────────────────────────────
const filtered = computed(() => {
  const q = query.value.trim()
  if (!q) {
    // Show recent items, then everything else
    const recentIds = getRecentIds()
    if (recentIds.length > 0) {
      const recentItems = recentIds
        .map(id => allItems.value.find(i => i.id === id))
        .filter(Boolean)
        .map(item => ({ item, matches: null, isRecent: true }))
      return recentItems
    }
    // No recents: show all items
    return allItems.value.map(item => ({ item, matches: null, isRecent: false }))
  }
  return fuse.value.search(q).slice(0, 12).map(r => ({
    item: r.item,
    matches: r.matches,
    isRecent: false,
  }))
})

const flatList = computed(() => filtered.value)

const groupedResults = computed(() => {
  const groups = {}
  for (const entry of filtered.value) {
    const key = entry.isRecent ? 'recent' : entry.item.section
    if (!groups[key]) groups[key] = []
    groups[key].push(entry)
  }
  return groups
})

const sectionLabels = computed(() => ({
  navigation: t('cmd.sections.navigation'),
  actions: t('cmd.sections.actions'),
  recent: t('cmd.recentlyUsed'),
  config: t('cmd.sections.config'),
  admin: t('cmd.sections.admin'),
  settings: t('cmd.sections.settings'),
}))

// ─── Open/close logic ─────────────────────────────────
watch(() => authStore.commandPaletteOpen, async (open) => {
  if (open) {
    query.value = ''
    selectedIndex.value = 0
    await nextTick()
    inputRef.value?.focus()
  }
})

function close() {
  authStore.commandPaletteOpen = false
}

function execute(entry) {
  pushRecent(entry.item.id)
  entry.item.action()
  close()
}

function globalIndex(entry) {
  return flatList.value.indexOf(entry)
}

function scrollSelectedIntoView() {
  nextTick(() => {
    const el = listRef.value?.querySelector('[data-selected="true"]')
    if (el) el.scrollIntoView({ block: 'nearest' })
  })
}

function handleKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, flatList.value.length - 1)
    scrollSelectedIntoView()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
    scrollSelectedIntoView()
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const entry = flatList.value[selectedIndex.value]
    if (entry) execute(entry)
  } else if (e.key === 'Escape') {
    close()
  }
}

// Reset selection when results change
watch(filtered, () => {
  selectedIndex.value = 0
})

// ─── Highlight helper ─────────────────────────────────
function highlightLabel(entry) {
  if (!entry.matches) return entry.item.label
  const labelMatch = entry.matches.find(m => m.key === 'label')
  if (!labelMatch || !labelMatch.indices?.length) return entry.item.label

  const text = entry.item.label
  const indices = [...labelMatch.indices].sort((a, b) => a[0] - b[0])
  let result = ''
  let lastEnd = 0

  for (const [start, end] of indices) {
    result += escapeHtml(text.slice(lastEnd, start))
    result += `<mark class="cmd-highlight">${escapeHtml(text.slice(start, end + 1))}</mark>`
    lastEnd = end + 1
  }
  result += escapeHtml(text.slice(lastEnd))
  return result
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="authStore.commandPaletteOpen"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="close" />

        <!-- Panel -->
        <div
          class="relative z-10 w-full max-w-[580px] bg-[var(--color-surface)] rounded-xl shadow-2xl border border-[var(--color-border)] overflow-hidden"
          @keydown="handleKeydown"
        >
          <!-- Search input -->
          <div class="flex items-center gap-3 px-4 py-3 border-b border-[var(--color-border)]">
            <Search class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
            <input
              ref="inputRef"
              v-model="query"
              :placeholder="t('cmd.placeholder')"
              class="flex-1 bg-transparent text-sm text-[var(--color-text-primary)] placeholder-[var(--color-text-tertiary)] outline-none"
            />
            <span class="pim-kbd text-[10px]">ESC</span>
          </div>

          <!-- Results -->
          <div ref="listRef" class="max-h-[420px] overflow-y-auto py-2">
            <!-- No results -->
            <template v-if="flatList.length === 0">
              <div class="px-4 py-10 text-center">
                <p class="text-sm text-[var(--color-text-tertiary)]">{{ t('cmd.noResults') }}</p>
                <p class="text-xs text-[var(--color-text-tertiary)] mt-1 opacity-60">{{ t('cmd.noResultsHint') }}</p>
              </div>
            </template>

            <!-- Grouped results -->
            <template v-else>
              <div v-for="(entries, section) in groupedResults" :key="section" class="mb-1">
                <!-- Section header -->
                <p class="px-4 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-tertiary)]">
                  {{ sectionLabels[section] || section }}
                </p>

                <!-- Items -->
                <button
                  v-for="entry in entries"
                  :key="entry.item.id"
                  :data-selected="globalIndex(entry) === selectedIndex ? 'true' : undefined"
                  :class="[
                    'w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors cursor-pointer group',
                    globalIndex(entry) === selectedIndex
                      ? 'bg-[color-mix(in_srgb,var(--color-accent)_8%,transparent)]'
                      : 'hover:bg-[var(--color-bg)]',
                  ]"
                  @click="execute(entry)"
                  @mouseenter="selectedIndex = globalIndex(entry)"
                >
                  <!-- Icon -->
                  <component
                    :is="entry.item.icon"
                    :class="[
                      'w-4 h-4 shrink-0 transition-colors',
                      globalIndex(entry) === selectedIndex ? 'text-[var(--color-accent)]' : 'text-[var(--color-text-tertiary)]',
                    ]"
                    :stroke-width="1.75"
                  />

                  <!-- Label + description -->
                  <div class="flex-1 min-w-0">
                    <span
                      :class="[
                        'text-[13px] block truncate',
                        globalIndex(entry) === selectedIndex
                          ? 'text-[var(--color-accent)] font-medium'
                          : 'text-[var(--color-text-primary)]',
                      ]"
                      v-html="highlightLabel(entry)"
                    />
                    <span
                      v-if="entry.item.description"
                      class="text-[11px] text-[var(--color-text-tertiary)] block truncate mt-0.5 leading-tight"
                    >
                      {{ entry.item.description }}
                    </span>
                  </div>

                  <!-- Go arrow on selected -->
                  <ArrowRight
                    v-if="globalIndex(entry) === selectedIndex"
                    class="w-3.5 h-3.5 text-[var(--color-accent)] shrink-0 opacity-60"
                    :stroke-width="2"
                  />
                </button>
              </div>
            </template>
          </div>

          <!-- Footer hint -->
          <div class="px-4 py-2 border-t border-[var(--color-border)] flex items-center gap-4 text-[10px] text-[var(--color-text-tertiary)]">
            <span class="flex items-center gap-1">
              <span class="pim-kbd text-[9px]">↑↓</span> {{ t('navigieren') }}
            </span>
            <span class="flex items-center gap-1">
              <span class="pim-kbd text-[9px]">↵</span> {{ t('öffnen') }}
            </span>
            <span class="flex items-center gap-1">
              <span class="pim-kbd text-[9px]">ESC</span> {{ t('schließen') }}
            </span>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

:deep(.cmd-highlight) {
  background: color-mix(in srgb, var(--color-accent) 20%, transparent);
  color: var(--color-accent);
  border-radius: 2px;
  padding: 0 1px;
}
</style>
