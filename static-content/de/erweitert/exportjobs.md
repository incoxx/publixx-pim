---
title: Export-Jobs
---

# Export-Jobs

Export-Jobs im anyPIM ermöglichen den automatisierten und zeitgesteuerten Export von Produktdaten an externe Systeme, Partner und Vertriebskanäle. Sie definieren Exportprofile mit Feldzuordnungen, Filtern und Zustelloptionen und können diese einmalig oder nach einem wiederkehrenden Zeitplan ausführen.

## Übersicht

Über den Menüpunkt **Export-Jobs** in der Sidebar gelangen Sie zur Übersicht aller konfigurierten Export-Jobs:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung des Export-Jobs |
| **Format** | Ausgabeformat (CSV, JSON, XML, Excel) |
| **Zeitplan** | Ausführungsintervall (manuell, täglich, wöchentlich etc.) |
| **Zustellung** | Zustellweg (SFTP, Webhook, E-Mail, Download) |
| **Letzte Ausführung** | Zeitpunkt und Status der letzten Ausführung |
| **Status** | Aktiv oder deaktiviert |

## Export-Job anlegen

Klicken Sie auf **+ Neuer Export-Job**, um einen neuen Job zu konfigurieren.

### Schritt 1: Grundeinstellungen

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Bezeichnung des Export-Jobs | Ja |
| **Beschreibung** | Erläuterung des Exportzwecks | Nein |
| **Format** | Ausgabeformat (CSV, JSON, XML, Excel) | Ja |
| **Zeichenkodierung** | UTF-8, ISO-8859-1 oder andere | Ja |
| **Trennzeichen** (nur CSV) | Spaltentrennzeichen (Semikolon, Komma, Tab) | Bedingt |

### Schritt 2: Exportprofil konfigurieren

Im Exportprofil definieren Sie, welche Daten exportiert werden und in welcher Struktur sie ausgegeben werden.

#### Feldzuordnung

Ordnen Sie die gewünschten Felder dem Export zu. Für jedes Feld können Sie folgende Einstellungen vornehmen:

| Einstellung | Beschreibung |
|---|---|
| **Quellfeld** | Produktattribut, Stammdatenfeld oder Preis |
| **Spaltenname** | Bezeichnung der Spalte in der Exportdatei |
| **Transformation** | Optionale Umwandlung des Werts (z.B. Grossschreibung, Formatierung) |
| **Standardwert** | Wert, der verwendet wird, wenn das Quellfeld leer ist |
| **Sprache** | Sprache des Attributwerts (bei übersetzbaren Attributen) |

::: tip Hinweis
Sie können dasselbe Attribut mehrfach mit unterschiedlichen Sprachen zuordnen, um z.B. den Produktnamen in Deutsch und Englisch als separate Spalten zu exportieren.
:::

#### Filter

Definieren Sie Bedingungen, um den Export auf bestimmte Produkte einzuschränken:

