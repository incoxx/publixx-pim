<script setup>
import { useI18n } from 'vue-i18n'
import {
  HelpCircle, BookOpen, Keyboard, MessageSquare, ExternalLink,
  Package, Sliders, GitBranch, Upload, Download, Image, DollarSign, Search, Star,
  FileJson, PlayCircle,
} from 'lucide-vue-next'

const { t } = useI18n()

const docsBase = '/web/help/de'

const sections = [
  {
    icon: Package,
    title: 'Produkte verwalten',
    link: `${docsBase}/bedienung/produkte`,
    items: [
      'Produkte erstellen, bearbeiten und löschen',
      'Attributwerte pro Sprache pflegen (Sprachumschalter im Attribut-Tab)',
      'Varianten anlegen und Vererbungsregeln festlegen',
      'Medien, Preise und Beziehungen zuordnen',
      'Produktvorschau mit Vollständigkeitsprüfung',
      'Excel- und PDF-Export der Produktdaten',
      'XLIFF-Export/Import für Übersetzungen',
      'Merkliste: Produkte markieren und exportieren (Excel, PDF, XLIFF)',
      'Produktvergleich: 2 Produkte Attribut für Attribut vergleichen',
    ],
  },
  {
    icon: Search,
    title: 'Suche',
    link: `${docsBase}/bedienung/`,
    items: [
      'SQL-basierte Volltextsuche über alle Produktfelder',
      'LIKE: Standard-Teiltext-Suche',
      'SOUNDEX: Ähnlich klingende Begriffe finden (tolerant bei Tippfehlern)',
      'REGEXP: Reguläre Ausdrücke für präzise Suchmuster',
      'Kategoriefilter mit automatischer Unterkategorie-Erweiterung',
      'Attribut-Filter mit Wertelistenauswahl',
    ],
  },
  {
    icon: Star,
    title: 'Merkliste',
    link: `${docsBase}/bedienung/produkte`,
    items: [
      'Produkte aus Suche oder Produktliste zur Merkliste hinzufügen',
      'Merkliste pro Benutzer in der Datenbank gespeichert',
      'Export als Excel-Datei',
      'Export als PDF (alle Produkte in einem Dokument)',
      'Export als PDF pro SKU im ZIP-Archiv',
      'XLIFF-Export für Übersetzungsworkflows',
    ],
  },
  {
    icon: Sliders,
    title: 'Attribute & Produkttypen',
    link: `${docsBase}/bedienung/attribute`,
    items: [
      'Attribute mit Datentypen (Text, Zahl, Datum, Auswahl, Composite, …)',
      'Attributgruppen zur logischen Gliederung',
      'Wertelisten für Auswahlfelder',
      'Produkttypen mit Attribut-Zuordnung',
      'Übersetzbare, pflichtbare, suchbare Attribute',
    ],
  },
  {
    icon: GitBranch,
    title: 'Hierarchien',
    link: `${docsBase}/bedienung/hierarchien`,
    items: [
      'Baumstruktur für Produktkategorien',
      'Attribut-Zuweisung auf Hierarchieebene',
      'Vererbung von Attributen über die Hierarchie',
    ],
  },
  {
    icon: Upload,
    title: 'Import & Export',
    link: `${docsBase}/import/`,
    items: [
      'CSV/Excel-Import von Produktdaten',
      'Vorlagen-Download für konsistente Imports',
      'Export in verschiedene Formate',
      'XLIFF-Export/Import für Übersetzungsworkflows',
    ],
  },
  {
    icon: FileJson,
    title: 'JSON Export/Import',
    link: `${docsBase}/json-export-import/`,
    items: [
      'Vollexport aller PIM-Daten als JSON (18 Sektionen in Abhängigkeitsreihenfolge)',
      'Sektionsauswahl: Nur bestimmte Entitätstypen exportieren',
      'Produktfilter: Status, Produkttyp, Freitext, Aktualisierungsdatum',
      'JSON-Import mit Validierung und Upsert- oder Delete-Insert-Modus',
      'REST-API: GET/POST /api/v1/json-export, POST /api/v1/json-import',
      'CLI: php artisan pim:json-export, php artisan pim:json-import',
      'Gut lesbar formatiertes JSON — ideal für Schnittstellen und Datensicherungen',
    ],
  },
  {
    icon: PlayCircle,
    title: 'Export-Jobs',
    link: `${docsBase}/export-jobs/`,
    items: [
      'Benannte Export-Jobs mit vorkonfigurierten Filtern und Formaten erstellen',
      'Unterstützte Formate: JSON, Excel, CSV, XML',
      'Sektionsauswahl pro Job (nur bei JSON-Format)',
      'Sofortige oder asynchrone Ausführung über Queue',
      'Download der letzten Export-Datei direkt aus der Job-Übersicht',
      'REST-API und CLI (php artisan pim:export-job) für Automatisierung',
      'Vorbereitet für geplante Ausführung (Cron-Ausdruck)',
    ],
  },
]

