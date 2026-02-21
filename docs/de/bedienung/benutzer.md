---
title: Benutzer
---

# Benutzer

Die Benutzerverwaltung des Publixx PIM basiert auf einem rollenbasierten Zugriffskontrollsystem (RBAC). Dieses Kapitel beschreibt das Anlegen und Verwalten von Benutzerkonten, die verfügbaren Rollen sowie das feingranulare Berechtigungssystem.

## Benutzerliste

Die Benutzerverwaltung erreichen Sie über den Menüpunkt **Benutzer** in der Sidebar. Die Übersicht zeigt alle registrierten Benutzer in tabellarischer Form:

| Spalte | Beschreibung |
|---|---|
| **Name** | Vor- und Nachname des Benutzers |
| **E-Mail** | E-Mail-Adresse (dient auch als Login) |
| **Rolle** | Zugewiesene Systemrolle |
| **Sprache** | Bevorzugte Oberflächensprache |
| **Erstellt am** | Zeitpunkt der Kontoerstellung |

::: info Zugriffsrecht
Nur Benutzer mit der Rolle **Admin** haben Zugriff auf die Benutzerverwaltung und können Konten anlegen, bearbeiten oder löschen.
:::

## Benutzer anlegen

1. Klicken Sie auf **+ Neuer Benutzer** oberhalb der Benutzerliste.
2. Es öffnet sich das Formular-Panel (UserFormPanel) mit folgenden Feldern:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Vorname** | Vorname des Benutzers | Ja |
| **Nachname** | Nachname des Benutzers | Ja |
| **E-Mail** | E-Mail-Adresse (Login-Kennung, muss eindeutig sein) | Ja |
| **Passwort** | Initiales Passwort | Ja |
| **Rolle** | Zuweisung einer Systemrolle | Ja |
| **Sprache** | Bevorzugte Oberflächensprache (DE/EN) | Ja |

3. Speichern Sie das Konto. Der Benutzer kann sich sofort mit den angegebenen Zugangsdaten anmelden.

### Benutzer bearbeiten

Klicken Sie in der Benutzerliste auf einen Benutzer, um dessen Details zu öffnen. Sie können alle Felder bearbeiten, einschließlich der Rolle. Das Passwort kann vom Administrator zurückgesetzt werden.

### Benutzer löschen

Klicken Sie in der Detailansicht auf **Löschen**, um ein Benutzerkonto zu entfernen. Die Löschung erfolgt nach einer Bestätigungsabfrage.

::: warning Hinweis
Gelöschte Benutzerkonten können nicht wiederhergestellt werden. In der Versionshistorie von Produkten bleibt der Benutzername weiterhin sichtbar, auch wenn das Konto gelöscht wurde.
:::

## Rollen

Das Publixx PIM definiert fünf Systemrollen mit unterschiedlichen Verantwortungsbereichen:

### Admin

| Eigenschaft | Beschreibung |
|---|---|
| **Vollzugriff** | Uneingeschränkter Zugriff auf alle Funktionsbereiche |
| **Benutzerverwaltung** | Kann Benutzer anlegen, bearbeiten und löschen |
| **Systemkonfiguration** | Kann Systemeinstellungen, Preisarten, Hierarchien und Attribute konfigurieren |
| **Datenmanagement** | Voller Zugriff auf alle Produkte, Medien und Preise |

Der Admin ist die einzige Rolle, die Zugriff auf die Benutzerverwaltung und Systemkonfiguration hat.

### Data Steward

| Eigenschaft | Beschreibung |
|---|---|
| **Datenmodellierung** | Kann Attribute, Attributgruppen, Produkttypen und Wertelisten verwalten |
| **Hierarchieverwaltung** | Kann Hierarchien anlegen und bearbeiten |
| **Qualitätssicherung** | Überprüft und validiert Produktdaten |
| **Kein Benutzerzugriff** | Keine Berechtigung für die Benutzerverwaltung |

Der Data Steward ist verantwortlich für die Datenstruktur und -qualität, ohne direkt Produktinhalte zu pflegen.

