# Kohlhammer / COVER → anyPIM — Migrationsanalyse

> **Stand:** Schritt 1 — Analyse der bestehenden PIMCORE-Importstrecke
> **Quellrepo:** `incoxx/kohlhammer-pimcore`, Verzeichnis `src/AppBundle/Backend/Import/`
> **Zielsystem:** anyPIM (`incoxx/publixx-pim`)

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
| Fieldcollection `Preis` | `PriceType` + `ProductPrice` | ✅ vorhanden (Staffel über `scale_from/to`) |
| Fieldcollection `Bearbeiter` | vermehrbares Attribut oder Beziehung | ⚠️ Modellentscheidung nötig |
| Classificationstore `Othertext` | `Attribute` (Datentyp String/Text) je `TEXTTYPECODE` | ✅ vorhanden |
| Classificationstore `Subject` | `Attribute` + `ValueList`/`ValueListEntry` | ✅ vorhanden |
| Classificationstore `Dienstleister` | Beziehung oder Attribut je `DL_TYP_BEZ` | ⚠️ Modellentscheidung nötig |
| Dynamisches Anlegen von Gruppen/Keys zur Laufzeit | kein Gegenstück — anyPIM-Attribute sind kuratiert | ⚠️ bewusst anders (Vorteil: Datenqualität) |
| `Kategorie`-Baum (`REFCODE`) | `Hierarchy` (`output`) + `HierarchyNode` | ✅ vorhanden |
| `CATEGORIES` (Mehrfachzuordnung) | `OutputHierarchyProductAssignment` | ✅ vorhanden |
| `LICENCECATEGORIES` (zweiter Baum) | zweite Output-Hierarchie | ✅ vorhanden |
| `Vorschau`-Bäume (Vorschau/Buchinfo/Lizenz) | weitere Output-Hierarchien | ✅ vorhanden |
| Relationen `SERIES`/`SETS`/`RELATIONS`/`BUNDLE` | `ProductRelationType` + `ProductRelation` | ✅ vorhanden |
| `ObjectMetadata` an Relationen | `ProductRelationAttributeValue` | ✅ **vorhanden** — Schlüsselbaustein |
| Rückrelationen `REVERSESERIES`/`REVERSESETS` | `is_bidirectional` am Beziehungstyp | ✅ vorhanden (redundante Gegenspeicherung entfällt) |
| **`DataObject\Contributor` (Autoren)** | **kein eigenes Objekt** | ❌ **Lücke — siehe 7.4** |
| `DataObject\Adresse` | `Manufacturer` / `Organization` decken es nur teilweise | ❌ Lücke |
| `DataObject\Email` | — | ❌ Lücke (an Adresse hängbar) |
| `DataObject\Keyword` | `ValueList` + `ValueListEntry` bzw. Dictionary | ✅ vorhanden |
| `Zeitschrift`/`Jahrgang`/`Heft` | Hierarchie **oder** Produkte + Beziehungen | ⚠️ Modellentscheidung nötig |
| Assets `BILD_K`/`BILD_G` | `Media` + `ProductMediaAssignment` + `MediaUsageType` | ✅ vorhanden |
| Delta-Import über `LASTCHANGE` | `ImportJob`, `ConnectorProductChecksum` | ✅ Bausteine vorhanden |

**Kernbefund:** anyPIM deckt den Produkt-, Preis-, Hierarchie-, Medien- und
Beziehungsteil vollständig ab — inklusive des kritischen Konstrukts
*Beziehungsattribute* (`ProductRelationAttributeValue`), das die Pimcore-
`ObjectMetadata` 1:1 ersetzen kann.

**Die eine echte Lücke sind die Nicht-Produkt-Stammobjekte:**
`Contributor` (Autoren), `Adresse`, `Email`.

---

## 7. Mappingvorschlag

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
| `MINIMUMORDERQUANTITY` / `BATCHQUANTITY` | `scale_from` / `scale_to` |
| `DISCOUNTPERCENT`, `DISCOUNTGROUP`, `TAXRATE*` | zusätzliche Preisfelder oder Produktattribute (Klärung nötig — anyPIM `product_prices` kennt diese Spalten heute nicht) |

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
| `CONTRIBUTORS` | `contributor` | `contributor-role`, `contributor-sort`, `contributor-address-ref` |

