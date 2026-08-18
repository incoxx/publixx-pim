# Kohlhammer / COVER → anyPIM — Migrationsanalyse

> **Stand:** Schritt 1 — Analyse der bestehenden PIMCORE-Importstrecke, Grundsatzentscheidungen getroffen
> **Quellrepo:** `incoxx/kohlhammer-pimcore`, Verzeichnis `src/AppBundle/Backend/Import/`
> **Zielsystem:** anyPIM (`incoxx/publixx-pim`)
>
> **Getroffene Entscheidungen** (Details in Abschnitt 7.0, 7.2, 7.4):
> 1. Übernahme über einen **Cover-Connector** auf eine beliebige MySQL-Datenbank —
>    Konzept in [`kohlhammer-cover-connector.md`](kohlhammer-cover-connector.md)
> 2. Zusätzliche Preisspalten aus COVER über ein neues Feature **Preis-Metadaten** —
>    Plan in [`plan-preis-metadaten.md`](plan-preis-metadaten.md)
> 3. Nicht-Produkt-Stammobjekte (Autoren, Adressen) als **eigene Produkttypen**

---

## 1. Ausgangslage

Der Verlag W. Kohlhammer betreibt sein Redaktionssystem bei der Firma **Cover**
(covernet.de). Aus Cover werden Tabellen **in dieselbe MySQL-Datenbank gespiegelt**,
in der auch PIMCORE 5/6 läuft (DB `pimcore5`). Die Spiegeltabellen tragen alle das
Präfix `SF_`.

Der Import ist damit **kein Datei-/API-Import, sondern ein reiner SQL→SQL-Abgleich**
innerhalb einer Datenbank: Jedes Skript liest den IST-Zustand aus den Pimcore-
Objekttabellen (`object_store_*`, `object_relations_*`, `object_metadata_*`,
`object_collection_*`), liest den SOLL-Zustand aus den `SF_`-Tabellen, vergleicht
beides im Speicher und schreibt nur bei Abweichung über die Pimcore-API zurück.

Das ist die zentrale Eigenschaft, die für anyPIM erhalten bleiben muss:
**Delta-Erkennung und Idempotenz**.

### 1.1 Orchestrierung: `go.sh`

```bash
mysql pimcore5 -u pimcore5 -p'…' < createindex.sql   # Indizes auf den SF_-Tabellen

php import_sf_product.php            5     # Stammdaten Produkt
php import_sf_price.php              5     # Preise
php import_sf_series.php           365     # Reihen (+ Rückbeziehung)
php import_sf_contributor.php        5     # Mitwirkende / Autoren
php import_sf_otherproduct.php     365     # Produkt-zu-Produkt-Beziehungen
php import_sf_othertext.php          5     # Langtexte
php import_sf_subject.php            5     # Sachgruppen / Klassifikationen
php import_sf_keyword.php            5     # Schlagworte
php import_sf_bearbeiter.php         5     # Lektorat / Bearbeiter
php import_sf_adressen.php           5     # Adressen
php import_sf_email.php              5     # E-Mail-Adressen
php import_sf_containeditem.php    365     # Bundle-Bestandteile
php import_sf_dienstleister.php      5     # Dienstleister je Produkt
php import_sf_series.php           365     # (bewusst ein zweites Mal)
php import_sf_sets.php             365     # Sets (+ Rückbeziehung)
php import_sf_categories_tree.php          # Kategoriebaum (WK)
php import_sf_categories.php         5     # Produkt → Kategorie
php import_sf_categories_lizenztitel.php 5 # Produkt → Lizenzkategorie (EN)
```

**Aufrufkonvention:**

| Argument | Bedeutung |
|----------|-----------|
| `$argv[1]` | Delta-Fenster in Tagen — geht als `datediff(now(), b.LASTCHANGE) < 0N` in das SQL ein |
| `$argv[2]` | optional: einzelner `RECORDIDENTIFIER` → gezielter Einzelimport (Debug/Nachzug) |

Varianten `go7.sh`, `go30.sh`, `go180.sh`, `gotest.sh` unterscheiden sich **nur** im
Delta-Fenster. Relationsskripte (`series`, `sets`, `otherproduct`, `containeditem`)
laufen bewusst immer mit `365`, weil eine Änderung am *Zielprodukt* sonst nicht
erkannt würde.

**Hinweis zum `datediff`-Ausdruck:** `datediff(now(), LASTCHANGE) < 0` + angehängte
Tageszahl ergibt `< 05` / `< 0365`. Der Delta-Filter funktioniert, ist aber
string-konkateniert und damit fehleranfällig — in anyPIM sauber parametrisieren.

### 1.2 Idempotenz-Muster (in jedem Skript identisch)

```
1. IST laden   → $arr0  (aus object_store_7 / object_collection_* / object_metadata_7)
2. SOLL laden  → $arr   (aus SF_*)
3. compareArrays($partnr, $arr, $arr0)   → nur bei true schreiben
4. Pimcore::collectGarbage() alle 1000 Datensätze
```

`compareArrays()` ist pro Skript unterschiedlich implementiert (Feldvergleich,
Count-Vergleich, Rollen-/Sortier-Vergleich). Bei `import_sf_containeditem.php` ist
der Vergleich per `if (true)` deaktiviert — dort wird immer geschrieben.

### 1.3 Bekannte Altlasten in der Importstrecke

| Beobachtung | Auswirkung |
|-------------|-----------|
| DB-Zugangsdaten im Klartext in `go*.sh` | Sicherheitsproblem, in anyPIM über `.env` lösen |
| `utf8_encode()`/`utf8_decode()` in `makeKey()`, teils inkonsistent (Product vs. Contributor) | Umlaut-Keys können je nach Skript abweichen |
| Doppelte Skripte (`_`-Varianten, `backup/`) | Unklar, welche Version produktiv ist — `go.sh` ist die Referenz |
| Kein Löschabgleich | In COVER gelöschte Sätze bleiben in Pimcore stehen |
| Kein Transaktions-/Fehlerabbruch | Fehler werden per `echo` protokolliert, Lauf läuft weiter |
| `import_sf_bearbeiter.php` liest zuerst nicht existente Preisspalten in `$keyCombined` (wird direkt danach überschrieben) | Toter Code, ohne Wirkung |

---

## 2. Skript-Inventar