### Product Manager

| Eigenschaft | Beschreibung |
|---|---|
| **Produktpflege** | Kann Produkte anlegen, bearbeiten, Varianten erstellen und Medien zuweisen |
| **Preispflege** | Kann Preise erfassen und bearbeiten |
| **Eingeschränkte Konfiguration** | Kein Zugriff auf die Attributdefinition oder Hierarchiestruktur |
| **Exportzugriff** | Kann Exporte anstoßen |

Der Product Manager ist die typische Rolle für die tägliche Produktdatenpflege.

### Viewer

| Eigenschaft | Beschreibung |
|---|---|
| **Nur-Lese-Zugriff** | Kann alle Produkte, Attribute und Hierarchien einsehen, aber nicht bearbeiten |
| **Suche und Navigation** | Kann die Produktsuche und Hierarchienavigation nutzen |
| **Kein Schreibzugriff** | Keine Berechtigung zum Anlegen, Bearbeiten oder Löschen von Daten |

Die Viewer-Rolle eignet sich für Stakeholder, die Produktdaten einsehen, aber nicht bearbeiten sollen.

### Export Manager

| Eigenschaft | Beschreibung |
|---|---|
| **Exportverwaltung** | Kann Export-Templates konfigurieren und Exporte anstoßen |
| **Lesezugriff auf Produkte** | Kann Produktdaten einsehen, aber nicht bearbeiten |
| **Importverwaltung** | Kann Datenimporte konfigurieren und durchführen |

Der Export Manager ist für die Datenausgabe und -eingabe über Schnittstellen verantwortlich.

### Rollenvergleich

| Berechtigung | Admin | Data Steward | Product Manager | Viewer | Export Manager |
|---|---|---|---|---|---|
| Benutzer verwalten | Ja | -- | -- | -- | -- |
| Systemeinstellungen | Ja | -- | -- | -- | -- |
| Attribute definieren | Ja | Ja | -- | -- | -- |
| Hierarchien verwalten | Ja | Ja | -- | -- | -- |
| Wertelisten pflegen | Ja | Ja | -- | -- | -- |
| Produkte anlegen/bearbeiten | Ja | -- | Ja | -- | -- |
| Produkte einsehen | Ja | Ja | Ja | Ja | Ja |
| Medien verwalten | Ja | -- | Ja | -- | -- |
| Preise pflegen | Ja | -- | Ja | -- | -- |
| Import durchführen | Ja | -- | -- | -- | Ja |
| Export konfigurieren | Ja | -- | -- | -- | Ja |

## Berechtigungssystem

Zusätzlich zu den Rollen bietet das Publixx PIM ein **feingranulares Berechtigungssystem**, das individuelle Berechtigungen pro Benutzer ermöglicht.

### Berechtigungsschema

Jede Berechtigung folgt dem Schema:

```
{entität}.{aktion}[:{einschränkung}]
```

| Bestandteil | Beschreibung | Beispiele |
|---|---|---|
| **Entität** | Der Funktionsbereich | `products`, `attributes`, `hierarchies`, `media`, `prices`, `users` |
| **Aktion** | Die erlaubte Operation | `view`, `create`, `update`, `delete`, `export`, `import` |
| **Einschränkung** | Optionale Begrenzung des Geltungsbereichs | `attribute_view:ecommerce`, `hierarchy:electronics` |

### Beispiele für Berechtigungen

| Berechtigung | Bedeutung |
|---|---|
| `products.view` | Alle Produkte einsehen |
| `products.update` | Alle Produkte bearbeiten |
| `products.create` | Neue Produkte anlegen |
| `products.delete` | Produkte löschen |
| `products.update:attribute_view:ecommerce` | Produkte bearbeiten, aber nur Attribute der Ansicht „E-Commerce" |
| `products.view:hierarchy:electronics` | Nur Produkte im Hierarchieknoten „Elektronik" einsehen |
| `attributes.create` | Neue Attribute definieren |
| `attributes.update` | Bestehende Attribute bearbeiten |
| `media.create` | Neue Medien hochladen |
| `media.delete` | Medien löschen |
| `prices.update` | Preise bearbeiten |
| `exports.create` | Exporte anstoßen |