`title`/`isbn` in den Pimcore-Metadaten sind **Denormalisierungen des Zielobjekts**
und werden nicht migriert — anyPIM liest sie über die Beziehung.

### 7.4 Autoren / Mitwirkende — die zentrale Modellentscheidung

Pimcore kennt `Contributor` als eigene Objektklasse. anyPIM kennt heute nur
`Product`. Drei Wege:

**Variante A — Contributor als Produkt eigenen Typs** *(Empfehlung)*

- `ProductType` `contributor` (`has_prices=false`, `has_variants=false`, `has_media=true`)
- `products.sku` = `ADR_NR[-PERSON_NR]` (stabil aus COVER), `products.name` = Anzeigename
- Personendaten (`VORNAME`, `NACHNAME`, `TITEL_ETIKETT`, `ANREDE_ETIKETT`, `PLZ`, `ORT`,
  `STRASSE`, `LAND_CODE`, `EMAIL`) als Attribute dieses Typs → deckt `Adresse` und
  `Email` gleich mit ab
- Verknüpfung über `ProductRelationType` `contributor` mit den Beziehungsattributen
  `contributor-role` (Werteliste ONIX-17), `contributor-sort`
- `allowed_source_product_type_ids` / `allowed_target_product_type_ids` verhindern,
  dass Contributor-Objekte im normalen Produktkatalog auftauchen
- **Pro:** keine Schema-Änderung, alle vorhandenen Mechanismen (Attribute, Vererbung,
  Medien, Suche, Export, Beziehungsattribute) greifen sofort
- **Contra:** „Produkt" ist semantisch unscharf; UI-Filterung über Produkttyp nötig

**Variante B — eigene Entität `Contributor` (+ `ContributorRole`)**

- Neue Tabellen `contributors`, `product_contributors` (mit `role`, `sort_order`)
- **Pro:** fachlich sauber, klare API
- **Contra:** eigenes CRUD, eigene UI, eigene Export-/Import-/Suchanbindung —
  deutlich größerer Aufwand, und der Attributmechanismus fehlt

**Variante C — generische Stammdatenobjekte („Entity"/„Objekt")**

- Ein generisches Objektmodell analog Pimcore-DataObjects, produkttypunabhängig
- **Pro:** deckt Contributor, Adresse, Email, Dienstleister und künftige Fälle ab
- **Contra:** größter Eingriff ins Kernmodell von anyPIM

> **Empfehlung:** Variante A für die Migration. Sie ist ohne Schema-Änderung
> umsetzbar, und ein späterer Wechsel auf B/C ist eine reine Umhängung der
> Beziehungen, weil die Rollen-/Sortierinformation bereits auf der Kante liegt.

Die slash-separierten Mehrfachrollen (`"A01/B01"`) werden dabei in **je eine
Beziehung pro Rolle** aufgelöst.

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
| Classificationstore `Dienstleister` | je `DL_TYP_BEZ` ein Attribut mit Adressreferenz, alternativ Beziehung auf Contributor-/Adressprodukt |
| `Keyword` + Relation `KEYWORDS` | `ValueList` `keywords` + vermehrbares `Selection`-Attribut |
| Fieldcollection `Bearbeiter` | vermehrbare Attributgruppe (`rolle`, `benutzer_id`, `name`, `kz_haupt_lektor`) oder Beziehung auf Personenobjekt |

Statt der Pimcore-Praxis „Key bei Bedarf zur Laufzeit anlegen" wird der
Attributkatalog **einmalig aus dem Bestand generiert** (`SELECT DISTINCT
TEXTTYPECODE`, `SUBJECTSCHEMENAME`, `DL_TYP_BEZ`) und danach kuratiert gepflegt.
Unbekannte Codes im laufenden Import gehören dann in ein Fehlerprotokoll
(`ImportJobError`), nicht in ein automatisch angelegtes Attribut.

### 7.7 Zeitschriften

Zeitschrift → Jahrgang → Heft ist in Pimcore eine dreistufige Objekthierarchie mit
Rückverweis auf die Produkte (`COVER_ZSEH`, `COVER_ZSOP`) und Cover-Assets.

Zwei Optionen:
- **Hierarchie:** Output-Hierarchie `zeitschriften` mit Ebenen ISSN / Jahrgang / Heft,
  Produkte per `OutputHierarchyProductAssignment` — schlanker, gut für Navigation