| # | Skript | Quelle (COVER) | Ziel (PIMCORE) | Art |
|---|--------|----------------|----------------|-----|
| 1 | `import_sf_product.php` | `SF_PRODUCT` | `DataObject\Produkt` (Klasse 7) | ~92 Stammdatenfelder |
| 2 | `import_sf_price.php` | `SF_PRODUCTPRICE` | Fieldcollection `Preis` + 4 Kopffelder | Preisstaffel |
| 3 | `import_sf_series.php` | `SF_PRODUCTSERIES` | Relation `SERIES` / `REVERSESERIES` | Produkt↔Produkt + Metadaten |
| 4 | `import_sf_contributor.php` | `SF_PRODUCTCONTRIBUTOR` | `DataObject\Contributor` (10) + Relation `CONTRIBUTORS` | **Autoren/Mitwirkende** |
| 5 | `import_sf_otherproduct.php` | `SF_PRODUCTRELATEDPRODUCT` | Relation `RELATIONS` | Produkt↔Produkt |
| 6 | `import_sf_othertext.php` | `SF_PRODUCTOTHERTEXT` | Classificationstore `Othertext` + `AUTOR`, `RUECKENTEXT` | Langtexte |
| 7 | `import_sf_subject.php` | `SF_PRODUCTSUBJECT` (Schema 23, 26) | Classificationstore `Subject` + `INLIZENZ` | Klassifikationen |
| 8 | `import_sf_keyword.php` | `SF_PRODUCTSUBJECT` (Schema 20) | `DataObject\Keyword` (11) + Relation `KEYWORDS` | Schlagworte |
| 9 | `import_sf_bearbeiter.php` | `SF_PRODUCTBEARBEITER` | Fieldcollection `Bearbeiter` | Lektorat |
| 10 | `import_sf_adressen.php` | `SF_ADRESSEN` | `DataObject\Adresse` (12) | Adressstamm |
| 11 | `import_sf_email.php` | `SF_EMAILADRESSEN` | `DataObject\Email` (13) | E-Mail-Stamm |
| 12 | `import_sf_containeditem.php` | `SF_PRODUCTCONTAINEDITEM` | Relation `BUNDLE` | Bundle-Bestandteile |
| 13 | `import_sf_dienstleister.php` | `SF_PRODUCTDIENSTLEISTER` | Classificationstore `Dienstleister` | Dienstleister je Typ |
| 14 | `import_sf_sets.php` | `SF_PRODUCTSETS` | Relation `SETS` / `REVERSESETS` | Set-Zugehörigkeit |
| 15 | `import_sf_categories_tree.php` | `SF_WK_KATEGORIEN` | `DataObject\Kategorie` (34) | Kategoriebaum, 3 Ebenen |
| 16 | `import_sf_categories.php` | `SF_PRODUCTSUBJECT` (Schema `100%`) | Relation `CATEGORIES` | Produkt → Kategorie |
| 17 | `import_sf_categories_lizenztitel.php` | `SF_PRODUCTSUBJECT` + `INLIZENZ` | Relation `LICENCECATEGORIES` | Produkt → EN-Kategorie |

**Nicht in `go.sh` — separat/manuell gefahren:**

| Skript | Zweck |
|--------|-------|
| `import_sf_categories_vorschau_tree.php` | Vorschau-Kategoriebaum (`DataObject\Vorschau`) inkl. Produktzuordnung |
| `import_sf_categories_buchinfo_tree.php` | Buchinfo-Baum (`DataObject\Vorschau`) |
| `import_sf_categories_lizenz_tree.php` | Lizenz-Baum (`DataObject\Vorschau`) |
| `import_zeitschriften_struktur.php` | Zeitschrift → Jahrgang → Heft (3 eigene Klassen) |
| `import_zeitschriften_struktur_bilder.php` | Cover-Bilder zu Heften |
| `import_sf_productimages.php` | Cover-Assets `BILD_K` / `BILD_G` aus Dateisystem (Pimcore-4-Altcode) |
| `import_sf_mediando.php`, `import_sf_mediandomedia.php`, `import_mediando.php` | Mediando-Anbindung (E-Book-Distribution) |
| `import_sf_categories_csv.php` | Kategoriezuordnung aus CSV |

---

## 3. Quelldatenmodell COVER (`SF_*`)

| Tabelle | Schlüssel | Wesentliche Spalten |
|---------|-----------|---------------------|
| `SF_PRODUCT` | `RECORDIDENTIFIER` | ONIX-nahes Stammsatz-Schema, ~92 Felder, `LASTCHANGE` als Delta-Marker |
| `SF_PRODUCTPRICE` | `RECORDIDENTIFIER` + `PRICETYPECODE` + `PRICESTATUS` + `CURRENCYCODE` + `PRICEEFFECTIVEFROM` + `MINIMUMORDERQUANTITY` | `PRICEAMOUNT`, `COUNTRYCODE`, `DISCOUNTPERCENT`, `DISCOUNTGROUP`, `BATCHQUANTITY`, `TAXRATE*1/2` |
| `SF_PRODUCTCONTRIBUTOR` | `RECORDIDENTIFIER` + `CONTRIBUTORROLE` + `KEYNAMES` | `NAMESBEFOREKEY`, `SEQUENCENUMBER`, `ADR_NR`, `PERSON_NR` |
| `SF_PRODUCTSERIES` | `RECORDIDENTIFIER` + `SERIESID` | `TITLEOFSERIES`, `NUMBERWITHINSERIES` |
| `SF_PRODUCTSETS` | `RECORDIDENTIFIER` + `SETID` | `NUMBERWITHINSET` |
| `SF_PRODUCTRELATEDPRODUCT` | `RECORDIDENTIFIER` + `PRODUCTIDENTIFIER` | `RELATIONCODE`, `PRODUCTFORM` |
| `SF_PRODUCTCONTAINEDITEM` | `RECORDIDENTIFIER` + `PRODUCTIDENTIFIER` | `AUSLEITUNG`, `ANTEIL` |
| `SF_PRODUCTOTHERTEXT` | `RECORDIDENTIFIER` + `TEXTTYPECODE` | `OTHERTEXT` (Langtext) |
| `SF_PRODUCTSUBJECT` | `RECORDIDENTIFIER` + `SUBJECTSCHEMEIDENTIFIER` + `SUBJECTCODE` | `SUBJECTSCHEMENAME`, `SUBJECTHEADINGTEXT` |
| `SF_PRODUCTBEARBEITER` | `RECORDIDENTIFIER` + `ROLLE` + `BENUTZER_ID` | `KZ_HAUPT_LEKTOR`, `NAME` |
| `SF_PRODUCTDIENSTLEISTER` | `RECORDIDENTIFIER` + `DL_TYP_BEZ` | `ADR_NR` |
| `SF_ADRESSEN` | `ADR_NR` (+ `PERSON_NR`) | `ADR_GR`, `LEKTORAT`, `ADRESS_TYP`, `VORNAME`, `NACHNAME`, `NAME1..3`, `ANREDE_CODE/_ETIKETT`, `TITEL_CODE/_ETIKETT`, `LAND_CODE`, `PLZ`, `ORT`, `STRASSE`, `DATUM_AEND` |
| `SF_EMAILADRESSEN` | `ADR_NR` + `PERSON_NR` | `TEL_ART`, `EMAIL` |
| `SF_WK_KATEGORIEN` | `KAT_2_ID` | `KAT_2_BEZ`, `KAT_2_BEZ_ENGL`, `EBENE` (`3 PRODUKT`) |

### 3.1 `SUBJECTSCHEMEIDENTIFIER` — ein Feld, vier Bedeutungen

