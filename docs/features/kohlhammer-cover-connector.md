# Cover-Connector — Konzept

> Inbound-Connector, der COVER-Daten aus einer **beliebigen MySQL-Datenbank** liest.
> Ergänzt die Migrationsanalyse in `kohlhammer-cover-migration.md`.

---

## 1. Abgrenzung zur heutigen Pimcore-Lösung

Heute liegen die gespiegelten `SF_`-Tabellen in derselben Datenbank wie PIMCORE, und
`go.sh` trägt die Zugangsdaten im Klartext. Der Cover-Connector löst beides:

| | PIMCORE heute | Cover-Connector |
|---|---|---|
| Quelle | dieselbe DB (`pimcore5`) | beliebiger MySQL-Host, eigene Credentials |
| Zugang | Klartext in `go.sh` | `ConnectorConnection`, Passwort verschlüsselt |
| Richtung | lesend + schreibend auf einer DB | **ausschließlich lesend** auf der Quelle |
| Delta | Voll-IST-Load + `compareArrays()` je Skript | `LASTCHANGE` + Checksummen je Datensatz |
| Protokoll | `echo` in Logdatei | `ImportJob` / `ImportJobError` / `ConnectorSyncLog` |

---

## 2. Verbindung

### 2.1 Ablage der Zugangsdaten

`ConnectorConnection` bringt bereits alles Nötige mit — `access_token` ist per
`encrypted`-Cast verschlüsselt, `settings` ist ein JSON-Feld:

```jsonc
{
  "connector_type": "cover",
  "name": "Kohlhammer COVER Produktiv",
  "access_token": "<DB-Passwort, verschlüsselt abgelegt>",
  "settings": {
    "host": "cover-db.kohlhammer.de",
    "port": 3306,
    "database": "cover_mirror",
    "username": "anypim_ro",
    "charset": "utf8mb4",
    "collation": "utf8mb4_unicode_ci",
    "table_prefix": "SF_",
    "ssl_ca": null,
    "timeout": 30
  }
}
```

Kein neues Modell, keine Migration. Dass `access_token` hier ein DB-Passwort
transportiert, ist dieselbe Zweckentfremdung, die DeepL bereits für seinen API-Key
nutzt — die Verschlüsselung ist der Punkt, nicht der Feldname.

### 2.2 Laufzeitverbindung

Die Verbindung wird **zur Laufzeit** registriert, nicht in `config/database.php`
eingetragen — es kann mehrere COVER-Verbindungen geben (Produktiv, Test):

```php
// App\Services\Connectors\Cover\CoverConnectionFactory
public function connection(ConnectorConnection $connection): Connection
{
    $name = 'cover_' . $connection->id;
    $s = $connection->settings;

    config(["database.connections.{$name}" => [
        'driver'    => 'mysql',
        'host'      => $s['host'],
        'port'      => $s['port'] ?? 3306,
        'database'  => $s['database'],
        'username'  => $s['username'],
        'password'  => $connection->access_token,
        'charset'   => $s['charset'] ?? 'utf8mb4',
        'collation' => $s['collation'] ?? 'utf8mb4_unicode_ci',
        'strict'    => false,          // Altdaten-Schema, keine strengen Modes erzwingen
        'options'   => $this->pdoOptions($s),
    ]]);

    DB::purge($name);                  // alte Instanz verwerfen, falls Settings geändert

    return DB::connection($name);
}
```

**Verbindlich:** nur `SELECT`. Es gibt keinen Schreibpfad Richtung COVER. Der
DB-Benutzer auf der Gegenseite ist read-only anzulegen — das ist die eigentliche
Absicherung, nicht die Disziplin im Code.

### 2.3 `ConnectorInterface`

Der Cover-Connector implementiert das bestehende Interface und stubbt die
OAuth-Methoden aus — exakt wie `DeepLConnector`:

| Methode | Verhalten |
|---|---|
| `getType()` | `'cover'` |
| `getCapabilities()` | `['inbound_sync', 'product_data', 'master_data']` |
| `isConfigured()` | mindestens eine aktive `cover`-Verbindung vorhanden |
| `getAuthorizationUrl()` / `handleCallback()` / `refreshToken()` | leer / no-op |
| `uploadAsset()` / `pushProductData()` | `RuntimeException` — der Connector ist rein eingehend |

Ergänzend eine eigene, nicht im Interface verankerte Methode `testConnection()`
(`SELECT 1` + Schema-Prüfung) für den Verbindungstest in der GUI.

---

## 3. Bausteine

```
app/Services/Connectors/Cover/
├── CoverConnector.php           ConnectorInterface, Registry-Eintrag
├── CoverConnectionFactory.php   Laufzeit-DB-Verbindung (siehe 2.2)
├── CoverSchemaInspector.php     Vorabprüfung: Tabellen/Spalten vorhanden?
├── CoverProfiler.php            SELECT DISTINCT für Schritt 2 der Migration
├── Readers/                     je Quellbereich ein Reader, liefert Generatoren
│   ├── ProductReader.php        SF_PRODUCT
│   ├── PriceReader.php          SF_PRODUCTPRICE
│   ├── ContributorReader.php    SF_PRODUCTCONTRIBUTOR
│   ├── AddressReader.php        SF_ADRESSEN + SF_EMAILADRESSEN
│   ├── SubjectReader.php        SF_PRODUCTSUBJECT (Schema 20 / 23,26 / 100*)
│   ├── TextReader.php           SF_PRODUCTOTHERTEXT
│   ├── RelationReader.php       SF_PRODUCTSERIES / -SETS / -RELATEDPRODUCT / -CONTAINEDITEM
│   ├── CategoryReader.php       SF_WK_KATEGORIEN
│   └── ServiceReader.php        SF_PRODUCTDIENSTLEISTER + SF_PRODUCTBEARBEITER
└── CoverImportService.php       Orchestrierung, Upsert, Protokollierung
```

