---
title: Rollen & Berechtigungen
---

# Rollen & Berechtigungen

Das anyPIM verfügt über ein rollenbasiertes Zugriffskontrollsystem (Role-Based Access Control, RBAC), mit dem Sie den Zugriff auf Funktionen und Daten differenziert steuern können. Rollen fassen Berechtigungen zu logischen Einheiten zusammen und werden Benutzern zugewiesen. Zusätzlich lassen sich granulare Einzelberechtigungen vergeben, um den Zugriff exakt auf die jeweiligen Aufgabenbereiche zuzuschneiden.

## Übersicht

Die Rollenverwaltung erreichen Sie über **Administration > Rollen & Berechtigungen** in der Sidebar. Die Übersicht zeigt alle im System definierten Rollen in tabellarischer Form:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung der Rolle |
| **Beschreibung** | Kurzbeschreibung des Verantwortungsbereichs |
| **Benutzer** | Anzahl der Benutzer, denen diese Rolle zugewiesen ist |
| **Berechtigungen** | Anzahl der zugewiesenen Einzelberechtigungen |
| **Erstellt am** | Zeitpunkt der Erstellung |

::: tip Hinweis
Nur Benutzer mit der Rolle **Admin** oder der Berechtigung `roles.manage` haben Zugriff auf die Rollenverwaltung. Für alle anderen Benutzer ist dieser Menüpunkt ausgeblendet.
:::

## Standardrollen

Das anyPIM liefert drei vordefinierte Standardrollen aus, die nicht gelöscht werden können:

### Admin

Uneingeschränkter Vollzugriff auf alle Funktionsbereiche des Systems. Der Admin kann Benutzer verwalten, Rollen definieren, Systemeinstellungen ändern und sämtliche Daten bearbeiten. Mindestens ein Benutzer muss immer die Admin-Rolle besitzen.

### Editor

Umfassende Bearbeitungsrechte für Produktdaten, Attribute, Medien und Preise. Der Editor kann neue Produkte anlegen, bestehende bearbeiten und Exporte anstoßen. Kein Zugriff auf die Benutzerverwaltung oder Systemkonfiguration.

### Viewer

Reiner Lesezugriff auf alle Produktdaten, Hierarchien und Medien. Der Viewer kann Daten einsehen und die Suchfunktion nutzen, jedoch keine Änderungen vornehmen. Schaltflächen zum Speichern und Löschen werden automatisch ausgeblendet.

### Vergleich der Standardrollen

| Berechtigung | Admin | Editor | Viewer |
|---|---|---|---|
| Benutzer verwalten | Ja | -- | -- |
| Rollen verwalten | Ja | -- | -- |
| Systemeinstellungen | Ja | -- | -- |
| Produkte anlegen/bearbeiten | Ja | Ja | -- |
| Produkte einsehen | Ja | Ja | Ja |
| Attribute definieren | Ja | Ja | -- |
| Hierarchien verwalten | Ja | Ja | -- |
| Medien verwalten | Ja | Ja | -- |
| Preise pflegen | Ja | Ja | -- |
| Import durchführen | Ja | Ja | -- |
| Export anstoßen | Ja | Ja | -- |

## Benutzerdefinierte Rollen erstellen

Neben den Standardrollen können Sie beliebig viele eigene Rollen anlegen, um den Zugriff exakt an Ihre Organisationsstruktur anzupassen.

1. Navigieren Sie zu **Administration > Rollen & Berechtigungen**.
2. Klicken Sie auf **+ Neue Rolle**.
3. Vergeben Sie einen Namen und eine optionale Beschreibung.
4. Wählen Sie die gewünschten Berechtigungen aus der Berechtigungsliste aus.
5. Speichern Sie die Rolle.

### Rolle bearbeiten

Klicken Sie in der Rollenliste auf eine Rolle, um deren Berechtigungen zu ändern. Änderungen an einer Rolle wirken sich sofort auf alle Benutzer aus, denen diese Rolle zugewiesen ist.

### Rolle löschen

Benutzerdefinierte Rollen können über die Detailansicht gelöscht werden. Voraussetzung ist, dass der Rolle keine Benutzer mehr zugewiesen sind.