`SF_PRODUCTSUBJECT` ist die am stärksten überladene Quelltabelle. Der Schema-Code
entscheidet, in welches Zielkonstrukt der Satz wandert:

| Schema | Verwendet von | Ziel |
|--------|---------------|------|
| `20` | `import_sf_keyword.php` | `DataObject\Keyword` + Relation `KEYWORDS` |
| `23`, `26` | `import_sf_subject.php` | Classificationstore-Gruppe `23`, Key = `SUBJECTSCHEMENAME` |
| `100*` | `import_sf_categories.php` | Relation `CATEGORIES` → `Kategorie.REFCODE = SUBJECTCODE` |
| `100*` + zusätzlich Satz mit `SUBJECTSCHEMENAME='INLIZENZ'` | `import_sf_categories_lizenztitel.php` | Relation `LICENCECATEGORIES` → `REFCODE = 'EN-' + substr(code,0,3) + '000000'` |

Im Subject-Import werden zwei Sprachschichten geschrieben:
`setLocalizedKeyValue($group, $key, SUBJECTHEADINGTEXT)` ohne Sprache und
`setLocalizedKeyValue($group, $key, SUBJECTCODE, 'de')` — also **Klartext als
Default, Code als deutsche Ausprägung**. Beim Übertragen nach anyPIM ist das
aufzulösen: Code und Klartext gehören in Werteliste + Eintrag, nicht in zwei
Sprachschichten.

---

## 4. PIMCORE-Zielmodell

### 4.1 Objektklassen

| Klasse | `object_store_*` | Schlüsselfeld | Ablage | Inhalt |
|--------|------------------|---------------|--------|--------|
| `Produkt` | 7 | `RECORDIDENTIFIER` | `/produkte/{PRODUKTTYP}` | Titel-/Artikelstammsatz |
| `Contributor` | 10 | `SYSKEY` (normalisierter Name), `FULLKEY` | `/contributor` | **Autoren, Herausgeber, Übersetzer …** |
| `Keyword` | 11 | `SYSKEY` | `/keyword` | Schlagwort |
| `Adresse` | 12 | `ADR_NR` (+ `PERSON_NR`), `SYSKEY` | `/adressen` | Adress-/Personenstamm |
| `Email` | 13 | `FULLKEY` = `ADR_NR-PERSON_NR` | `/email` | E-Mail zu Adresse |
| `Kategorie` | 34 | `REFCODE` | `/shop` | Warengruppenbaum (3 Ebenen) |
| `Vorschau` | — | `REFCODE` | eigener Baum | Vorschau-/Buchinfo-/Lizenz-Bäume inkl. Produktliste |
| `Zeitschrift` | — | `ISSN` | `/zeitschriften` | Zeitschriftentitel |
| `Zeitschriftjahrgang` | — | `ISSN_JG` | unter Zeitschrift | Jahrgang |
| `Zeitschriftjahrgangheft` | — | `ISSN_JG_HEFT` | unter Jahrgang | Heft, verlinkt Produkte + Cover |

### 4.2 Fieldcollections (n:1-Wertelisten am Produkt)

| Fieldcollection | Tabelle | Felder |
|-----------------|---------|--------|
| `Preis` | `object_collection_preis_7` | `PRICETYPECODE`, `PRICESTATUS`, `PRICEAMOUNT`, `CURRENCYCODE`, `COUNTRYCODE`, `PRICEEFFECTIVEFROM/UNTIL`, `DISCOUNTPERCENT`, `DISCOUNTGROUP`, `MINIMUMORDERQUANTITY`, `BATCHQUANTITY`, `TAXRATECODE/PERCENT/ABLEAMOUNT/AMOUNT 1+2` |
| `Bearbeiter` | `object_collection_bearbeiter_7` | `ROLLE`, `BENUTZER_ID`, `KZ_HAUPT_LEKTOR`, `NAME` |

### 4.3 Classification Stores (dynamische Attribute)

Drei Stores am Produkt, deren Gruppen **und Keys zur Laufzeit angelegt werden**,
sobald ein unbekannter Code auftaucht (`GroupConfig`, `KeyConfig`,
`KeyGroupRelation`):

| Store-Feld | Gruppe | Key | Wert |
|-----------|--------|-----|------|
| `Othertext` | `OTHERTEXT` | `TEXTTYPECODE` (z. B. `AS`, `RT`) | `OTHERTEXT` |
| `Subject` | `23` | `SUBJECTSCHEMENAME` (z. B. `ZUSTAND`, `BISAC`, `INLIZENZ`) | Klartext + Code |
| `Dienstleister` | `DIENSTLEISTER` | `DL_TYP_BEZ` | `ADR_NR` |

Ausgewählte Werte werden **zusätzlich** in native Produktfelder gespiegelt, damit
sie sortier-/filterbar sind: `AS → AUTOR`, `RT → RUECKENTEXT`, `INLIZENZ → INLIZENZ`.
Dasselbe Muster im Preisimport (`LP-Deutschland → LPPRICE`, `CA-Deutschland →
CAPRICE`, `LP-Schweiz → LPPRICECH`, `LP-Österreich → LPPRICEAT`, `DISCOUNTPERCENT`).

Vor jedem Schreiben wird der Store **feldweise geleert** (alle aktiven Gruppen auf
`''`), erst danach neu befüllt — ein Voll-Replace, kein Merge.

### 4.4 Relationen mit Metadaten (`ObjectMetadata`)

Das ist das Konstrukt mit dem größten Abbildungsbedarf: eine
Advanced-Many-to-Many-Relation, deren **Kanten eigene Felder tragen**
(`object_metadata_7`).

| Produktfeld | Ziel | Metadaten-Spalten |
|-------------|------|-------------------|
| `CONTRIBUTORS` | `Contributor` | `role`, `sort`, `person`, `adressid` |
| `KEYWORDS` | `Keyword` | `keyword` |
| `SERIES` | `Produkt` (Reihe) | `title`, `series`, `number` |
| `REVERSESERIES` | `Produkt` (Band) | `number` (+ Titel aus Zielobjekt) |
| `SETS` | `Produkt` (Set) | `titel`, `set`, `number` |
| `REVERSESETS` | `Produkt` (Setteil) | `number` |
| `RELATIONS` | `Produkt` | `title`, `isbn`, `relationcode`, `productform` |
| `BUNDLE` | `Produkt` | `ausleitung`, `anteil` |
| `CATEGORIES` | `Kategorie` | — (einfache Relation) |
| `LICENCECATEGORIES` | `Kategorie` | — (einfache Relation) |

**Besonderheit Contributor:** Hat eine Person am selben Produkt mehrere Rollen,
werden diese **nicht als mehrere Kanten**, sondern als *slash-separierte Strings in
einer Kante* abgelegt:

```
role     = "A01/B01"
sort     = "01/02"
adressid = "4711-1/4711-1"
```

