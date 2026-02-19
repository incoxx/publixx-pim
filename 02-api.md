# Publixx PIM — RESTful API

> **Zweck:** Vollständige API-Referenz. Verwende diesen Skill beim Erstellen von Controllern, Routen, Form Requests, API Resources und Tests.

---

## Konventionen

| Konvention | Wert | Beispiel |
|------------|------|---------|
| Base URL | `/api/v1` | `https://pim.example.com/api/v1` |
| Auth | Bearer Token (Laravel Sanctum) | `Authorization: Bearer {token}` |
| Content-Type | `application/json` | |
| Paginierung | Cursor + Page | `?page=2&per_page=50` |
| Sortierung | `?sort=field&order=asc|desc` | `?sort=name_de&order=asc` |
| Filter | `?filter[field]=value` | `?filter[status]=active` |
| Includes | `?include=rel1,rel2` | `?include=unitGroup,valueList` |
| Sparse Fields | `?fields[type]=f1,f2` | `?fields[products]=id,sku,name` |
| Suche | `?search=term` | `?search=Bohrmaschine` |
| Sprache | `Accept-Language` Header oder `?lang=de` | Multi: `?lang=de,en` |
| Fehler | RFC 7807 Problem Details | `{ "type": "...", "title": "...", "status": 422 }` |
| Rate Limit | 60 req/min (Standard), 600/min (Export) | |

---

## Endpunkte

### Auth

```
POST   /auth/login              Login → Token
POST   /auth/logout             Logout → Token invalidieren
GET    /auth/me                 Aktueller User + Rechte
POST   /auth/refresh            Token erneuern
```

### Attribute

```
GET    /attributes                         Alle (paginiert, filterbar)
POST   /attributes                         Anlegen
GET    /attributes/{id}                    Einzeln (?include=valueList,unitGroup,children)
PUT    /attributes/{id}                    Aktualisieren
DELETE /attributes/{id}                    Löschen
```

### Attributtypen (Gruppen)

```
GET    /attribute-types                    Alle
POST   /attribute-types                    Anlegen
PUT    /attribute-types/{id}               Aktualisieren
DELETE /attribute-types/{id}               Löschen
```

### Einheiten

```
GET    /unit-groups                        Alle (?include=units)
POST   /unit-groups                        Gruppe anlegen
GET    /unit-groups/{id}                   Einzeln mit Einheiten
PUT    /unit-groups/{id}                   Gruppe aktualisieren
DELETE /unit-groups/{id}                   Gruppe löschen
POST   /unit-groups/{id}/units             Einheit hinzufügen
PUT    /units/{id}                         Einheit aktualisieren
DELETE /units/{id}                         Einheit löschen
```

### Wertelisten

```
GET    /value-lists                        Alle (?include=entries)
POST   /value-lists                        Anlegen
POST   /value-lists/{id}/entries           Eintrag hinzufügen
PUT    /value-list-entries/{id}            Eintrag aktualisieren
DELETE /value-list-entries/{id}            Eintrag löschen
```

### Attributsichten

```
GET    /attribute-views                    Alle
POST   /attribute-views                    Anlegen
PUT    /attribute-views/{id}               Aktualisieren
DELETE /attribute-views/{id}               Löschen
POST   /attribute-views/{id}/attributes    Attribut zuordnen
DELETE /attribute-views/{id}/attributes/{attr_id}  Zuordnung entfernen
```

### Produkttypen

```
GET    /product-types                      Alle
POST   /product-types                      Anlegen
GET    /product-types/{id}                 Einzeln
PUT    /product-types/{id}                 Aktualisieren
DELETE /product-types/{id}                 Löschen
GET    /product-types/{id}/schema          Effektives Attributschema
```

### Hierarchien

```
GET    /hierarchies                        Alle (?filter[type]=master)
POST   /hierarchies                        Anlegen
PUT    /hierarchies/{id}                   Aktualisieren
DELETE /hierarchies/{id}                   Löschen
GET    /hierarchies/{id}/tree              Kompletter Baum als JSON (?depth=3)
GET    /hierarchies/{id}/nodes             Flache Knotenliste
POST   /hierarchies/{id}/nodes             Knoten anlegen
PUT    /hierarchy-nodes/{id}               Knoten aktualisieren
DELETE /hierarchy-nodes/{id}               Knoten löschen (mit Kindern)
PUT    /hierarchy-nodes/{id}/move          Knoten verschieben {parent_node_id, sort_order}
```

### Hierarchie-Attributzuordnung

```
GET    /hierarchy-nodes/{id}/attributes    Zugeordnete Attribute (?inherited=true)
POST   /hierarchy-nodes/{id}/attributes    Attribut zuordnen
PUT    /node-attribute-assignments/{id}    Ändern (Sort, Collection)
DELETE /node-attribute-assignments/{id}    Entfernen
PUT    /node-attribute-assignments/bulk-sort  Drag & Drop Reihenfolge
```

### Produkte