const shortcuts = [
  { keys: ['Ctrl', 'K'], action: 'Globale Suche öffnen' },
  { keys: ['Ctrl', 'S'], action: 'Speichern (im Produkteditor)' },
  { keys: ['Esc'], action: 'Dialog/Panel schließen' },
]
</script>

<template>
  <div class="space-y-6 max-w-3xl">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-[color-mix(in_srgb,var(--color-accent)_15%,transparent)] flex items-center justify-center">
        <HelpCircle class="w-5 h-5 text-[var(--color-accent)]" :stroke-width="1.75" />
      </div>
      <div>
        <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ t('nav.help') }}</h2>
        <p class="text-xs text-[var(--color-text-tertiary)]">Publixx PIM — Product Information Management</p>
      </div>
    </div>

    <!-- Full documentation link -->
    <a
      :href="docsBase + '/'"
      target="_blank"
      rel="noopener"
      class="pim-card p-4 flex items-center gap-3 hover:border-[var(--color-accent)] transition-colors cursor-pointer no-underline"
    >
      <div class="w-9 h-9 rounded-lg bg-[color-mix(in_srgb,var(--color-accent)_15%,transparent)] flex items-center justify-center shrink-0">
        <BookOpen class="w-4.5 h-4.5 text-[var(--color-accent)]" :stroke-width="1.75" />
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-[var(--color-text-primary)]">Vollständige Dokumentation</div>
        <div class="text-xs text-[var(--color-text-tertiary)]">Architektur, Bedienung, API-Referenz, Installation, FAQ und mehr</div>
      </div>
      <ExternalLink class="w-4 h-4 text-[var(--color-text-tertiary)] shrink-0" :stroke-width="1.75" />
    </a>

    <!-- Feature sections -->
    <div class="grid gap-4">
      <div v-for="section in sections" :key="section.title" class="pim-card p-4">
        <div class="flex items-center gap-2 mb-3">
          <component :is="section.icon" class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
          <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">{{ section.title }}</h3>
        </div>
        <ul class="space-y-1.5">
          <li v-for="(item, i) in section.items" :key="i" class="text-[13px] text-[var(--color-text-secondary)] flex items-start gap-2">
            <span class="text-[var(--color-accent)] mt-0.5 shrink-0">•</span>
            {{ item }}
          </li>
        </ul>
        <a
          v-if="section.link"
          :href="section.link"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1.5 mt-3 text-[12px] text-[var(--color-accent)] hover:underline"
        >
          <BookOpen class="w-3 h-3" :stroke-width="1.75" />
          Mehr erfahren in der Dokumentation
        </a>
      </div>
    </div>

    <!-- Keyboard shortcuts -->
    <div class="pim-card p-4">
      <div class="flex items-center gap-2 mb-3">
        <Keyboard class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Tastenkürzel</h3>
      </div>
      <div class="space-y-2">
        <div v-for="s in shortcuts" :key="s.action" class="flex items-center justify-between">
          <span class="text-[13px] text-[var(--color-text-secondary)]">{{ s.action }}</span>
          <div class="flex gap-1">
            <kbd
              v-for="key in s.keys"
              :key="key"
              class="px-2 py-0.5 text-[11px] font-mono bg-[var(--color-bg)] border border-[var(--color-border)] rounded text-[var(--color-text-secondary)]"
            >{{ key }}</kbd>
          </div>
        </div>
      </div>
    </div>

    <!-- Links -->
    <div class="pim-card p-4">
      <div class="flex items-center gap-2 mb-3">
        <BookOpen class="w-4 h-4 text-[var(--color-accent)]" :stroke-width="1.75" />
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)]">Weitere Ressourcen</h3>
      </div>
      <div class="space-y-2">
        <a
          :href="docsBase + '/architektur/'"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <BookOpen class="w-3.5 h-3.5" :stroke-width="1.75" />
          Architektur & Datenmodell
        </a>
        <a
          :href="docsBase + '/api/'"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <BookOpen class="w-3.5 h-3.5" :stroke-width="1.75" />
          JSON API-Referenz
        </a>
        <a
          :href="docsBase + '/installation/'"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <BookOpen class="w-3.5 h-3.5" :stroke-width="1.75" />
          Installation & Deployment
        </a>
        <a
          :href="docsBase + '/faq/'"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <HelpCircle class="w-3.5 h-3.5" :stroke-width="1.75" />
          FAQ — Häufige Fragen
        </a>
        <a
          href="mailto:info@incoxx.com"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <MessageSquare class="w-3.5 h-3.5" :stroke-width="1.75" />
          Kontakt aufnehmen — teilen wir Ihnen gerne mit
        </a>
      </div>
    </div>

    <p class="text-[11px] text-[var(--color-text-tertiary)]">
      Publixx PIM ist lizenziert unter AGPL-3.0.
    </p>
  </div>
</template>