Das ist ein Denormalisierungs-Workaround für die Pimcore-Kantenstruktur, der in
anyPIM **nicht nachgebaut, sondern aufgelöst** werden sollte (eine Kante je Rolle).

### 4.5 Rollenschlüssel (ONIX-Liste 17)

Im Frontend/Export ausgewertet: `A01` Autor, `B01` Herausgeber, `B09`
Reihenherausgeber, daneben `A32`, `B17`, `B99`. Suche und XML-Export filtern
explizit auf diese Codes — die Rolle ist also **fachlich relevant, nicht nur
Beiwerk**.

### 4.6 Verkettung Contributor → Adresse → E-Mail

`Contributor` selbst trägt nur Name (`SYSKEY`) und `FULLKEY`. Alle Personendaten
hängen an der Relations-Metaspalte `adressid` im Format `ADR_NR-PERSON_NR`, über die
zur Laufzeit `Adresse` und `Email` nachgeladen werden (siehe
`app/Resources/views/Metadata/inc_productxml.html.php`). Es gibt **keine
Objektrelation** Contributor→Adresse — nur einen String-Fremdschlüssel.

---

## 5. Feldinventar `Produkt` (~92 Felder)

Fachlich gruppiert — dies ist der Rohstoff für den anyPIM-Attributkatalog:

| Gruppe | Felder |
|--------|--------|
| **Identifikation** | `RECORDIDENTIFIER`, `RECORDREFERENCE`, `NOTIFICATIONTYPE`, `PUBLISHERORDERID`, `PRODUCTISBN13`, `PRODUCTEAN`, `BARCODE`, `ISSN`, `DOI`, `OBJ_NR`, `IDEEN_NR`, `L_AUSG_NR`, `GRUP_ID` |
| **Titel** | `DISTINCTIVETITLE`, `SUBTITLE`, `ORIGINALTITLE`, `ORIGINALSUBTITLE` |
| **Ausgabe** | `EDITIONNUMBER`, `EDITIONSTATEMENT`, `NOEDITION`, `STAND`, `DRUCKAUFLAGE`, `VERKAUFSAUFLAGE`, `KZ_BASIS_BAND` |
| **Sprache** | `LANGUAGEOFTEXT`, `ORIGINALLANGUAGE` |
| **Umfang** | `NUMBEROFPAGES`, `PAGESROMAN`, `PAGESARABIC`, `ILLUSTRATIONSNOTE`, `CASEITEN`, `EXTENTVALUE`, `EXTENTTYPE`, `EXTENTUNIT`, `SEITEN_VON`, `SEITEN_BIS` |
| **Form** | `PRODUCTFORM`, `PRODUCTFORMDETAIL`, `PRODUKTTYP`, `BEIWERK_1..4` |
| **Maße** | `PRODUCTHEIGHT`, `PRODUCTWIDTH`, `PRODUCTTHICKNESS`, `PRODUCTWEIGHT` |
| **Verlag** | `PUBLISHERCODE`, `PUBLISHERNAME`, `COUNTRYOFFINALMANUFACTURE` |
| **Status / Termine** | `PUBLISHINGSTATUS`, `PUBLISHINGSTATUSNOTE`, `MELDETEXT`, `PUBLICATIONDATEYEAR/MONTH/DAY`, `ONSALEDATE`, `EXPECTEDSHIPDATEYEAR/MONTH/DAY`, `PRODUCTAVAILABILITY`, `ERSCHEINUNG`, `VORAUSSICHT_ERSCH_DATUM`, `QUARTAL_MONAT`, `LASTCHANGE` |
| **Handel** | `PREISBIND`, `SPERRKZ`, `KDSTAFRAB`, `MWST`, `GESAMTBESTAND` |
| **Zeitschrift** | `HEFTNUMMER`, `JAHRGANG`, `JAHRZAEHL` |
| **Reihe** | `VLB_REIHE`, `KOH_REIHE` |
| **Klassifikation** | `PRODUCTCLASSIFICATIONCODE`, `PRODUCTCLASSIFICATIONTYPE`, `VLBSCHEMENEW`, `INDEX_VLB`, `TEXTTYPECODE` |
| **Vorschau / Sichtbarkeit** | `INVORSCHAU`, `RPV_EBSICHTBAR`, `PV_EBSICHTBAR` |
| **Sonstiges** | `NOCONTRIBUTOR`, `PRODUCTLINK` |
| **Abgeleitet (nicht aus `SF_PRODUCT`)** | `AUTOR`, `RUECKENTEXT` (Othertext), `INLIZENZ` (Subject), `LPPRICE`, `LPPRICECH`, `LPPRICEAT`, `CAPRICE`, `DISCOUNTPERCENT` (Preise) |
| **Deaktiviert im Code** | `REPLACESISBN`, `REPLACEDBYISBN`, `BENUTZER_ID_LEKTOR` (auskommentiert) |

`PRODUKTTYP` steuert zusätzlich die **Ordnerablage** (`/produkte/{typ}`) und ist
damit faktisch der Produkttyp-Diskriminator (z. B. `ZSEH`, `ZSOP`, `ZEBU` für
Zeitschriften).

---

## 6. Gap-Analyse PIMCORE ↔ anyPIM

| PIMCORE-Konstrukt | anyPIM-Äquivalent | Status |
|-------------------|-------------------|--------|
| `DataObject\Produkt` | `Product` + `ProductType` | ✅ vorhanden |
| ~92 Produktfelder | `Attribute` + `ProductAttributeValue` | ✅ vorhanden |
| Ordnerablage `/produkte/{PRODUKTTYP}` | `ProductType` bzw. Master-Hierarchie | ✅ vorhanden |
| Fieldcollection `Preis` | `PriceType` + `ProductPrice` | ✅ vorhanden — Zusatzspalten über **Preis-Metadaten** (neu, siehe 7.2) |
| Fieldcollection `Bearbeiter` | vermehrbare Attributgruppe | ✅ vorhanden |
| Classificationstore `Othertext` | `Attribute` (Datentyp String/Text) je `TEXTTYPECODE` | ✅ vorhanden |
| Classificationstore `Subject` | `Attribute` + `ValueList`/`ValueListEntry` | ✅ vorhanden |
| Classificationstore `Dienstleister` | Beziehung Titel → `adresse` mit Beziehungsattribut `dl-typ` | ✅ vorhanden |
| Dynamisches Anlegen von Gruppen/Keys zur Laufzeit | kein Gegenstück — anyPIM-Attribute sind kuratiert | ⚠️ bewusst anders (Vorteil: Datenqualität) |
| `Kategorie`-Baum (`REFCODE`) | `Hierarchy` (`output`) + `HierarchyNode` | ✅ vorhanden |
| `CATEGORIES` (Mehrfachzuordnung) | `OutputHierarchyProductAssignment` | ✅ vorhanden |
| `LICENCECATEGORIES` (zweiter Baum) | zweite Output-Hierarchie | ✅ vorhanden |
| `Vorschau`-Bäume (Vorschau/Buchinfo/Lizenz) | weitere Output-Hierarchien | ✅ vorhanden |
| Relationen `SERIES`/`SETS`/`RELATIONS`/`BUNDLE` | `ProductRelationType` + `ProductRelation` | ✅ vorhanden |
| `ObjectMetadata` an Relationen | `ProductRelationAttributeValue` | ✅ **vorhanden** — Schlüsselbaustein |
| Rückrelationen `REVERSESERIES`/`REVERSESETS` | `is_bidirectional` am Beziehungstyp | ✅ vorhanden (redundante Gegenspeicherung entfällt) |
| **`DataObject\Contributor` (Autoren)** | **Produkttyp `contributor`** + Beziehung mit Rolle | ✅ **entschieden — siehe 7.4** |
| `DataObject\Adresse` | **Produkttyp `adresse`** | ✅ entschieden — siehe 7.0 |
| `DataObject\Email` | Attribute `email` / `tel_art` am Produkttyp `adresse` | ✅ entschieden — siehe 7.0 |
| `DataObject\Keyword` | `ValueList` + `ValueListEntry` | ✅ vorhanden — kein Produkttyp, siehe 7.0 |
| `Zeitschrift`/`Jahrgang`/`Heft` | Output-Hierarchie mit Knotenattributen und Knotenmedien | ✅ entschieden — siehe 7.7 |
| Assets `BILD_K`/`BILD_G` | `Media` + `ProductMediaAssignment` + `MediaUsageType` | ✅ vorhanden |
| Delta-Import über `LASTCHANGE` | Cover-Connector: `LASTCHANGE` + `ConnectorProductChecksum` | ✅ entschieden — eigenes Konzept |