::: warning Warnung
Das Löschen einer Rolle kann nicht rückgängig gemacht werden. Stellen Sie sicher, dass alle betroffenen Benutzer vorher einer anderen Rolle zugewiesen wurden.
:::

## Granulare Berechtigungen

Jede Berechtigung folgt dem Schema `{entität}.{aktion}` und steuert den Zugriff auf eine bestimmte Operation in einem Funktionsbereich.

### Verfügbare Berechtigungen

| Berechtigung | Beschreibung |
|---|---|
| `products.view` | Produkte einsehen |
| `products.create` | Neue Produkte anlegen |
| `products.edit` | Bestehende Produkte bearbeiten |
| `products.delete` | Produkte löschen |
| `attributes.view` | Attribute einsehen |
| `attributes.create` | Neue Attribute definieren |
| `attributes.edit` | Bestehende Attribute bearbeiten |
| `attributes.delete` | Attribute löschen |
| `hierarchies.view` | Hierarchien einsehen |
| `hierarchies.create` | Neue Hierarchieknoten anlegen |
| `hierarchies.edit` | Hierarchieknoten bearbeiten |
| `hierarchies.delete` | Hierarchieknoten löschen |
| `media.view` | Medien einsehen |
| `media.create` | Neue Medien hochladen |
| `media.edit` | Medien bearbeiten |
| `media.delete` | Medien löschen |
| `prices.view` | Preise einsehen |
| `prices.edit` | Preise bearbeiten |
| `exports.create` | Exporte anstoßen |
| `imports.create` | Importe durchführen |
| `users.manage` | Benutzer verwalten |
| `roles.manage` | Rollen verwalten |
| `settings.manage` | Systemeinstellungen bearbeiten |

### Berechtigungen einer Rolle zuweisen

Im Bearbeitungsdialog einer Rolle werden alle verfügbaren Berechtigungen nach Funktionsbereichen gruppiert angezeigt. Sie können einzelne Berechtigungen per Checkbox aktivieren oder deaktivieren. Über die Schaltfläche **Alle auswählen** lassen sich alle Berechtigungen eines Bereichs auf einmal setzen.

## Rollen Benutzern zuweisen

Die Zuweisung einer Rolle erfolgt in der Benutzerverwaltung:

1. Navigieren Sie zu **Administration > Benutzer**.
2. Öffnen Sie den gewünschten Benutzer.
3. Wählen Sie im Feld **Rolle** die gewünschte Rolle aus der Dropdown-Liste.
4. Speichern Sie die Änderung.

Ein Benutzer kann genau eine Rolle besitzen. Die Rolle bestimmt die Grundberechtigungen des Benutzers.

::: danger Achtung
Entziehen Sie sich nicht selbst die Admin-Rolle, wenn Sie der einzige Administrator sind. Das System verhindert diese Aktion, um eine Aussperrung zu vermeiden.
:::

## Best Practices

- **Principle of Least Privilege** -- Vergeben Sie Benutzern immer nur die Berechtigungen, die sie für ihre tägliche Arbeit tatsächlich benötigen.
- **Rollenplanung** -- Definieren Sie die Rollenstruktur vor dem Anlegen der Benutzerkonten. Orientieren Sie sich an den Verantwortungsbereichen Ihrer Organisation.
- **Benutzerdefinierte Rollen nutzen** -- Erstellen Sie für spezifische Aufgabenbereiche eigene Rollen, anstatt die Standardrollen mit zusätzlichen Berechtigungen zu überladen.
- **Regelmäßige Überprüfung** -- Kontrollieren Sie in regelmäßigen Abständen, ob die zugewiesenen Rollen und Berechtigungen noch den aktuellen Anforderungen entsprechen.
- **Dokumentation** -- Pflegen Sie eine interne Übersicht, welche Rolle für welchen Personenkreis vorgesehen ist.

## Nächste Schritte

- Erfahren Sie, wie Sie [Benutzerkonten](../bedienung/benutzer) anlegen und verwalten.
- Lernen Sie das [Benutzer-Audit](./benutzer-audit) kennen, um Benutzeraktivitäten nachzuverfolgen.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
