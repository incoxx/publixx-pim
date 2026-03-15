---
title: Benutzer-Audit
---

# Benutzer-Audit

Das Benutzer-Audit protokolliert sämtliche Benutzeraktivitäten im anyPIM und bietet eine lückenlose Nachverfolgung aller Anmeldungen, Abmeldungen und sicherheitsrelevanten Aktionen. Die Audit-Funktion unterstützt Sie bei der Einhaltung interner Compliance-Richtlinien und ermöglicht es, ungewöhnliche Aktivitäten frühzeitig zu erkennen.

## Übersicht

Das Benutzer-Audit erreichen Sie über **Administration > Benutzer-Audit** in der Sidebar. Die Hauptansicht zeigt eine chronologische Liste aller protokollierten Benutzeraktionen.

| Spalte | Beschreibung |
|---|---|
| **Zeitpunkt** | Datum und Uhrzeit der Aktion (Zeitzone des Servers) |
| **Benutzer** | Name und E-Mail-Adresse des handelnden Benutzers |
| **Aktion** | Art der durchgeführten Aktion |
| **IP-Adresse** | IP-Adresse, von der die Aktion ausging |
| **User-Agent** | Browser- und Betriebssysteminformation |
| **Details** | Zusätzliche Informationen zur Aktion |

::: tip Hinweis
Das Benutzer-Audit ist ausschließlich für Benutzer mit der Rolle **Admin** zugänglich. Die Audit-Protokolle sind schreibgeschützt und können nicht bearbeitet oder gelöscht werden.
:::

## Protokollierte Aktionen

Das System zeichnet automatisch die folgenden Benutzeraktionen auf:

### Authentifizierung

| Aktion | Beschreibung |
|---|---|
| `login.success` | Erfolgreiche Anmeldung |
| `login.failed` | Fehlgeschlagener Anmeldeversuch |
| `logout` | Abmeldung durch den Benutzer |
| `session.expired` | Automatische Abmeldung nach Sitzungsablauf |
| `password.changed` | Passwortänderung durch den Benutzer |
| `password.reset` | Passwortzurücksetzung durch einen Administrator |

### Benutzerverwaltung

| Aktion | Beschreibung |
|---|---|
| `user.created` | Neues Benutzerkonto wurde angelegt |
| `user.updated` | Benutzerkonto wurde bearbeitet |
| `user.deleted` | Benutzerkonto wurde gelöscht |
| `role.changed` | Rolle eines Benutzers wurde geändert |

### Datenzugriff

| Aktion | Beschreibung |
|---|---|
| `export.triggered` | Export wurde ausgelöst |
| `import.triggered` | Import wurde gestartet |
| `api.access` | API-Zugriff über Token |

## Login-Verlauf

Der Login-Verlauf zeigt eine detaillierte Übersicht aller An- und Abmeldevorgänge. Für jeden Eintrag werden folgende Informationen erfasst:

- **Zeitpunkt** -- Datum und Uhrzeit der Anmeldung
- **IP-Adresse** -- Quell-IP-Adresse der Verbindung
- **Standort** -- Ungefährer Standort basierend auf der IP-Adresse (sofern verfügbar)
- **User-Agent** -- Browser- und Betriebssysteminformation
- **Dauer** -- Dauer der Sitzung bis zur Abmeldung
- **Status** -- Erfolgreiche Anmeldung oder fehlgeschlagener Versuch

::: warning Warnung
Mehrere fehlgeschlagene Anmeldeversuche von derselben IP-Adresse können auf einen Angriffsversuch hindeuten. Überprüfen Sie solche Einträge zeitnah und erwägen Sie gegebenenfalls eine IP-Sperrung auf Serverebene.
:::

## Filteroptionen

Die Audit-Liste kann über mehrere Kriterien gefiltert werden, um relevante Einträge schnell zu finden:

| Filter | Beschreibung | Beispiel |
|---|---|---|
| **Benutzer** | Filtern nach einem bestimmten Benutzer | Max Mustermann |
| **Aktion** | Filtern nach Aktionstyp | `login.success`, `user.created` |
| **Zeitraum** | Filtern nach Datum von/bis | 01.01.2026 -- 31.01.2026 |
| **IP-Adresse** | Filtern nach IP-Adresse | 192.168.1.100 |
| **Status** | Filtern nach Erfolg/Fehler | Nur fehlgeschlagene Aktionen |

Die Filter können beliebig kombiniert werden. Die Ergebnisliste wird automatisch aktualisiert, sobald ein Filterkriterium geändert wird.

## Audit-Log exportieren

Das Audit-Log kann für die externe Archivierung oder Weiterverarbeitung exportiert werden:

1. Setzen Sie die gewünschten Filter, um den Exportumfang einzuschränken.
2. Klicken Sie auf **Exportieren** oberhalb der Ergebnisliste.
3. Wählen Sie das gewünschte Format:

| Format | Beschreibung |
|---|---|
| **CSV** | Kommagetrennte Werte, geeignet für Tabellenkalkulationen |
| **JSON** | Strukturiertes Datenformat für die maschinelle Verarbeitung |
| **PDF** | Druckfertiger Bericht mit Zusammenfassung |

4. Der Download beginnt automatisch.

::: tip Hinweis
Bei großen Datenmengen wird der Export als Hintergrundaufgabe verarbeitet. Sie erhalten eine Benachrichtigung, sobald die Datei zum Download bereitsteht.
:::

## Aufbewahrungszeitraum

Audit-Einträge werden standardmäßig für **365 Tage** aufbewahrt. Nach Ablauf dieses Zeitraums werden die Einträge automatisch archiviert. Der Aufbewahrungszeitraum kann in den Systemeinstellungen angepasst werden.

| Einstellung | Standardwert | Beschreibung |
|---|---|---|
| `audit.retention_days` | 365 | Aufbewahrungszeitraum in Tagen |
| `audit.archive_enabled` | true | Automatische Archivierung aktiviert |
| `audit.log_api_access` | true | API-Zugriffe protokollieren |

## Best Practices

- **Regelmäßige Überprüfung** -- Kontrollieren Sie das Audit-Log mindestens wöchentlich auf ungewöhnliche Aktivitäten.
- **Fehlgeschlagene Logins überwachen** -- Achten Sie auf gehäufte fehlgeschlagene Anmeldeversuche, insbesondere von unbekannten IP-Adressen.
- **Export und Archivierung** -- Exportieren Sie das Audit-Log regelmäßig für die langfristige Aufbewahrung, insbesondere wenn regulatorische Anforderungen bestehen.
- **Datenschutz beachten** -- Beachten Sie bei der Auswertung des Audit-Logs die geltenden Datenschutzrichtlinien (DSGVO). Informieren Sie Ihre Benutzer über die Protokollierung.

## Nächste Schritte

- Erfahren Sie mehr über die [Rollen & Berechtigungen](./rollen), um den Zugriff auf Funktionen zu steuern.
- Nutzen Sie das [Journal](./journal), um Änderungen an Produktdaten nachzuverfolgen.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