**Kernbefund:** anyPIM deckt den Produkt-, Preis-, Hierarchie-, Medien- und
Beziehungsteil vollständig ab — inklusive des kritischen Konstrukts
*Beziehungsattribute* (`ProductRelationAttributeValue`), das die Pimcore-
`ObjectMetadata` 1:1 ersetzen kann.

**Die einzige echte Lücke waren die Nicht-Produkt-Stammobjekte** (`Contributor`,
`Adresse`, `Email`). Sie wird über eigene Produkttypen geschlossen (7.0/7.4) — ohne
Schemaänderung am Kernmodell. Offen bleibt einzig die kleine Ergänzung
`product_types.is_master_data`, damit Stammdaten-Produkte nicht im Produktkatalog
auftauchen.

Zwei Punkte brauchen echte Neuentwicklung:
- **Preis-Metadaten** — für die COVER-Rabatt- und Steuerspalten
  ([`plan-preis-metadaten.md`](plan-preis-metadaten.md))
- **Cover-Connector** — Lesezugriff auf eine beliebige MySQL-Datenbank
  ([`kohlhammer-cover-connector.md`](kohlhammer-cover-connector.md))

---

## 7. Mappingvorschlag

### 7.0 Welche PIMCORE-Klassen werden Produkttypen — und welche nicht

Die Frage, ob **alle** Pimcore-Klassen als eigene Produkttypen abgebildet werden,
lässt sich nicht pauschal mit Ja beantworten: Ein Teil der Klassen bildet Bäume ab,
und für Bäume hat anyPIM mit Hierarchien das deutlich stärkere Konstrukt.

| PIMCORE-Klasse | Abbildung in anyPIM | Begründung |
|---|---|---|
| `Produkt` (7) | **Produkttypen** — je `PRODUKTTYP` einer (`buch`, `zseh`, `zsop`, `zebu`, …) | echte Produkte |
| `Contributor` (10) | **Produkttyp `contributor`** | braucht Beziehungen mit Rolle + eigene Attribute |
| `Adresse` (12) | **Produkttyp `adresse`** | eigenständig nötig, weil `SF_PRODUCTDIENSTLEISTER` direkt auf `ADR_NR` verweist — nicht auf den Contributor |
| `Email` (13) | **kein eigener Typ** → Attribute `email`, `tel_art` am Produkttyp `adresse` | Schlüssel ist `ADR_NR-PERSON_NR`, also identisch zur Adresse. Ein eigenes Objekt brächte nur eine Beziehung ohne Mehrwert (Mengengerüst prüfen, siehe Connector-Konzept, Abschnitt 6 Punkt 3) |
| `Keyword` (11) | **kein Produkttyp** → `ValueList` `keywords` + vermehrbares Selection-Attribut | reines Vokabular ohne eigene Daten. `ValueListEntry` kann bereits mehrsprachig und über `parent_entry_id` hierarchisch (Thesaurus) |
| `Kategorie` (34) | **Output-Hierarchien** `kohlhammer-wk`, `kohlhammer-lizenz` | Baumstruktur, Mehrfachzuordnung, Knotenattribute und Vererbung sind Kernfunktionen der Hierarchie — als Produkttyp ginge das alles verloren |
| `Vorschau` | **Output-Hierarchien** (Vorschau, Buchinfo, Lizenz) | dito |
| `Zeitschrift` / `-jahrgang` / `-jahrgangheft` | **eine Output-Hierarchie `zeitschriften` mit drei Ebenen** | siehe 7.7 |

**Faustregel:** Alles, was eigene Felder trägt und in Beziehungen steht, wird
Produkttyp. Alles, was primär eine Baumstruktur mit Produktzuordnung ist, wird
Hierarchie. Reines Vokabular wird Werteliste.

Ergänzend als Produkttyp denkbar, sobald Bedarf besteht: `dienstleister` — heute
reicht eine Beziehung Titelprodukt → `adresse` mit dem Beziehungsattribut `dl-typ`
(aus `DL_TYP_BEZ`).

### 7.1 Produkt

| COVER / PIMCORE | anyPIM |
|-----------------|--------|
| `RECORDIDENTIFIER` | `products.sku` (Leitschlüssel für Delta & Upsert) |
| `PRODUCTEAN` / `PRODUCTISBN13` | `products.ean` bzw. eigenes Attribut `product-isbn13` |
| `DISTINCTIVETITLE` | `products.name` + Attribut `product-title` |
| `PRODUKTTYP` | `ProductType.technical_name` (Codes aus COVER übernehmen) |
| `PUBLISHINGSTATUS` | `products.status` (Mapping-Tabelle) + Originalcode als Attribut |
| alle übrigen ~85 Felder | je ein `Attribute`, Gruppierung nach Abschnitt 5 über `AttributeType` |

Datentypen bewusst nachziehen: `PRODUCTWEIGHT`/`-HEIGHT`/`-WIDTH`/`-THICKNESS` als
`Number` mit `UnitGroup`, `PUBLICATIONDATE*`/`ONSALEDATE` als `Date` (aus
Y/M/D-Tripeln zusammensetzen), `PREISBIND`/`SPERRKZ` als `Flag`, `PRODUCTFORM`/
`LANGUAGEOFTEXT`/`PUBLISHINGSTATUS` als `Selection` mit Werteliste (ONIX-Codes).

### 7.2 Preise

`SF_PRODUCTPRICE` → `ProductPrice`:

