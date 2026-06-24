---
title: Zugangslinks
---

# Zugangslinks

Mit Zugangslinks können Sie externen Personen einen temporären, schreibgeschützten Zugriff auf ausgewählte Produktdaten im anyPIM gewähren, ohne dafür ein Benutzerkonto anlegen zu müssen. Die Links eignen sich beispielsweise für die Freigabe von Produktkatalogen an Handelspartner, Agenturen oder interne Stakeholder.

## Übersicht

Die Verwaltung der Zugangslinks erreichen Sie über **Administration > Zugangslinks** in der Sidebar. Die Übersicht zeigt alle erstellten Links in tabellarischer Form:

| Spalte | Beschreibung |
|---|---|
| **Bezeichnung** | Frei wählbarer Name des Zugangslinks |
| **URL** | Die generierte Link-URL |
| **Gültig bis** | Ablaufdatum des Links |
| **Passwortschutz** | Ob ein Passwort erforderlich ist |
| **Zugriffe** | Anzahl der bisherigen Aufrufe |
| **Status** | Aktiv, abgelaufen oder deaktiviert |
| **Erstellt von** | Benutzer, der den Link erstellt hat |

::: tip Hinweis
Nur Benutzer mit der Rolle **Admin** oder den `access-links.*`-Berechtigungen (`access-links.view`, `access-links.create`, `access-links.delete`) können Zugangslinks erstellen und verwalten.
:::

## Zugangslink erstellen

1. Navigieren Sie zu **Administration > Zugangslinks**.
2. Klicken Sie auf **+ Neuer Zugangslink**.
3. Füllen Sie das Formular aus:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Bezeichnung** | Beschreibender Name (z. B. "Katalog Frühjahr 2026 -- Agentur XY") | Ja |
| **Ablaufdatum** | Datum, an dem der Link automatisch ungültig wird | Ja |
| **Passwort** | Optionales Passwort für zusätzlichen Schutz | Nein |
| **Hierarchieknoten** | Einschränkung auf bestimmte Hierarchieknoten | Nein |
| **Produkte** | Einschränkung auf bestimmte Produkte | Nein |
| **Attributansicht** | Nur bestimmte Attribute anzeigen | Nein |
| **Sprache** | Anzeigesprache der Produktdaten | Ja |

4. Klicken Sie auf **Erstellen**.
5. Kopieren Sie die generierte URL und teilen Sie sie mit dem Empfänger.

::: warning Warnung
Die vollständige Link-URL wird nur einmalig nach der Erstellung angezeigt. Kopieren Sie die URL sofort und bewahren Sie sie sicher auf.
:::

## Zugriffsbeschränkungen

### Einschränkung auf Hierarchien

Sie können den Zugangslink auf einen oder mehrere Hierarchieknoten beschränken. Der Empfänger sieht dann nur Produkte, die den ausgewählten Knoten (und deren Unterknoten) zugeordnet sind. Alle anderen Produktdaten bleiben verborgen.

### Einschränkung auf Produkte

Alternativ oder zusätzlich können Sie den Link auf eine explizite Liste von Produkten einschränken. Wählen Sie die gewünschten Produkte über die Suchfunktion aus.

### Attributansicht

Über die Auswahl einer Attributansicht steuern Sie, welche Produktattribute für den Empfänger sichtbar sind. So können Sie beispielsweise nur Marketingtexte und Bilder freigeben, ohne technische Daten oder Einkaufspreise preiszugeben.

## Passwortschutz

Wenn ein Passwort gesetzt ist, muss der Empfänger beim ersten Aufruf des Links das Passwort eingeben. Die Sitzung bleibt anschließend für die Dauer des Browserfensters aktiv.

::: danger Achtung
Versenden Sie das Passwort immer über einen separaten Kommunikationskanal (z. B. telefonisch oder per SMS), niemals zusammen mit der Link-URL in derselben E-Mail.
:::

## Nutzung nachverfolgen

Im Detailbereich jedes Zugangslinks finden Sie eine Zugriffstatistik:

| Information | Beschreibung |
|---|---|
| **Gesamtzugriffe** | Anzahl aller Aufrufe des Links |
| **Eindeutige Besucher** | Anzahl unterschiedlicher IP-Adressen |
| **Letzter Zugriff** | Datum und Uhrzeit des letzten Aufrufs |
| **Zugriffsprotokoll** | Chronologische Liste aller Einzelzugriffe mit IP, Zeitpunkt und User-Agent |

## Link verwalten

### Link deaktivieren

Sie können einen aktiven Link jederzeit deaktivieren, ohne ihn zu löschen. Klicken Sie dazu auf **Deaktivieren** in der Detailansicht. Der Link ist sofort nicht mehr aufrufbar, kann aber bei Bedarf wieder aktiviert werden.

### Link verlängern

Das Ablaufdatum eines bestehenden Links kann jederzeit geändert werden. Öffnen Sie den Link in der Detailansicht und passen Sie das Datum im Feld **Gültig bis** an.

### Link löschen

Nicht mehr benötigte Links können über die Detailansicht gelöscht werden. Die Zugriffstatistik wird dabei ebenfalls entfernt.

## Best Practices

- **Kurze Gültigkeitsdauer** -- Setzen Sie das Ablaufdatum so knapp wie möglich. Verlängern Sie bei Bedarf statt lange Laufzeiten zu vergeben.
- **Passwortschutz nutzen** -- Aktivieren Sie den Passwortschutz bei sensiblen Produktdaten.
- **Zugriffe überwachen** -- Prüfen Sie regelmäßig die Zugriffstatistiken, um unerwartete Nutzung zu erkennen.
- **Beschreibende Bezeichnungen** -- Verwenden Sie aussagekräftige Namen, die den Zweck und den Empfänger des Links erkennen lassen.
- **Minimale Datenfreigabe** -- Schränken Sie den Zugriff auf die tatsächlich benötigten Hierarchien, Produkte und Attribute ein.

## Nächste Schritte

- Erfahren Sie mehr über die [Rollen & Berechtigungen](./rollen), um den internen Zugriff zu steuern.
- Nutzen Sie das [Benutzer-Audit](./benutzer-audit), um Zugriffe auf das System nachzuverfolgen.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
