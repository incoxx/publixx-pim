<script setup>
import { useI18n } from 'vue-i18n'
import {
  HelpCircle, BookOpen, Keyboard, MessageSquare, ExternalLink,
  Package, Sliders, GitBranch, Upload, Download, Image, DollarSign,
} from 'lucide-vue-next'

const { t } = useI18n()

const sections = [
  {
    icon: Package,
    title: 'Produkte verwalten',
    items: [
      'Produkte erstellen, bearbeiten und löschen',
      'Attributwerte pro Sprache pflegen (Sprachumschalter im Attribut-Tab)',
      'Varianten anlegen und Vererbungsregeln festlegen',
      'Medien, Preise und Beziehungen zuordnen',
      'Produktvorschau mit Vollständigkeitsprüfung',
      'Excel- und PDF-Export der Produktdaten',
      'XLIFF-Export/Import für Übersetzungen',
    ],
  },
  {
    icon: Sliders,
    title: 'Attribute & Produkttypen',
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
    items: [
      'Baumstruktur für Produktkategorien',
      'Attribut-Zuweisung auf Hierarchieebene',
      'Vererbung von Attributen über die Hierarchie',
    ],
  },
  {
    icon: Upload,
    title: 'Import & Export',
    items: [
      'CSV/Excel-Import von Produktdaten',
      'Vorlagen-Download für konsistente Imports',
      'Export in verschiedene Formate',
      'XLIFF-Export/Import für Übersetzungsworkflows',
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
          href="https://github.com/incoxx/publixx-pim"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <ExternalLink class="w-3.5 h-3.5" :stroke-width="1.75" />
          GitHub Repository
        </a>
        <a
          href="https://github.com/incoxx/publixx-pim/issues"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-2 text-[13px] text-[var(--color-accent)] hover:underline"
        >
          <MessageSquare class="w-3.5 h-3.5" :stroke-width="1.75" />
          Fehler melden / Feedback
        </a>
      </div>
    </div>

    <p class="text-[11px] text-[var(--color-text-tertiary)]">
      Publixx PIM ist lizenziert unter AGPL-3.0.
    </p>
  </div>
</template>