| Quelle | Ziel |
|--------|------|
| `PRICETYPECODE` + `PRICESTATUS` | `PriceType` (kombinierter technischer Name, z. B. `lp_de_02`) |
| `PRICEAMOUNT` | `amount` |
| `CURRENCYCODE` | `currency` |
| `COUNTRYCODE` | `country` |
| `PRICEEFFECTIVEFROM/UNTIL` | `valid_from` / `valid_to` |
| `MINIMUMORDERQUANTITY` | `scale_from` |
| `DISCOUNTPERCENT`, `DISCOUNTGROUP`, `BATCHQUANTITY`, `TAXRATE*1/2` | **Preis-Metadaten** (neues Feature, siehe [`plan-preis-metadaten.md`](plan-preis-metadaten.md)) |

**Wichtig — Preisart muss Land und Status enthalten:** `product_prices` trägt den
Unique-Index `(product_id, price_type_id, currency, valid_from, scale_from)`. Weder
`country`/`price_region_id` noch ein Status sind darin enthalten. Würde man
`LP-Deutschland` und `LP-Österreich` auf eine gemeinsame Preisart `LP` + Land
abbilden, kollidierten beide (gleiche Währung, gleiches Gültigkeitsdatum). Die
COVER-Preisarten werden deshalb 1:1 als `price_types` übernommen
(`lp_de_02`, `lp_at_02`, `lp_ch_02`, `ca_de_01`, …).

Die abgeleiteten Kopffelder (`LPPRICE`, `CAPRICE`, …) entfallen — anyPIM kann den
Listenpreis direkt über `PriceType` selektieren.

### 7.3 Beziehungen

Ein `ProductRelationType` je Pimcore-Relationsfeld, Kantenfelder als Attribute mit
`allows_free_attributes` bzw. definierten Beziehungsattributen:

| PIMCORE | `ProductRelationType` | Beziehungsattribute |
|---------|----------------------|---------------------|
| `SERIES` / `REVERSESERIES` | `series` (bidirektional) | `series-number` (`NUMBERWITHINSERIES`), `series-title` |
| `SETS` / `REVERSESETS` | `set` (bidirektional) | `set-number` |
| `RELATIONS` | `related-product` | `relation-code`, `product-form` |
| `BUNDLE` | `bundle-item` | `ausleitung`, `anteil` |
| `CONTRIBUTORS` | `contributor` | `contributor-role`, `contributor-sort` |
| `SF_PRODUCTDIENSTLEISTER` | `dienstleister` (Ziel: Produkttyp `adresse`) | `dl-typ` (`DL_TYP_BEZ`) |

Die Metaspalte `adressid` (`ADR_NR-PERSON_NR`) entfällt: In PIMCORE ist sie ein
String-Fremdschlüssel, weil es keine Relation Contributor→Adresse gibt. In anyPIM
wird daraus eine echte Beziehung `contributor` → `adresse` (Typ `contributor-address`).

`title`/`isbn` in den Pimcore-Metadaten sind **Denormalisierungen des Zielobjekts**
und werden nicht migriert — anyPIM liest sie über die Beziehung.

### 7.4 Autoren / Mitwirkende — entschieden: eigener Produkttyp

**Entscheidung:** Contributor werden als **Produkte eines eigenen Produkttyps**
geführt (ursprüngliche Variante A). Damit stehen Attribute, Medien, Suche, Vererbung,
Export und vor allem **Beziehungen mit Beziehungsattributen** ohne Schemaänderung zur
Verfügung.

| | Festlegung |
|---|---|
| Produkttyp | `contributor` (`has_prices=false`, `has_variants=false`, `has_ean=false`, `has_media=true`) |
| `products.sku` | `CTR-{ADR_NR}[-{PERSON_NR}]`, ersatzweise normalisierter Name, wenn keine Adresse hinterlegt ist |
| `products.name` | Anzeigename (`KEYNAMES`, `NAMESBEFOREKEY`) |
| Verknüpfung | `ProductRelationType` `contributor`, Quelle = Titelprodukt, Ziel = Contributor |
| Beziehungsattribute | `contributor-role` (Selection, Werteliste ONIX-Liste 17), `contributor-sort` (Number) |

Die slash-separierten Mehrfachrollen aus PIMCORE (`role="A01/B01"`) werden in **je
eine Beziehung pro Rolle** aufgelöst.

`allowed_source_product_type_ids` / `allowed_target_product_type_ids` am
Beziehungstyp verhindern, dass Contributor versehentlich als Zubehör oder Set-Teil
verknüpft werden.

#### Zwei Punkte, die dabei zu lösen sind

**1. SKU-Kollisionen.** `products.sku` ist systemweit eindeutig. `RECORDIDENTIFIER`
(Titel) und `ADR_NR` (Adresse) stammen aus verschiedenen Nummernkreisen und können
sich theoretisch überschneiden. Deshalb **Präfixe** je Stammdatentyp: `CTR-`, `ADR-`.

**2. Stammdaten-Produkte gehören nicht in den Produktkatalog.** `product_types` hat
heute keine Kennzeichnung „kein verkaufbares Produkt". Ohne sie tauchen Contributor
und Adressen in Produktlisten, Suchindex, Dashboards und Exporten auf. Vorschlag:
eine schmale Migration mit `product_types.is_master_data BOOLEAN DEFAULT false` und
ein Standardfilter in Produktliste, Suche und Export. Das ist deutlich billiger als
eine eigene Entität und hält die Trennung trotzdem sauber.

### 7.5 Kategorien & Bäume

| PIMCORE | anyPIM |
|---------|--------|
| `Kategorie`-Baum `wk` (3 Ebenen, `REFCODE` 9-stellig) | Output-Hierarchie `kohlhammer-wk` |
| `Kategorie`-Baum `lizenz` (Präfix `EN-`, englische Bezeichnungen) | Output-Hierarchie `kohlhammer-lizenz` |
| `Vorschau`-Bäume (Vorschau / Buchinfo / Lizenz) | je eine weitere Output-Hierarchie |
| `CATEGORIES` / `LICENCECATEGORIES` | `OutputHierarchyProductAssignment` |
| Produktordner `/produkte/{PRODUKTTYP}` | Master-Hierarchie nach Produkttyp |

Die Ebenenlogik aus `import_sf_categories_tree.php` (`KAT_2_ID` 9-stellig, Ebenen
über `%000000` / `%000` / Rest, Parent per Präfix) lässt sich direkt auf
`HierarchyNode.path` / `depth` abbilden. Die Sonderregel
`if ($cat_1_id == '020000000') $cat_1_id = '010000000'` ist eine fachliche
Ausnahme und muss übernommen oder mit Kohlhammer geklärt werden.

### 7.6 Texte, Klassifikationen, Schlagworte