### Einschränkungen auf Attributansichten

Berechtigungen können auf bestimmte **Attributansichten** eingeschränkt werden. Ein Benutzer mit der Berechtigung `products.update:attribute_view:ecommerce` kann nur die Attribute bearbeiten, die in der Attributansicht „E-Commerce" definiert sind. Alle anderen Attribute sind für ihn schreibgeschützt.

Typische Anwendungsfälle:
- Ein Marketing-Mitarbeiter darf nur die Marketingtexte bearbeiten (Ansicht „Marketing").
- Ein Techniker darf nur technische Daten pflegen (Ansicht „Technische Daten").
- Der E-Commerce-Manager darf nur Online-Shop-relevante Felder bearbeiten (Ansicht „E-Shop").

### Einschränkungen auf Hierarchieknoten

Berechtigungen können auf bestimmte **Hierarchieknoten** eingeschränkt werden. Ein Benutzer mit der Berechtigung `products.update:hierarchy:electronics` kann nur Produkte bearbeiten, die dem Hierarchieknoten „Elektronik" (und seinen Unterknoten) zugeordnet sind.

Typische Anwendungsfälle:
- Ein Produktmanager für die Sparte „Haushalt" sieht nur Produkte seiner Sparte.
- Ein regionaler Verantwortlicher pflegt nur die Produkte seiner Produktgruppe.

### Kombinierte Einschränkungen

Attributansicht- und Hierarchie-Einschränkungen können kombiniert werden. Ein Benutzer kann beispielsweise auf die Attributansicht „E-Shop" eingeschränkt sein und gleichzeitig nur den Hierarchieknoten „Bekleidung" sehen. So entsteht eine Matrix-Berechtigung, die sowohl den Datenumfang (welche Attribute) als auch den Produktumfang (welche Produkte) steuert.

## Benutzereinstellungen

Jeder Benutzer kann in seinen persönlichen Einstellungen folgende Optionen konfigurieren:

| Einstellung | Beschreibung |
|---|---|
| **Sprache** | Bevorzugte Oberflächensprache (Deutsch/Englisch) |
| **Passwort ändern** | Eigenes Passwort aktualisieren |

Die Einstellungen erreichen Sie über den Menüpunkt **Einstellungen** in der Sidebar oder über Ihr Benutzerprofil.

## Best Practices

- **Minimale Berechtigungen** -- Vergeben Sie Benutzern nur die Berechtigungen, die sie für ihre Aufgaben benötigen (Principle of Least Privilege).
- **Rollenplanung** -- Definieren Sie vor der Benutzeranlage, welche Rollen und Berechtigungen in Ihrem Unternehmen benötigt werden.
- **Attributansichten nutzen** -- Erstellen Sie Attributansichten für verschiedene Abteilungen und schränken Sie die Bearbeitungsrechte darauf ein. So vermeiden Sie versehentliche Änderungen an fachfremden Daten.
- **Hierarchie-Einschränkungen** -- Nutzen Sie Hierarchie-Einschränkungen, um Produktmanagern nur ihren Verantwortungsbereich sichtbar zu machen.
- **Regelmäßige Überprüfung** -- Überprüfen Sie regelmäßig die Benutzerkonten und Berechtigungen. Deaktivieren Sie Konten von Mitarbeitern, die das Unternehmen verlassen haben.

## Nächste Schritte

- Erfahren Sie, wie [Attributansichten](./attribute#attributansichten-attribute-views) für die Berechtigungssteuerung konfiguriert werden.
- Lernen Sie die [Hierarchieverwaltung](./hierarchien) kennen, um Einschränkungen auf Kategorien zu definieren.
- Kehren Sie zur [Übersicht](./index) zurück, um andere Funktionsbereiche zu erkunden.