- **Produkte:** Heft als eigener Produkttyp mit Beziehungen zu den ZSEH/ZSOP-Produkten
  — nötig, falls Hefte eigene Attribute, Preise oder Medien brauchen

Vorschlag: Hierarchie, solange Hefte keine eigenen Preise führen.

### 7.8 Medien

`import_sf_productimages.php` lädt Cover aus dem Dateisystem
(`media/BILD_K/{ISBN}_K.jpg`, `_G`) in Pimcore-Assets und hängt sie an `BILD_K` /
`BILD_G`. In anyPIM: `Media` + `ProductMediaAssignment` mit `MediaUsageType`
`cover_klein` / `cover_gross`. Das Skript ist Pimcore-4-Altcode (`Asset_Unknown`,
`Pimcore_File`) und muss ohnehin neu geschrieben werden.

---

## 8. Offene Punkte / Klärungsbedarf

1. **Übernahmeweg:** Direktzugriff auf die COVER-Spiegeltabellen (wie heute) oder
   Zwischenformat (CSV/JSON/BMEcat)? Davon hängt ab, ob ein eigener
   *Cover-Connector* (`app/Services/Connectors/`) oder ein Import-Profil gebaut wird.
2. **Einmalmigration oder Dauerbetrieb?** Läuft Pimcore parallel weiter oder wird es
   abgelöst? Bei Dauerbetrieb braucht es das Delta-/Idempotenzverhalten von `go.sh`
   inkl. Zeitplan.
3. **Löschabgleich:** In COVER entfernte Sätze werden heute nicht bereinigt. Soll
   anyPIM das ändern (Soft-Delete/`discontinued`)?
4. **Contributor-Modell:** Variante A/B/C (Abschnitt 7.4) — Entscheidung nötig, bevor
   der Attributkatalog steht.
5. **Preisfelder:** `DISCOUNTPERCENT`, `DISCOUNTGROUP`, `TAXRATE*1/2` haben in
   `product_prices` heute kein Ziel — Schema erweitern oder als Produktattribute führen?
6. **Wertelisten:** Sollen die ONIX-Codelisten (Produktform, Sprache, Publishing
   Status, Contributor-Rolle) als gepflegte Wertelisten mit Klartext angelegt werden?
7. **Sprachen:** COVER liefert im Kern deutsch; die Lizenzkategorien tragen englische
   Bezeichnungen (`KAT_2_BEZ_ENGL`). Welche Sprachen führt anyPIM produktiv?
8. **Mengengerüst:** Anzahl Produkte, Contributor, Adressen, Preise, Texte — bestimmt
   Chunking und Laufzeitbudget des Importers.
9. **Vorschau-/Buchinfo-Bäume:** Werden diese fachlich noch gebraucht (nicht Teil von
   `go.sh`) oder sind sie Altlast?
10. **Mediando-Skripte:** aktiv oder abgekündigt?

---

## 9. Vorschlag für die nächsten Schritte

| Schritt | Inhalt |
|---------|--------|
| **2** | Ist-Datenprofil auf den `SF_`-Tabellen: `SELECT DISTINCT` auf `PRODUKTTYP`, `TEXTTYPECODE`, `SUBJECTSCHEMENAME`/`-IDENTIFIER`, `DL_TYP_BEZ`, `CONTRIBUTORROLE`, `PRICETYPECODE`, `RELATIONCODE` + Mengengerüst |
| **3** | Attributkatalog ableiten: Excel-Importvorlage (Sheets 01–07) für Produkttypen, Attributgruppen, Einheiten, Wertelisten, Attribute, Hierarchien |
| **4** | Entscheidung Contributor-Modell (7.4) und Anlage der Beziehungstypen inkl. Beziehungsattribute |
| **5** | Cover-Importer bauen — als Connector oder Artisan-Command, mit Delta über `LASTCHANGE`, Chunking, `ImportJob`/`ImportJobError`-Protokollierung |
| **6** | Testmigration einer Produkttyp-Scheibe (z. B. nur Bücher), Abgleich Pimcore ↔ anyPIM |
| **7** | Vollmigration + Verprobung der Exporte (BMEcat/publixx) gegen die heutigen Pimcore-Exporte |