| PIMCORE | anyPIM |
|---------|--------|
| Classificationstore `Othertext` (`TEXTTYPECODE`) | je Textart ein `Attribute` (`String`, `is_translatable=true`), Gruppe `texte` |
| Classificationstore `Subject` (Gruppe 23) | je `SUBJECTSCHEMENAME` ein `Attribute` (`Selection`) + `ValueList` (Code → Klartext) |
| Classificationstore `Dienstleister` | Beziehung `dienstleister` auf den Produkttyp `adresse`, Typ über das Beziehungsattribut `dl-typ` |
| `Keyword` + Relation `KEYWORDS` | `ValueList` `keywords` + vermehrbares `Selection`-Attribut |
| Fieldcollection `Bearbeiter` | vermehrbare Attributgruppe (`rolle`, `benutzer_id`, `name`, `kz_haupt_lektor`) — `BENUTZER_ID` verweist auf interne Bearbeiter, nicht auf `SF_ADRESSEN` |

Statt der Pimcore-Praxis „Key bei Bedarf zur Laufzeit anlegen" wird der
Attributkatalog **einmalig aus dem Bestand generiert** (`SELECT DISTINCT
TEXTTYPECODE`, `SUBJECTSCHEMENAME`, `DL_TYP_BEZ`) und danach kuratiert gepflegt.
Unbekannte Codes im laufenden Import gehören dann in ein Fehlerprotokoll
(`ImportJobError`), nicht in ein automatisch angelegtes Attribut.

### 7.7 Zeitschriften — entschieden: Hierarchie

Zeitschrift → Jahrgang → Heft ist in Pimcore eine dreistufige Objekthierarchie mit
Rückverweis auf die Produkte (`COVER_ZSEH`, `COVER_ZSOP`) und Cover-Assets.

Abbildung als **eine Output-Hierarchie `zeitschriften` mit drei Ebenen**. Der Grund,
dass dafür kein Produkttyp nötig ist: anyPIM-Hierarchieknoten können selbst Daten
tragen —

- `HierarchyNodeAttributeValue` → `ISSN`, `ISSN_JG`, `ISSN_JG_HEFT`, `JAHRGANG`,
  `HEFTNUMMER`, `TITEL`
- `HierarchyNodeMediaAssignment` → Cover-Bild des Hefts
- `OutputHierarchyProductAssignment` → die ZSEH-/ZSOP-Produkte am Heftknoten

Damit bleibt die Baumsemantik erhalten (Navigation, Vererbung, Mehrfachzuordnung),
ohne dass Hefte als Scheinprodukte im Katalog liegen.

**Wechselkriterium:** Sobald Hefte eigene Preise, Varianten oder einen eigenen
Bestellvorgang brauchen, wird `zeitschriftenheft` doch ein Produkttyp. Solange sie
nur strukturieren und ein Cover tragen, ist die Hierarchie richtig.

### 7.8 Medien

`import_sf_productimages.php` lädt Cover aus dem Dateisystem
(`media/BILD_K/{ISBN}_K.jpg`, `_G`) in Pimcore-Assets und hängt sie an `BILD_K` /
`BILD_G`. In anyPIM: `Media` + `ProductMediaAssignment` mit `MediaUsageType`
`cover_klein` / `cover_gross`. Das Skript ist Pimcore-4-Altcode (`Asset_Unknown`,
`Pimcore_File`) und muss ohnehin neu geschrieben werden.

---

## 8. Offene Punkte / Klärungsbedarf

Erledigt: Übernahmeweg (Cover-Connector), Contributor-Modell (Produkttyp),
Preiszusatzfelder (Preis-Metadaten).

1. **Einmalmigration oder Dauerbetrieb?** Läuft PIMCORE parallel weiter oder wird es
   abgelöst? Bei Dauerbetrieb braucht der Connector einen Zeitplan (`ScheduledAction`).
2. **Löschabgleich:** In COVER entfernte Sätze werden heute nicht bereinigt. Der
   Connector sieht `--reconcile` mit `status = 'discontinued'` vor — bitte fachlich
   bestätigen.
3. **`is_master_data`-Kennzeichen:** Kleine Migration an `product_types`, damit
   Contributor und Adressen nicht im Produktkatalog, Suchindex und den Exporten
   auftauchen (siehe 7.4). Freigabe nötig, weil es das Kernschema berührt.
4. **Wertelisten:** Sollen die ONIX-Codelisten (Produktform, Sprache, Publishing
   Status, Contributor-Rolle) als gepflegte Wertelisten mit Klartext angelegt werden?
5. **Sprachen:** COVER liefert im Kern deutsch; die Lizenzkategorien tragen englische
   Bezeichnungen (`KAT_2_BEZ_ENGL`). Welche Sprachen führt anyPIM produktiv?
6. **Mengengerüst:** Anzahl Produkte, Contributor, Adressen, Preise, Texte — bestimmt
   Chunking und Laufzeitbudget des Connectors.
7. **E-Mail-Kardinalität:** Gibt es je `ADR_NR`/`PERSON_NR` mehr als eine Zeile in
   `SF_EMAILADRESSEN` (`TEL_ART`)? Entscheidet über einfaches vs. vermehrbares
   Attribut. Der heutige Pimcore-Import vergleicht dort nur über `ADR_NR` und ist
   deshalb kein verlässlicher Beleg.
8. **Zeichensatz der Quelle:** vermutlich `latin1` (die Skripte nutzen
   `utf8_encode`/`utf8_decode`) — vor dem ersten Lauf verifizieren.
9. **Vorschau-/Buchinfo-Bäume:** Werden diese fachlich noch gebraucht (nicht Teil von
   `go.sh`) oder sind sie Altlast?
10. **Mediando-Skripte:** aktiv oder abgekündigt?
11. **Netzweg und Lesebenutzer:** Direktverbindung, SSH-Tunnel oder VPN; read-only
    MySQL-Benutzer muss von Cover/Kohlhammer bereitgestellt werden.

---

## 9. Vorschlag für die nächsten Schritte

| Schritt | Inhalt |
|---------|--------|
| **2** | Cover-Verbindung herstellen und `CoverProfiler` laufen lassen: `SELECT DISTINCT` auf `PRODUKTTYP`, `TEXTTYPECODE`, `SUBJECTSCHEMENAME`/`-IDENTIFIER`, `DL_TYP_BEZ`, `CONTRIBUTORROLE`, `PRICETYPECODE`, `RELATIONCODE` + Mengengerüst + Zeichensatzprüfung |
| **3** | Schema-Seeder auf leerer Instanz fahren (Abschnitt 10) und Manifest um die Profiling-Ergebnisse ergänzen |
| **4** | Migration `product_types.is_master_data`; Attribut-zu-Knoten-Zuordnungen nach dem Hierarchie-Import |
| **5** | Feature **Preis-Metadaten** umsetzen ([`plan-preis-metadaten.md`](plan-preis-metadaten.md)) |
| **6** | **Cover-Connector** bauen ([`kohlhammer-cover-connector.md`](kohlhammer-cover-connector.md)) — Verbindung, Reader, Phasen, Delta, Protokollierung |
| **7** | Testmigration einer Produkttyp-Scheibe (z. B. nur Bücher) mit `--dry-run`, Abgleich PIMCORE ↔ anyPIM |
| **8** | Vollmigration + Verprobung der Exporte (BMEcat/publixx) gegen die heutigen PIMCORE-Exporte |