`CoverSchemaInspector` läuft vor jedem Import: fehlt eine erwartete Tabelle oder
Spalte, bricht der Job **vor** dem ersten Schreibzugriff mit klarer Meldung ab.
Das ersetzt das heutige Verhalten, bei dem fehlende Spalten als PHP-Notice
durchrutschen und stillschweigend Leerwerte erzeugen.

---

## 4. Import-Ablauf

Reihenfolge wie in `go.sh`, aber mit strikter Phasentrennung — alle Stammdaten sind
angelegt, bevor Beziehungen gezogen werden:

| Phase | Inhalt | Abhängig von |
|---|---|---|
| 0 | Verbindungs- und Schemaprüfung | — |
| 1 | Stammdaten: Adressen (+ E-Mail), Contributor | — |
| 2 | Kategorie-/Vorschaubäume → Hierarchieknoten | — |
| 3 | Produkte (`SF_PRODUCT`) inkl. Attributwerte | 1 |
| 4 | Preise, Texte, Subjects, Schlagworte, Bearbeiter, Dienstleister | 3 |
| 5 | Beziehungen: Contributor, Series, Sets, Relations, Bundle | 1, 3 |
| 6 | Hierarchiezuordnungen: Kategorien, Lizenzkategorien | 2, 3 |
| 7 | Medien (Cover) | 3 |

Phase 5 nach Phase 3 zu legen beseitigt das heutige Problem, dass Relationsskripte
mit `365` laufen müssen, weil das Zielprodukt sonst noch nicht existiert.

### 4.1 Delta

Zwei Stufen, die sich ergänzen:

1. **Grobfilter am Quellsystem** — `LASTCHANGE >= :since` auf `SF_PRODUCT`,
   parametrisiert (nicht wie heute per String-Konkatenation `< 0` + Tageszahl).
   `--since-days=0` bzw. `--full` lädt alles.
2. **Feinfilter lokal** — je Datensatz eine Checksumme über alle Quellzeilen dieses
   Produkts, abgelegt in `ConnectorProductChecksum` (`connection_id`, `product_id`,
   `checksum`, `synced_at`). Nur bei Abweichung wird geschrieben.

Damit entfällt der heutige Voll-IST-Load aus den Pimcore-Objekttabellen komplett.
Weil Contributor und Adressen in anyPIM ebenfalls Produkte sind (siehe
Migrationsanalyse 7.4), deckt dieselbe Checksummen-Tabelle auch sie ab.

### 4.2 Löschabgleich

Neu gegenüber heute und **optional pro Lauf** (`--reconcile`): Bei einem Volllauf
werden lokale Datensätze, deren Quellschlüssel in COVER nicht mehr existiert, auf
`status = 'discontinued'` gesetzt — nie hart gelöscht. Bei Delta-Läufen findet kein
Abgleich statt, weil das Delta-Fenster keine Aussage über Gelöschtes trifft.

### 4.3 Protokollierung

`ImportJob` je Lauf, `ImportJobError` je fehlerhaftem Datensatz (Quelltabelle,
Schlüssel, Spalte, Wert, Fehler). Unbekannte Codes — ein `TEXTTYPECODE` ohne
Attribut, ein `SUBJECTCODE` ohne Wertelisteneintrag — sind **Fehlerzeilen, keine
Auto-Anlage**. Das ist der bewusste Unterschied zu PIMCORE, wo solche Codes zur
Laufzeit neue Classificationstore-Keys erzeugen.

---

## 5. Artisan-Command

```
pim:cover-import
    {--connection=      : ID oder Name der ConnectorConnection}
    {--since-days=      : Delta-Fenster in Tagen (Standard: 1)}
    {--full             : Volllauf ohne Delta-Filter}
    {--only=            : Phasen kommagetrennt (products,prices,contributors,...)}
    {--record=          : einzelner RECORDIDENTIFIER (Debug/Nachzug)}
    {--reconcile        : Löschabgleich bei Volllauf}
    {--dry-run          : nur analysieren und protokollieren, nichts schreiben}
```

`--record` erhält bewusst die heutige `$argv[2]`-Funktion aus den Pimcore-Skripten;
`--dry-run` ist neu und macht die Testmigration überprüfbar.

---

## 6. Offene Punkte

1. **Netzweg:** Direktverbindung, SSH-Tunnel oder VPN? Beeinflusst `ssl_ca`/Timeouts.
2. **Zeichensatz:** Die Pimcore-Skripte arbeiten mit `utf8_encode()`/`utf8_decode()` —
   die Quelle ist also vermutlich `latin1`. Vor dem ersten Lauf am Bestand prüfen und
   in `settings.charset` festschreiben, sonst entstehen erneut kaputte Umlaute.
3. **Datenqualitätsbefund E-Mail:** `import_sf_email.php` schlüsselt seine
   Vergleichsarrays nur über `ADR_NR`, schreibt aber über `ADR_NR-PERSON_NR`. Bei
   mehreren Personen je Adresse ist die Delta-Erkennung dort heute unvollständig.
   Vor der Migration prüfen, wie viele `SF_EMAILADRESSEN`-Zeilen je
   `ADR_NR`/`PERSON_NR`/`TEL_ART` existieren — davon hängt ab, ob E-Mail ein
   einfaches oder ein vermehrbares Attribut wird.
4. **Lesender DB-Benutzer:** Muss von Cover/Kohlhammer bereitgestellt werden.
