import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import App from './App.vue'
import router from './router'
import './assets/main.css'

// i18n messages
const messages = {
  de: {
    nav: {
      search: 'Suche',
      products: 'Produkte',
      hierarchies: 'Hierarchien',
      attributes: 'Attribute',
      valueLists: 'Wertelisten',
      imports: 'Import',
      exports: 'Export',
      media: 'Medien',
      prices: 'Preise',
      users: 'Benutzer',
      settings: 'Einstellungen',
    },
    common: {
      save: 'Speichern',
      cancel: 'Abbrechen',
      delete: 'Löschen',
      create: 'Erstellen',
      edit: 'Bearbeiten',
      search: 'Suchen…',
      filter: 'Filtern',
      loading: 'Laden…',
      noResults: 'Keine Ergebnisse',
      confirm: 'Bestätigen',
      actions: 'Aktionen',
      status: 'Status',
      name: 'Name',
      description: 'Beschreibung',
      type: 'Typ',
      active: 'Aktiv',
      inactive: 'Inaktiv',
      draft: 'Entwurf',
      all: 'Alle',
      selected: '{count} ausgewählt',
      perPage: 'pro Seite',
      of: 'von',
      yes: 'Ja',
      no: 'Nein',
    },
    product: {
      sku: 'Artikelnummer',
      title: 'Produkte',
      newProduct: 'Neues Produkt',
      details: 'Details',
      attributes: 'Attribute',
      variants: 'Varianten',
      media: 'Medien',
      prices: 'Preise',
      relations: 'Beziehungen',
      preview: 'Vorschau',
      history: 'Historie',
    },
    hierarchy: {
      title: 'Hierarchien',
      newNode: 'Neuer Knoten',
      moveNode: 'Knoten verschieben',
      deleteConfirm: 'Knoten und alle Unterknoten löschen?',
    },
    attribute: {
      title: 'Attribute',
      newAttribute: 'Neues Attribut',
      dataType: 'Datentyp',
      required: 'Pflichtfeld',
      searchable: 'Suchbar',
      filterable: 'Filterbar',
      inherited: 'Vererbt von: {source}',
      overridden: 'Überschrieben',
    },
    import: {
      title: 'Import',
      uploadFile: 'Datei hochladen',
      validate: 'Validieren',
      execute: 'Ausführen',
      template: 'Vorlage herunterladen',
    },
    export: {
      title: 'Export',
      startExport: 'Export starten',
      mapping: 'Mapping',
      preview: 'Vorschau',
    },
    cmd: {
      placeholder: 'Suche oder Befehl eingeben…',
      noResults: 'Keine Ergebnisse gefunden',
      sections: {
        navigation: 'Navigation',
        actions: 'Aktionen',
        recent: 'Zuletzt verwendet',
      },
    },
  },
  en: {
    nav: {
      search: 'Search',
      products: 'Products',
      hierarchies: 'Hierarchies',
      attributes: 'Attributes',
      valueLists: 'Value Lists',
      imports: 'Import',
      exports: 'Export',
      media: 'Media',
      prices: 'Prices',
      users: 'Users',
      settings: 'Settings',
    },
    common: {
      save: 'Save',
      cancel: 'Cancel',
      delete: 'Delete',
      create: 'Create',
      edit: 'Edit',
      search: 'Search…',
      filter: 'Filter',
      loading: 'Loading…',
      noResults: 'No results',
      confirm: 'Confirm',
      actions: 'Actions',
      status: 'Status',
      name: 'Name',
      description: 'Description',
      type: 'Type',
      active: 'Active',
      inactive: 'Inactive',
      draft: 'Draft',
      all: 'All',
      selected: '{count} selected',
      perPage: 'per page',
      of: 'of',
      yes: 'Yes',
      no: 'No',
    },
    product: {
      sku: 'SKU',
      title: 'Products',
      newProduct: 'New Product',
      details: 'Details',
      attributes: 'Attributes',
      variants: 'Variants',
      media: 'Media',
      prices: 'Prices',
      relations: 'Relations',
      preview: 'Preview',
      history: 'History',
    },
    hierarchy: {
      title: 'Hierarchies',
      newNode: 'New Node',
      moveNode: 'Move Node',
      deleteConfirm: 'Delete node and all children?',
    },
    attribute: {
      title: 'Attributes',
      newAttribute: 'New Attribute',
      dataType: 'Data Type',
      required: 'Required',
      searchable: 'Searchable',
      filterable: 'Filterable',
      inherited: 'Inherited from: {source}',
      overridden: 'Overridden',
    },
    import: {
      title: 'Import',
      uploadFile: 'Upload File',
      validate: 'Validate',
      execute: 'Execute',
      template: 'Download Template',
    },
    export: {
      title: 'Export',
      startExport: 'Start Export',
      mapping: 'Mapping',
      preview: 'Preview',
    },
    cmd: {
      placeholder: 'Search or enter command…',
      noResults: 'No results found',
      sections: {
        navigation: 'Navigation',
        actions: 'Actions',
        recent: 'Recently Used',
      },
    },
  },
}

const i18n = createI18n({
  legacy: false,
  locale: import.meta.env.VITE_DEFAULT_LOCALE || 'de',
  fallbackLocale: 'en',
  messages,
})

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(i18n)
app.mount('#app')
