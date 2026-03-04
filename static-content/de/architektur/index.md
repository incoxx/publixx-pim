---
title: Architekturuebersicht
---

# Architekturuebersicht

Publixx PIM ist als moderne Webanwendung mit klar getrenntem Backend und Frontend konzipiert. Das Backend stellt eine vollstaendige REST-API bereit, das Frontend konsumiert diese als Single-Page-Application. Diese Architektur ermoeglicht sowohl die Nutzung ueber die Weboberflaeche als auch die direkte API-Integration durch Drittsysteme.

## Systemarchitektur

<svg viewBox="0 0 900 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="dropShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="3" flood-opacity="0.12"/>
    </filter>
    <linearGradient id="clientGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" style="stop-color:#60a5fa"/>
      <stop offset="100%" style="stop-color:#2563eb"/>
    </linearGradient>
    <linearGradient id="nginxGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" style="stop-color:#4ade80"/>
      <stop offset="100%" style="stop-color:#16a34a"/>
    </linearGradient>
    <linearGradient id="laravelGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" style="stop-color:#f87171"/>
      <stop offset="100%" style="stop-color:#dc2626"/>
    </linearGradient>
    <linearGradient id="serviceGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" style="stop-color:#fb923c"/>
      <stop offset="100%" style="stop-color:#ea580c"/>
    </linearGradient>
    <linearGradient id="dbGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" style="stop-color:#a78bfa"/>
      <stop offset="100%" style="stop-color:#7c3aed"/>
    </linearGradient>
    <linearGradient id="redisGrad" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" style="stop-color:#fb7185"/>
      <stop offset="100%" style="stop-color:#e11d48"/>
    </linearGradient>
    <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#64748b"/>
    </marker>
    <marker id="arrowheadBack" markerWidth="10" markerHeight="7" refX="0" refY="3.5" orient="auto">
      <polygon points="10 0, 0 3.5, 10 7" fill="#64748b"/>
    </marker>
  </defs>

  <!-- Background Zones -->
  <rect x="20" y="15" width="860" height="100" rx="10" fill="#eff6ff" stroke="#bfdbfe" stroke-width="1"/>
  <text x="40" y="38" font-size="11" fill="#3b82f6" font-weight="bold">CLIENT</text>

  <rect x="20" y="130" width="860" height="80" rx="10" fill="#f0fdf4" stroke="#bbf7d0" stroke-width="1"/>
  <text x="40" y="153" font-size="11" fill="#16a34a" font-weight="bold">WEBSERVER</text>

  <rect x="20" y="225" width="860" height="260" rx="10" fill="#fef2f2" stroke="#fecaca" stroke-width="1"/>
  <text x="40" y="248" font-size="11" fill="#dc2626" font-weight="bold">BACKEND (Laravel 11)</text>

  <!-- Client: Vue SPA -->
  <rect x="250" y="45" width="400" height="55" rx="10" fill="url(#clientGrad)" filter="url(#dropShadow)"/>
  <text x="450" y="68" text-anchor="middle" fill="white" font-size="14" font-weight="bold">Vue 3 SPA</text>
  <text x="450" y="86" text-anchor="middle" fill="white" font-size="11">Vite + Tailwind CSS + DaisyUI + Pinia</text>

  <!-- Nginx -->
  <rect x="300" y="150" width="300" height="45" rx="10" fill="url(#nginxGrad)" filter="url(#dropShadow)"/>
  <text x="450" y="178" text-anchor="middle" fill="white" font-size="14" font-weight="bold">Nginx Reverse Proxy</text>

  <!-- Laravel API Layer -->
  <rect x="55" y="260" width="170" height="50" rx="8" fill="url(#laravelGrad)" filter="url(#dropShadow)"/>
  <text x="140" y="282" text-anchor="middle" fill="white" font-size="12" font-weight="bold">Sanctum Auth</text>
  <text x="140" y="298" text-anchor="middle" fill="white" font-size="10">Token + RBAC</text>

  <rect x="245" y="260" width="180" height="50" rx="8" fill="url(#laravelGrad)" filter="url(#dropShadow)"/>
  <text x="335" y="282" text-anchor="middle" fill="white" font-size="12" font-weight="bold">REST Controllers</text>
  <text x="335" y="298" text-anchor="middle" fill="white" font-size="10">90+ Endpoints</text>

  <rect x="445" y="260" width="180" height="50" rx="8" fill="url(#laravelGrad)" filter="url(#dropShadow)"/>
  <text x="535" y="282" text-anchor="middle" fill="white" font-size="12" font-weight="bold">Form Requests</text>
  <text x="535" y="298" text-anchor="middle" fill="white" font-size="10">Validierung</text>

  <rect x="645" y="260" width="170" height="50" rx="8" fill="url(#laravelGrad)" filter="url(#dropShadow)"/>
  <text x="730" y="282" text-anchor="middle" fill="white" font-size="12" font-weight="bold">Resources</text>
  <text x="730" y="298" text-anchor="middle" fill="white" font-size="10">JSON-Transformation</text>

  <!-- Service Layer -->
  <rect x="130" y="340" width="540" height="45" rx="8" fill="url(#serviceGrad)" filter="url(#dropShadow)"/>
  <text x="400" y="360" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Service Layer</text>
  <text x="400" y="375" text-anchor="middle" fill="white" font-size="10">ExportService | ImportService | InheritanceService | PqlService | VersioningService</text>

  <!-- Models / Eloquent -->
  <rect x="200" y="410" width="200" height="45" rx="8" fill="url(#laravelGrad)" filter="url(#dropShadow)"/>
  <text x="300" y="432" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Eloquent Models</text>
  <text x="300" y="447" text-anchor="middle" fill="white" font-size="10">35 Tabellen, UUID PKs</text>

  <!-- MySQL -->
  <rect x="55" y="410" width="120" height="60" rx="8" fill="url(#dbGrad)" filter="url(#dropShadow)"/>
  <text x="115" y="436" text-anchor="middle" fill="white" font-size="13" font-weight="bold">MySQL 8</text>
  <text x="115" y="454" text-anchor="middle" fill="white" font-size="10">JSON + FULLTEXT</text>

  <!-- Redis -->
  <rect x="690" y="340" width="140" height="45" rx="8" fill="url(#redisGrad)" filter="url(#dropShadow)"/>
  <text x="760" y="360" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Redis</text>
  <text x="760" y="375" text-anchor="middle" fill="white" font-size="10">Cache + Queue</text>

  <!-- Horizon -->
  <rect x="690" y="410" width="140" height="45" rx="8" fill="url(#redisGrad)" filter="url(#dropShadow)"/>
  <text x="760" y="432" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Horizon</text>
  <text x="760" y="447" text-anchor="middle" fill="white" font-size="10">Async Jobs</text>

  <!-- Arrows -->
  <line x1="450" y1="100" x2="450" y2="148" stroke="#64748b" stroke-width="2" marker-end="url(#arrowhead)"/>
  <line x1="450" y1="195" x2="450" y2="258" stroke="#64748b" stroke-width="2" marker-end="url(#arrowhead)"/>
  <line x1="400" y1="310" x2="400" y2="338" stroke="#64748b" stroke-width="2" marker-end="url(#arrowhead)"/>
  <line x1="300" y1="385" x2="300" y2="408" stroke="#64748b" stroke-width="2" marker-end="url(#arrowhead)"/>
  <line x1="198" y1="430" x2="177" y2="430" stroke="#64748b" stroke-width="2" marker-end="url(#arrowheadBack)"/>
  <line x1="670" y1="362" x2="688" y2="362" stroke="#64748b" stroke-width="2" marker-end="url(#arrowhead)"/>
