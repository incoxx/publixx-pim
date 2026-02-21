---
title: Bedienung - Übersicht
---

# Bedienung des Publixx PIM

Willkommen im Bedienungshandbuch des Publixx PIM. Dieses Kapitel richtet sich an alle Anwender, die täglich mit dem System arbeiten: vom Produktmanager über den Data Steward bis zum Export-Verantwortlichen. Sie erfahren hier, wie Sie Produkte anlegen und pflegen, Attribute konfigurieren, Hierarchien aufbauen, Medien verwalten, Preise erfassen und Benutzer administrieren.

## Die Benutzeroberfläche im Überblick

Das Publixx PIM ist als moderne Single-Page-Applikation auf Basis von Vue 3 mit Tailwind CSS und DaisyUI umgesetzt. Nach dem Login gelangen Sie auf das Dashboard, von dem aus alle Funktionsbereiche über die **Seitenleiste** (Sidebar) erreichbar sind.

### Aufbau der Oberfläche

<svg viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .frame { fill: #f8fafc; stroke: #94a3b8; stroke-width: 2; rx: 8; }
      .sidebar { fill: #1e293b; rx: 8; }
      .sidebar-item { fill: none; stroke: none; }
      .sidebar-text { fill: #e2e8f0; font-family: system-ui, sans-serif; font-size: 13px; }
      .sidebar-text-active { fill: #38bdf8; font-family: system-ui, sans-serif; font-size: 13px; font-weight: 600; }
      .sidebar-label { fill: #64748b; font-family: system-ui, sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
      .content-bg { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1; rx: 6; }
      .header-bg { fill: #f1f5f9; rx: 6; }
      .title-text { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 16px; font-weight: 700; }
      .body-text { fill: #64748b; font-family: system-ui, sans-serif; font-size: 12px; }
      .logo-bg { fill: #6366f1; rx: 4; }
      .logo-text { fill: #ffffff; font-family: system-ui, sans-serif; font-size: 14px; font-weight: 700; }
      .brand-text { fill: #6366f1; font-family: system-ui, sans-serif; font-size: 14px; font-weight: 600; }
      .icon-dot { fill: #94a3b8; }
      .icon-dot-active { fill: #38bdf8; }
      .divider { stroke: #334155; stroke-width: 1; }
    </style>
  </defs>
  <!-- Outer frame -->
  <rect class="frame" x="1" y="1" width="798" height="398" />
  <!-- Sidebar -->
  <rect class="sidebar" x="1" y="1" width="200" height="398" />
  <!-- Logo area -->
  <rect class="logo-bg" x="16" y="16" width="28" height="28" />
  <text class="logo-text" x="24" y="36">P</text>
  <text class="brand-text" x="54" y="36">Publixx PIM</text>
  <!-- Sidebar nav items -->
  <circle class="icon-dot" cx="28" cy="72" r="4" />
  <text class="sidebar-text" x="44" y="76">Suche</text>
  <circle class="icon-dot-active" cx="28" cy="100" r="4" />
  <text class="sidebar-text-active" x="44" y="104">Produkte</text>
  <circle class="icon-dot" cx="28" cy="128" r="4" />
  <text class="sidebar-text" x="44" y="132">Hierarchien</text>
  <circle class="icon-dot" cx="28" cy="156" r="4" />
  <text class="sidebar-text" x="44" y="160">Attribute</text>
  <circle class="icon-dot" cx="28" cy="184" r="4" />
  <text class="sidebar-text" x="44" y="188">Produkttypen</text>
  <circle class="icon-dot" cx="28" cy="212" r="4" />
  <text class="sidebar-text" x="44" y="216">Attributgruppen</text>
  <circle class="icon-dot" cx="28" cy="240" r="4" />
  <text class="sidebar-text" x="44" y="244">Wertelisten</text>
  <!-- Divider -->
  <line class="divider" x1="16" y1="262" x2="184" y2="262" />
  <circle class="icon-dot" cx="28" cy="282" r="4" />
  <text class="sidebar-text" x="44" y="286">Import</text>
  <circle class="icon-dot" cx="28" cy="310" r="4" />
  <text class="sidebar-text" x="44" y="314">Export</text>
  <circle class="icon-dot" cx="28" cy="338" r="4" />
  <text class="sidebar-text" x="44" y="342">Medien</text>
  <circle class="icon-dot" cx="28" cy="366" r="4" />
  <text class="sidebar-text" x="44" y="370">Preise</text>
  <!-- Content area -->
  <rect class="content-bg" x="216" y="16" width="568" height="50" />
  <text class="title-text" x="232" y="48">Inhaltsbereich</text>
  <rect class="content-bg" x="216" y="80" width="568" height="304" />
  <text class="body-text" x="420" y="200" text-anchor="middle">Hier erscheint der jeweilige Funktionsbereich</text>
  <text class="body-text" x="420" y="220" text-anchor="middle">mit Listen- und Detailansichten.</text>
</svg>

Die Sidebar ist persistent sichtbar und kann eingeklappt werden. Sie gliedert sich in die folgenden Hauptbereiche:

| Bereich | Beschreibung |
|---|---|
| **Suche** | Globale Produktsuche und Suchassistent mit PQL-Abfragesprache |
| **Produkte** | Produktverwaltung mit Varianten, Relationen und Versionshistorie |
| **Hierarchien** | Master- und Ausgabehierarchien als Baumstrukturen |
| **Attribute** | Attributdefinitionen und Konfigurationen |
| **Produkttypen** | Definition von Produkttypen mit zugeordneten Attributen |
| **Attributgruppen** | Logische Gruppierung von Attributen (AttributeTypes) |
| **Wertelisten** | Auswahllisten fuer Selection-Attribute |
| **Import / Export** | Datenimport (Excel) und -export (JSON, PXF) |
| **Medien** | Medienbibliothek mit Upload und Zuordnung |
| **Preise** | Preisarten, Waehrungen und Gueltigkeiten |
| **Benutzer** | Benutzerverwaltung, Rollen und Berechtigungen |
| **Einstellungen** | Systemkonfiguration und Benutzereinstellungen |

## Anmeldung und Authentifizierung

Beim Aufruf der PIM-URL werden Sie automatisch auf die Login-Seite weitergeleitet. Geben Sie dort Ihre **E-Mail-Adresse** und Ihr **Passwort** ein. Nach erfolgreicher Anmeldung wird ein Authentifizierungs-Token (Laravel Sanctum) erzeugt, das Ihre Sitzung absichert.

::: tip Hinweis
Ihr Administrator kann die Session-Dauer konfigurieren. Bei Inaktivitaet werden Sie automatisch abgemeldet und muessen sich erneut anmelden.
:::

### Abmelden

Klicken Sie in der Sidebar unten auf **Abmelden**, um Ihre Sitzung sicher zu beenden. Alternativ koennen Sie sich ueber die Einstellungsseite abmelden.

## Sprachumschaltung

Das Publixx PIM unterstuetzt **Deutsch** und **Englisch** als Oberflaechensprachen. Die Spracheinstellung koennen Sie an zwei Stellen aendern:

1. **Benutzereinstellungen** -- Unter Ihrem Benutzerprofil koennen Sie die bevorzugte Sprache dauerhaft festlegen.
2. **Sidebar** -- Ueber den Sprachschalter in der Seitenleiste wechseln Sie schnell zwischen DE und EN.

Die Sprachumschaltung betrifft die Benutzeroberflaeche. Die Sprache der **Produktdaten** (Attributwerte) wird separat ueber die Inhaltssprache gesteuert, die Sie pro Bearbeitungskontext waehlen koennen. So koennen Sie beispielsweise die Oberflaeche auf Deutsch nutzen, waehrend Sie englische Produkttexte bearbeiten.

## Funktionsbereiche im Detail

Jeder Funktionsbereich ist auf einer eigenen Seite ausfuehrlich dokumentiert:

### [Produkte](./produkte)
Alles rund um das Anlegen, Bearbeiten und Verwalten von Produkten. Erfahren Sie, wie Sie die Produktliste nutzen, Detailansichten bearbeiten, Varianten anlegen, Relationen definieren und Massenoperationen durchfuehren.

### [Attribute](./attribute)
Lernen Sie die verschiedenen Datentypen kennen (String, Number, Float, Date, Flag, Selection, Dictionary, Collection), konfigurieren Sie Attributeigenschaften wie Uebersetzbarkeit und Pflichtfelder, und organisieren Sie Attribute ueber Gruppen und Ansichten.

### [Hierarchien](./hierarchien)
Bauen Sie Master-Hierarchien fuer die Produktklassifizierung und Ausgabe-Hierarchien fuer Exporte auf. Verwalten Sie Baumstrukturen mit bis zu sechs Ebenen per Drag-and-Drop.

### [Medien](./medien)
Laden Sie Bilder, Dokumente und Videos hoch, organisieren Sie diese in der Medienbibliothek und weisen Sie Medien per Drag-and-Drop Ihren Produkten zu.

### [Preise](./preise)
Verwalten Sie verschiedene Preisarten in mehreren Waehrungen mit Gueltigkeitszeitraeumen und ordnen Sie Preise Ihren Produkten zu.

### [Benutzer](./benutzer)
Administrieren Sie Benutzerkonten, weisen Sie Rollen zu (Admin, Data Steward, Product Manager, Viewer, Export Manager) und konfigurieren Sie feingranulare Berechtigungen auf Entitaets- und Aktionsebene.

## Tastenkombinationen

Fuer haeufige Aktionen stehen Tastenkombinationen zur Verfuegung:

| Kuerzel | Aktion |
|---|---|
| `Strg + S` | Aktuellen Datensatz speichern |
| `Strg + K` | Befehlspalette (Command Palette) oeffnen |
| `Esc` | Dialog oder Modal schliessen |
| `Strg + F` | Suche im aktuellen Bereich oeffnen |

## Naechste Schritte

Wir empfehlen, mit dem Abschnitt [Produkte](./produkte) zu beginnen, da die Produktverwaltung den Kern des PIM-Systems bildet. Von dort aus koennen Sie bei Bedarf in die jeweiligen Konfigurationsbereiche fuer Attribute, Hierarchien und andere Themen abzweigen.