```
GET    /products                           Alle (paginiert, filterbar, suchbar)
POST   /products                           Anlegen {product_type_id, sku, name, ...}
GET    /products/{id}                      Einzeln (?include=attributeValues,variants,media,prices)
PUT    /products/{id}                      Aktualisieren
DELETE /products/{id}                      Löschen
```

### Varianten

```
GET    /products/{id}/variants             Varianten eines Produkts
POST   /products/{id}/variants             Variante anlegen
GET    /products/{id}/variant-rules        Vererbungsregeln
PUT    /products/{id}/variant-rules        Regeln setzen {rules: [{attribute_id, mode}]}
```

### Attributwerte

```
GET    /products/{id}/attribute-values     Alle Werte (?view=eshop_view&lang=de)
PUT    /products/{id}/attribute-values     Bulk speichern {values: [{attribute_id, value, ...}]}
```

### Medien

```
GET    /media                              Alle (?filter[media_type]=image)
POST   /media                              Upload (multipart/form-data)
GET    /media/{id}                         Einzeln
PUT    /media/{id}                         Metadaten aktualisieren
DELETE /media/{id}                         Löschen
GET    /media/file/{filename}              Datei direkt ausliefern (für assetBase)
```

### Produkt-Medien

```
GET    /products/{id}/media                Zugeordnete Medien
POST   /products/{id}/media                Medium zuordnen
DELETE /product-media/{id}                 Zuordnung entfernen
```

### Preise

```
GET    /products/{id}/prices               Preise eines Produkts
POST   /products/{id}/prices               Preis anlegen
PUT    /product-prices/{id}                Aktualisieren
DELETE /product-prices/{id}                Löschen
GET    /price-types                        Alle Preisarten (CRUD)
```

### Beziehungen

```
GET    /products/{id}/relations            Alle Beziehungen
POST   /products/{id}/relations            Anlegen
DELETE /product-relations/{id}             Löschen
GET    /relation-types                     Alle Beziehungstypen (CRUD)
```

### Benutzer & Rollen

```
GET    /users                              Alle
POST   /users                              Anlegen {name, email, password, role_id}
PUT    /users/{id}                         Aktualisieren
DELETE /users/{id}                         Löschen
GET    /roles                              Alle (?include=permissions)
POST   /roles                              Anlegen {name, permissions: [...]}
PUT    /roles/{id}                         Aktualisieren
DELETE /roles/{id}                         Löschen
PUT    /roles/{id}/permissions             Berechtigungen setzen
```

### Export

```
GET    /export/products                    Produkte als JSON (Filter via Query Params)
GET    /export/products/{id}               Einzelprodukt als Dataset
POST   /export/products/bulk               Bulk-Export nach Filter
GET    /export/products/{id}/publixx       Im Publixx-PXF-Dataset-Format
POST   /export/query                       Export mit PQL-Filter
```

### PQL

```
POST   /pql/query                          PQL-Query ausführen → JSON-Array
POST   /pql/query/count                    Nur Trefferanzahl
POST   /pql/query/validate                 Query validieren
POST   /pql/query/explain                  Query-Plan + Kosten
```

### Publixx Integration

```
GET    /publixx/datasets/{mapping_id}                Alle Produkte als Dataset-Array
GET    /publixx/datasets/{mapping_id}/{product_id}   Einzelnes Produkt-Dataset
POST   /publixx/datasets/{mapping_id}/pql            Datasets mit PQL-Query
POST   /publixx/webhook                              Webhook von Publixx
```

### PXF Templates

```
GET    /pxf-templates                      Alle
POST   /pxf-templates                      Anlegen / Upload
GET    /pxf-templates/{id}                 Einzeln (inkl. PXF-JSON)
PUT    /pxf-templates/{id}                 Aktualisieren
DELETE /pxf-templates/{id}                 Löschen
GET    /pxf-templates/{id}/preview/{product_id}  Live-Preview: Template + Produktdaten
POST   /pxf-templates/import               PXF-Datei importieren
```

### Import

```
POST   /imports                            Excel hochladen → Validierung starten
GET    /imports/{id}                        Status + Validierungsergebnis
GET    /imports/{id}/preview                Vorschau (Create/Update/Skip)
POST   /imports/{id}/execute                Import ausführen
GET    /imports/{id}/result                 Ergebnis-Report
GET    /imports/templates/{type}            Leeres Excel-Template herunterladen
DELETE /imports/{id}                        Abbrechen / Löschen
```

---

## Berechtigungsschema

Format: `{entität}.{aktion}` mit optionaler Einschränkung:

```
products.view              Produkte sehen
products.create            Produkte anlegen
products.edit              Produkte bearbeiten
products.edit:eshop_view   Nur E-Shop-Attribute bearbeiten
products.edit:{node-uuid}  Nur Produkte unter einem Knoten
products.delete            Produkte löschen
attributes.*               Alle Attribut-Operationen
export.*                   Alle Export-Operationen
users.*                    Benutzerverwaltung
```

Vordefinierte Rollen: Admin, Data Steward, Product Manager, Viewer, Export Manager