---

## 10. Schema-Seeder für eine leere anyPIM-Instanz

Die Konfiguration lässt sich zum größten Teil **aus den PIMCORE-Importskripten
ableiten** — die Feldlisten stehen dort als statische `setPartData()`-Blöcke und
`ObjectMetadata`-Definitionen im Code. Daraus ist ein Manifest entstanden, das ein
idempotenter Seeder auf eine leere Instanz anwendet.

### 10.1 Kein SQL, sondern Manifest + Seeder

Ausdrücklich **keine SQL-Dumps**: Die anyPIM-Tabellen selbst sind kundenneutral und
kommen aus den Migrationen — angelegt wird nur die kundenspezifische *Konfiguration*.
Dafür ist ein Seeder das richtige Werkzeug, denn er löst UUID-Fremdschlüssel über
technische Namen auf, ist mehrfach ausführbar und durchläuft die Model-Events
(Suchindex, Audit). Ein SQL-Skript müsste UUIDs hart verdrahten und wäre nach der
ersten Schemaänderung unbrauchbar.

```
database/seeders/
├── KohlhammerSchemaSeeder.php
└── data/kohlhammer/
    ├── attributes.php     121 Attribute
    └── structure.php      Gruppen, Produkttypen, Beziehungstypen, Preisarten,
                           Preis-Metadaten, Wertelisten, Hierarchien, Kernfelder
```

Als lesbare Übersicht liegt dasselbe Modell zusätzlich als Arbeitsmappe bei:
[`kohlhammer-datenmodell.xlsx`](kohlhammer-datenmodell.xlsx) — ein Tab je Produkttyp,
dazu Beziehungen, Preise, Hierarchien, Wertelisten und die nicht übernommenen Felder.
Die Mappe wird aus dem Manifest generiert und nicht von Hand gepflegt.

```bash
php artisan migrate
php artisan db:seed --class=KohlhammerSchemaSeeder
```

Der Seeder ist **nicht** in `DatabaseSeeder` eingehängt — er ist kundenspezifisch und
wird gezielt aufgerufen.

### 10.2 Was der Lauf anlegt

| Objekt | Anzahl | Herkunft |
|---|---|---|
| Attributgruppen | 20 | fachliche Gliederung aus Abschnitt 5 |
| Attribute | 121 | 82 Produktfelder + 2 abgeleitete + 21 Adresse/E-Mail + 3 Contributor + 4 Bearbeiter + 9 Beziehungsattribute |
| Produkttypen | 6 | `titel`, `zeitschrift-heft/-online/-ebook` (ZSEH/ZSOP/ZEBU), `contributor`, `adresse` |
| Beziehungstypen | 7 | ein Typ je PIMCORE-Relationsfeld, inkl. erlaubter Quell-/Zielprodukttypen |
| Preisarten | 4 | die in `import_sf_price.php` belegten Kombinationen |
| Preis-Metadaten | 11 | `SF_PRODUCTPRICE`-Restspalten (übersprungen, solange das Feature fehlt) |
| Wertelisten | 21 | Container; nur `onix-contributor-role` ist bereits belegt |
| Hierarchien | 6 | Wurzeln für Master, WK, Lizenz, Vorschau, Buchinfo, Zeitschriften |
| Einheiten | 2 Gruppen | `length` (mm/cm), `weight` (g/kg) für die Maßattribute |

82 statt 85 Produktattribute, weil `RECORDIDENTIFIER`, `PRODUCTEAN` und `PRODUKTTYP`
auf feste Produktspalten gehen (`sku`, `ean`, `product_type_id`) statt auf Attribute.
`DISTINCTIVETITLE` und `PUBLISHINGSTATUS` sind beides — Kernspalte *und* Attribut.

Jedes Attribut trägt `source_system = 'COVER'` und in `source_attribute_name` die
COVER-Spalte. Damit ist das Manifest zugleich die **Feldkarte des Cover-Connectors** —
Schema und Import lesen dieselbe Quelle.

Verifiziert auf leerer SQLite-Instanz: erster Lauf legt 121 Attribute an, zweiter
Lauf 0 — der Seeder ist idempotent und kann nach jeder Manifest-Erweiterung erneut
laufen.

### 10.3 Was sich *nicht* aus dem Code ableiten lässt

Das ist die ehrliche Grenze des Verfahrens. Aus PIMCORE kommt die **Struktur**, nicht
die **Wertebereiche** — dort ist praktisch alles ein Textfeld:

| Nicht ableitbar | Konsequenz |
|---|---|
| Datentypen (String/Number/Date/Flag/Selection) | im Manifest **kuratiert** gesetzt, am Bestand zu verifizieren |
| Wertelisteninhalte (Produktform, Sprache, Status, …) | Container leer angelegt, Einträge kommen aus dem Profiling |
| Die tatsächlichen `PRODUKTTYP`-Codes | nur ZSEH/ZSOP/ZEBU sind im Code belegt, Rest per `SELECT DISTINCT` |
| `TEXTTYPECODE`, `SUBJECTSCHEMENAME`, `DL_TYP_BEZ` | in PIMCORE datengetrieben zur Laufzeit angelegt — Attribute entstehen erst nach dem Profiling |
| Weitere `PRICETYPECODE`/`PRICESTATUS`-Kombinationen | nur vier sind im Code belegt |
| Einheiten der Maßfelder | als mm/g angenommen (ONIX-üblich), am Bestand zu prüfen |
| Wertebereich der Kennzeichen-Felder | als `Flag` gesetzt; ob `J`/`N`, `0`/`1` oder anderes, zeigt das Profiling |
| Kategoriebäume | Struktur steht im Code, Knoten kommen aus `SF_WK_KATEGORIEN` |

Der Ablauf ist deshalb zweistufig: **Seeder jetzt** (Struktur), **Profiling danach**
(Wertebereiche), dann Manifest ergänzen und Seeder erneut laufen lassen.

### 10.4 Bekannte Einschränkungen

- **`product_types.is_master_data`** existiert noch nicht. Der Seeder prüft die Spalte
  und warnt, statt zu scheitern — bis zur Migration erscheinen `contributor` und
  `adresse` im normalen Produktkatalog (siehe 7.4).
- **Preis-Metadaten** werden übersprungen, solange
  `price_metadata_definitions` fehlt. Der Rest läuft durch, damit eine leere Instanz
  auch ohne das Feature aufsetzbar ist.
- **Attribut-zu-Knoten-Zuordnungen** (`HierarchyNodeAttributeAssignment`) legt der
  Seeder nicht an — sie brauchen die Hierarchieknoten, die erst der Import erzeugt.
  Als Zwischenlösung tragen die Produkttypen ihre `default_attribute_groups`.