- **Statusfilter** -- Nur Produkte mit einem bestimmten Status exportieren (z.B. nur „Aktiv").
- **Attributfilter** -- Produkte anhand von Attributwerten filtern.
- **Herstellerfilter** -- Nur Produkte bestimmter Hersteller einbeziehen.
- **Hierarchiefilter** -- Export auf bestimmte Kategorien beschränken.
- **Preisregion** -- Nur Produkte mit Preisen in einer bestimmten Region exportieren.

Filter können mit UND/ODER-Logik kombiniert werden.

### Schritt 3: Zeitplan

Konfigurieren Sie den Ausführungszeitplan für den Export-Job:

| Intervall | Beschreibung |
|---|---|
| **Manuell** | Der Job wird nur bei manueller Auslösung ausgeführt |
| **Täglich** | Tägliche Ausführung zu einer definierten Uhrzeit |
| **Wöchentlich** | Wöchentliche Ausführung an einem bestimmten Wochentag und Uhrzeit |
| **Monatlich** | Monatliche Ausführung an einem bestimmten Tag des Monats |
| **Benutzerdefiniert** | Individuelles Intervall per Cron-Ausdruck |

::: warning Warnung
Stellen Sie sicher, dass der Zeitplan mit den Anforderungen des Empfängersystems kompatibel ist. Häufige Exporte mit grossen Datenmengen können die Systemleistung beeinträchtigen.
:::

### Schritt 4: Zustellung

Legen Sie fest, wie die exportierte Datei zugestellt wird:

| Zustellweg | Beschreibung |
|---|---|
| **SFTP** | Übertragung auf einen SFTP-Server (Host, Port, Benutzer, Pfad) |
| **Webhook** | HTTP-POST-Anfrage an eine URL mit der Datei als Payload |
| **E-Mail** | Versand der Datei als E-Mail-Anhang an definierte Empfänger |
| **Download** | Bereitstellung im Download-Bereich des anyPIM |

#### SFTP-Konfiguration

| Feld | Beschreibung |
|---|---|
| **Host** | Hostname oder IP-Adresse des SFTP-Servers |
| **Port** | Portnummer (Standard: 22) |
| **Benutzername** | Anmeldename auf dem SFTP-Server |
| **Authentifizierung** | Passwort oder SSH-Schlüssel |
| **Zielverzeichnis** | Pfad auf dem Server, in den die Datei hochgeladen wird |
| **Dateiname** | Name der Exportdatei (Platzhalter wie `{date}` und `{time}` möglich) |

## Ausführungshistorie

Für jeden Export-Job führt das anyPIM eine Ausführungshistorie, die Sie über den Tab **Historie** in der Job-Detailansicht einsehen können:

| Spalte | Beschreibung |
|---|---|
| **Ausführungszeitpunkt** | Datum und Uhrzeit der Ausführung |
| **Status** | Erfolgreich, fehlgeschlagen oder abgebrochen |
| **Dauer** | Dauer der Ausführung |
| **Exportierte Produkte** | Anzahl der exportierten Datensätze |
| **Dateigrösse** | Grösse der generierten Exportdatei |
| **Download** | Link zum Herunterladen der Exportdatei |

### Fehlerbehandlung

Bei fehlgeschlagenen Exporten werden detaillierte Fehlermeldungen protokolliert. Typische Fehlerursachen sind:

- **SFTP-Verbindungsfehler** -- Der Zielserver ist nicht erreichbar oder die Anmeldedaten sind falsch.
- **Webhook-Timeout** -- Die Ziel-URL antwortet nicht innerhalb der Frist.
- **Datenvalidierung** -- Ein Pflichtfeld im Exportprofil ist bei einem oder mehreren Produkten leer.

### Automatische Wiederholung

Sie können für jeden Export-Job eine automatische Wiederholung bei Fehlern konfigurieren:

1. Öffnen Sie den Export-Job und wechseln Sie zum Abschnitt **Fehlerbehandlung**.
2. Aktivieren Sie **Automatisch wiederholen**.
3. Legen Sie die maximale Anzahl der Wiederholungsversuche fest (z.B. 3).
4. Definieren Sie das Intervall zwischen den Versuchen (z.B. 15 Minuten).

::: danger Achtung
Bei Webhook-Zustellungen kann eine automatische Wiederholung zu doppelten Datenlieferungen führen, wenn der Empfänger die erste Lieferung zwar erhalten, aber nicht korrekt bestätigt hat. Klären Sie die Idempotenz mit dem Empfängersystem ab.
:::

## Export-Jobs manuell ausführen

Unabhängig vom konfigurierten Zeitplan können Sie einen Export-Job jederzeit manuell starten:

1. Öffnen Sie den gewünschten Export-Job.
2. Klicken Sie auf **Jetzt ausführen**.
3. Der Export wird im Hintergrund gestartet. Den Fortschritt können Sie in der Ausführungshistorie verfolgen.

## Nächste Schritte

- Nutzen Sie den [Planungskalender](./planungskalender), um Ihre Export-Jobs visuell zu planen.
- Konfigurieren Sie [Preisregionen](./preisregionen) für regionsspezifische Exporte.
- Erstellen Sie [Berichte](./berichte) für die Analyse Ihrer Exportdaten.