</svg>

## Technologie-Stack

| Schicht | Technologie | Version | Zweck |
|---|---|---|---|
| **Frontend** | Vue 3 | 3.x | Reaktives SPA-Framework mit Composition API |
| **Build** | Vite | 5.x | Schnelles HMR und optimierte Builds |
| **Styling** | Tailwind CSS + DaisyUI | 3.x / 4.x | Utility-first CSS mit Komponentenbibliothek |
| **State** | Pinia | 2.x | Zentrales State-Management |
| **Backend** | Laravel | 11.x | PHP-Framework fuer die REST-API |
| **Authentifizierung** | Laravel Sanctum | - | Token-basierte API-Authentifizierung |
| **Autorisierung** | Spatie Permission | - | Rollen- und Berechtigungsverwaltung |
| **Datenbank** | MySQL | 8.0+ | Relationale Datenhaltung mit JSON- und FULLTEXT-Support |
| **Cache** | Redis | 7.x | Anwendungscache und Session-Speicher |
| **Queue** | Redis + Horizon | - | Asynchrone Jobverarbeitung und Monitoring |

## Backend-Architektur

### Schichtenmodell

Das Backend folgt einem strikten Schichtenmodell, das die Verantwortlichkeiten klar trennt:

**1. Routing und Middleware**
Alle API-Routen sind unter `/api/v1/` registriert und durchlaufen die Sanctum-Authentifizierungs-Middleware. Zusaetzliche Middleware prueft Berechtigungen auf Rollen- und Hierarchieebene.

**2. Controller-Schicht**
Controller validieren eingehende Requests ueber Form Requests, delegieren die Geschaeftslogik an Services und transformieren die Ergebnisse ueber API Resources in JSON-Antworten. Controller enthalten keine Geschaeftslogik.

**3. Service-Schicht**
Services kapseln die gesamte Geschaeftslogik. Sie orchestrieren Datenbankoperationen, loesen Events aus und koordinieren agentenuebergreifende Operationen. Detaillierte Beschreibungen finden sich unter [Services und Events](./services).

