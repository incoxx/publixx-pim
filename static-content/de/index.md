---
layout: home
title: anyPIM Dokumentation
hero:
  name: anyPIM
  text: Product Information Management
  tagline: Flexible, leistungsstarke Produktdatenverwaltung mit EAV-Architektur, Vererbung, PQL-Abfragesprache und nahtloser Integration. Open Source (GPL-3.0).
  actions:
    - theme: brand
      text: Schnellstart
      link: /de/installation/schnellstart
    - theme: alt
      text: Alleinstellungsmerkmale
      link: /de/intro/alleinstellungsmerkmale
    - theme: alt
      text: API-Referenz
      link: /de/api/

# Die Kacheln entsprechen 1:1 den Hauptbereichen der Seitenleiste (gleiche Titel,
# gleiche Reihenfolge), damit Startseite und Navigation dieselbe Sprache sprechen.
features:
  - icon: 🚀
    title: Erste Schritte
    details: In zehn Minuten vom frischen Server zur laufenden Installation.
    link: /de/installation/schnellstart
  - icon: ⚙️
    title: Installation & Betrieb
    details: Voraussetzungen, Umgebungsvariablen, Deployment, Cronjobs und Suchdienste.
    link: /de/installation/
  - icon: 📋
    title: Daily Business
    details: Produkte, Varianten, Hierarchien, Medien, Merkliste, Workflow und Planungskalender.
    link: /de/bedienung/
  - icon: 🔧
    title: Konfiguration
    details: Attribute, Wörterbuch, Einheiten, Preise, Hersteller und Beziehungstypen einrichten.
    link: /de/bedienung/attribute
  - icon: 🔄
    title: Import & Export
    details: Excel-Import mit Validierung, JSON- und Publixx-Export, BMEcat, Export-Jobs.
    link: /de/import/
  - icon: 📤
    title: Publish & Ausgabe
    details: Berichte, PDF-Vorlagen, Katalog-Embed, Portale und Social-Videos.
    link: /de/erweitert/berichte
  - icon: 🤖
    title: KI & Automatisierung
    details: Copilot als KI-Assistent und semantische Suche über alle Produktdaten.
    link: /de/ki/
  - icon: 🌍
    title: Übersetzungen
    details: Translation Memory für Metadaten und XLIFF-Jobs für Produkttexte.
    link: /de/bedienung/uebersetzungen
  - icon: 📁
    title: Projektmanagement
    details: Projekte und Teams organisieren, Aufgaben zuweisen und nachverfolgen.
    link: /de/bedienung/projekte-teams
  - icon: 🔌
    title: Integrationen & API
    details: 600+ REST-Endpoints, PQL, API-Designer und Konnektoren zu Shop-Systemen.
    link: /de/api/
  - icon: 🛡️
    title: Administration
    details: Rollen und Berechtigungen, Benutzer, Audit, Zugangslinks und Systemwerkzeuge.
    link: /de/administration/rollen
  - icon: 🏗️
    title: Architektur
    details: EAV-Datenmodell, Vererbungssystem, Services und Events im Detail.
    link: /de/architektur/
  - icon: ❓
    title: FAQ
    details: Antworten auf die häufigsten Fragen rund um anyPIM.
    link: /de/faq/
---

## Willkommen

anyPIM ist ein **Product Information Management System**, das speziell für die Anforderungen moderner Produktdatenverwaltung entwickelt wurde. Es kombiniert die Flexibilität einer EAV-Architektur mit der Leistungsfähigkeit von Laravel und Vue.js.

### Für wen ist diese Dokumentation?

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin: 1.5rem 0;">

<div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">

**Anwender**

Sie möchten Produkte pflegen, Medien zuordnen oder Importe und Exporte durchführen?
→ Starten Sie mit [Daily Business](/de/bedienung/)

</div>

<div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">

**Administrator**

Sie richten das System ein, vergeben Rechte und betreuen den Betrieb?
→ Starten Sie mit [Installation](/de/installation/) und [Administration](/de/administration/rollen)

</div>

<div style="padding: 1rem; border: 1px solid var(--vp-c-divider); border-radius: 8px;">

**Entwickler**

Sie möchten die API nutzen, das System integrieren oder die Architektur verstehen?
→ Starten Sie mit der [API-Referenz](/de/api/) oder der [Architektur](/de/architektur/)

</div>

</div>