**4. Model-Schicht**
Eloquent-Models bilden die 35 Datenbanktabellen ab. Sie definieren Relationen, Scopes, Accessors und Mutators. Alle Models verwenden UUID-Primaerschluessel.

### Agentenbasiertes Moduldesign

Die Backend-Codebasis ist in **funktionale Agenten** unterteilt, die jeweils einen fachlichen Bereich abdecken:

- **Attribut-Agent**: Verwaltung von Attributen, Attributgruppen, Attributtypen und deren Validierungsregeln
- **Produkt-Agent**: Produktlebenszyklus, Variantenverwaltung, Wertzuweisungen
- **Hierarchie-Agent**: Baumstrukturen, Knotenoperationen, Produkt-Knoten-Zuordnung
- **Import-Agent**: Excel-Parsing, Validierung, Fuzzy-Matching, transaktionaler Import
- **Export-Agent**: Template-Verwaltung, Mapping-Ausfuehrung, Publixx-Anbindung
- **Medien-Agent**: Datei-Upload, Bildverarbeitung, Medienzuordnung zu Produkten
- **Auth-Agent**: Benutzer, Rollen, Berechtigungen, Attribut-Views

Jeder Agent besitzt eigene Controller, Services, Models und Form Requests. Agentenuebergreifende Kommunikation erfolgt ueber Service-Injection und Events.

## Frontend-Architektur

Das Frontend ist eine **Vue 3 Single-Page-Application** mit folgender Struktur:

### Kern-Technologien

- **Composition API** durchgaengig fuer reaktive Logik und Code-Wiederverwendung
- **Pinia Stores** fuer globalen Zustand (aktueller Benutzer, Sprachauswahl, aktive Hierarchie)
- **Vue Router** mit verschachtelten Routen und navigationsbasierten Guards fuer Berechtigungen
- **Axios** als HTTP-Client mit automatischer Token-Erneuerung und Fehlerbehandlung

### Build-Pipeline

Vite uebernimmt sowohl die Entwicklungsumgebung (HMR mit unter 100ms Aktualisierung) als auch den Produktions-Build mit Tree-Shaking, Code-Splitting und Asset-Hashing. Die gebaute SPA wird als statische Dateien ueber Nginx ausgeliefert.

## Caching-Strategie

### Redis als Anwendungscache

Publixx PIM nutzt Redis fuer zwei Zwecke: als Anwendungscache und als Queue-Broker.

**Tagged Cache Invalidation:**
Caches werden mit Tags versehen, die eine gezielte Invalidierung ermoeglichen. Aendert sich ein Produkt, werden alle Caches mit dem Tag `product:{id}` invalidiert -- unabhaengig davon, ob sie Listendarstellungen, Detailansichten oder Suchindex-Eintraege betreffen.

```php
// Beispiel: Cache mit Tags setzen
Cache::tags(['products', "product:{$id}"])->put($key, $data, 3600);

// Beispiel: Gezieltes Invalidieren
Cache::tags(["product:{$id}"])->flush();
```

**Cache-Ebenen:**

| Ebene | TTL | Beschreibung |
|---|---|---|
| Attribut-Definitionen | 24 Stunden | Aendern sich selten, werden bei Schema-Aenderungen invalidiert |
| Hierarchie-Baeume | 1 Stunde | Werden bei Strukturaenderungen sofort invalidiert |
| Produktdaten | 30 Minuten | Werden bei jeder Aenderung gezielt invalidiert |
| Suchindex | Permanent | Wird ueber Datenbank-Triggers und Events aktualisiert |

## Queue-System und Horizon

Langlebige Operationen werden nicht synchron in der HTTP-Request-Verarbeitung ausgefuehrt, sondern in **Background Jobs** ausgelagert:

- **Excel-Import**: Parsing und Verarbeitung grosser Dateien
- **Export-Generierung**: Zusammenstellung und Formatierung der Exportdaten
- **Suchindex-Aktualisierung**: Neuberechnung betroffener Eintraege nach Datenaenderungen
- **Cache-Aufwaermung**: Proaktives Fuellen invalidierter Cache-Eintraege
- **Medienverarbeitung**: Thumbnail-Generierung und Bildoptimierung

**Laravel Horizon** ueberwacht die Queue-Verarbeitung, bietet Echtzeitmetriken und erlaubt die Konfiguration von Parallelitaet und Wiederholungsstrategien.

## Unterseiten dieser Sektion

| Seite | Inhalt |
|---|---|
| [Datenmodell](./datenmodell) | Detaillierte Beschreibung aller 35 Tabellen, des EAV-Musters und des Suchindex |
| [Services und Events](./services) | Service-Schicht, Event-System, Queue-Jobs und Cache-Invalidierung |
| [Vererbung](./vererbung) | Hierarchie- und Varianten-Vererbung mit Aufloesungsregeln und Sortierung |
