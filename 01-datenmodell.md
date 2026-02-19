# Publixx PIM — Datenmodell

> **Zweck:** Vollständiges Entity-Relationship-Modell. Verwende diesen Skill beim Erstellen von Migrations, Models, Seedern, API-Resources oder bei Fragen zum Schema.

---

## Stack-Kontext

- **DB:** MySQL 8.0+ (InnoDB, JSON-Spalten, CTEs, FULLTEXT)
- **Backend:** PHP 8.3, Laravel 11
- **PKs:** UUID (CHAR(36)) überall
- **Timestamps:** created_at / updated_at auf jeder Entität

---

## Entitäten-Übersicht (35 Tabellen)

| Bereich | Entitäten |
|---------|-----------|
| Attributmodell (10) | Attribute, AttributeType, UnitGroup, Unit, ValueList, ValueListEntry, AttributeView, AttributeViewAssignment, ComparisonOperatorGroup, ComparisonOperator |
| Produktmodell (6) | Product, ProductType, ProductAttributeValue, VariantInheritanceRule, ProductRelationType, ProductRelation |
| Hierarchiemodell (4) | Hierarchy, HierarchyNode, HierarchyNodeAttributeAssignment, OutputHierarchyProductAssignment |
| Medienmodell (2) | Media, ProductMediaAssignment |
| Preismodell (2) | PriceType, ProductPrice |
| Export & PXF (2) | PublixxExportMapping, PxfTemplate |
| Import (2) | ImportJob, ImportJobError |
| Benutzerverwaltung (5) | User, Role, Permission, RolePermission, UserRole |
| Performance (1) | products_search_index |
| System (1) | AuditLog |

---

## Attributmodell

### attributes

| Feld | Typ | Nullable | Beschreibung |
|------|-----|----------|--------------|
| id | CHAR(36) PK | Nein | UUID |
| technical_name | VARCHAR(100) UNIQUE | Nein | z.B. `product-weight-num` |
| name_de | VARCHAR(255) | Nein | Anzeigename deutsch |
| name_en | VARCHAR(255) | Ja | Anzeigename englisch |
| name_json | JSON | Ja | Weitere Sprachen `{"fr":"Poids"}` |
| description_de | TEXT | Ja | Beschreibung |
| description_en | TEXT | Ja | |
| data_type | ENUM('String','Number','Float','Date','Flag','Selection','Dictionary','Collection') | Nein | |
| attribute_type_id | FK → attribute_types.id | Ja | Attributgruppe |
| value_list_id | FK → value_lists.id | Ja | Bei Selection/Dictionary |
| unit_group_id | FK → unit_groups.id | Ja | Für numerische Attribute |
| default_unit_id | FK → units.id | Ja | Standard-Einheit |
| comparison_operator_group_id | FK → comparison_operator_groups.id | Ja | |
| is_translatable | BOOLEAN | Nein | Werte benötigen Übersetzung |
| is_multipliable | BOOLEAN | Nein | Vermehrbar |
| max_multiplied | INT | Ja | Max. Vermehrungen |
| max_pre_decimal | INT | Ja | Vorkommastellen |
| max_post_decimal | INT | Ja | Nachkommastellen |
| max_characters | INT | Ja | Max. Zeichenlänge |
| is_searchable | BOOLEAN DEFAULT true | Nein | In Suche enthalten |
| is_mandatory | BOOLEAN DEFAULT false | Nein | Pflichtfeld |
| is_unique | BOOLEAN DEFAULT false | Nein | Systemweit eindeutiger Wert |
| is_country_specific | BOOLEAN DEFAULT false | Nein | Länderspezifisch |
| is_inheritable | BOOLEAN DEFAULT true | Nein | Über Hierarchie vererbbar |
| parent_attribute_id | FK → attributes.id | Ja | Hierarchisches Attribut (Eltern) |
| position | INT | Ja | Sortierung |
| source_system | VARCHAR(50) | Ja | PIM / SAP ERP / Other |
| source_attribute_name | VARCHAR(255) | Ja | Name im Quellsystem |
| source_attribute_key | VARCHAR(255) | Ja | Key im Quellsystem |
| status | ENUM('active','inactive') DEFAULT 'active' | Nein | |

### attribute_types

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | z.B. `technical_attributes` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| sort_order | INT | |

### unit_groups

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | z.B. `weight`, `length` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |

### units

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| unit_group_id | FK → unit_groups.id | |
| technical_name | VARCHAR(100) | z.B. `kilogram` |
| abbreviation | VARCHAR(20) | z.B. `kg` |
| abbreviation_json | JSON nullable | Übersetzung: `{"de":"Stk.","en":"pcs."}` |
| conversion_factor | DECIMAL(20,10) DEFAULT 1 | Faktor zur Basiseinheit |
| is_base_unit | BOOLEAN | |
| is_translatable | BOOLEAN | Kürzel benötigt Übersetzung |

### value_lists

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| value_data_type | ENUM('String','Number') DEFAULT 'String' | |
| max_depth | INT DEFAULT 1 | Max. Verschachtelungstiefe |

### value_list_entries

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| value_list_id | FK → value_lists.id | |
| parent_entry_id | FK → value_list_entries.id nullable | Hierarchisch |
| technical_name | VARCHAR(100) | z.B. `red` |
| display_value_de | VARCHAR(255) | z.B. `Rot` |
| display_value_en | VARCHAR(255) nullable | z.B. `Red` |
| display_value_json | JSON nullable | `{"fr":"Rouge"}` |
| sort_order | INT | |
| is_active | BOOLEAN DEFAULT true | |
| UNIQUE(value_list_id, technical_name) | | |

### attribute_views

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | z.B. `eshop_view` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| sort_order | INT | |
| is_write_protected | BOOLEAN DEFAULT false | |

### attribute_view_assignments

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| attribute_id | FK → attributes.id | |
| attribute_view_id | FK → attribute_views.id | |
| UNIQUE(attribute_id, attribute_view_id) | | |

### comparison_operator_groups / comparison_operators

```
comparison_operator_groups: id, technical_name, name_de, name_en
comparison_operators: id, group_id (FK), technical_name, symbol, description_de
```

---

## Produktmodell

### product_types

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | `physical_product`, `training`, `service`, `software`, `bundle`, `digital_asset` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| icon | VARCHAR(50) nullable | Lucide icon name |
| color | VARCHAR(7) nullable | #hex |
| has_variants | BOOLEAN | |
| has_ean | BOOLEAN | |
| has_prices | BOOLEAN | |
| has_media | BOOLEAN | |
| has_stock | BOOLEAN | |
| has_physical_dimensions | BOOLEAN | |
| default_attribute_groups | JSON nullable | Auto-zugeordnete Gruppen |
| allowed_relation_types | JSON nullable | |
| validation_rules | JSON nullable | |
| sort_order | INT | |
| is_active | BOOLEAN DEFAULT true | |

### products

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| product_type_id | FK → product_types.id NOT NULL | |
| sku | VARCHAR(100) UNIQUE | Artikelnummer |
| ean | VARCHAR(20) nullable | EAN / GTIN |
| name | VARCHAR(500) | Produktname (Hauptsprache) |
| status | ENUM('draft','active','inactive','discontinued') DEFAULT 'draft' | |
| product_type_ref | ENUM('product','variant') DEFAULT 'product' | Produkt oder Variante |
| parent_product_id | FK → products.id nullable | Bei Varianten: Elternprodukt |
| master_hierarchy_node_id | FK → hierarchy_nodes.id nullable | Einmalzuordnung Master |
| created_by | FK → users.id nullable | |
| updated_by | FK → users.id nullable | |
| INDEX(status), INDEX(sku), INDEX(ean), INDEX(master_hierarchy_node_id), FULLTEXT(name) | | |

### product_attribute_values

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| product_id | FK → products.id | |
| attribute_id | FK → attributes.id | |
| value_string | TEXT nullable | Bei String/Selection |
| value_number | DECIMAL(20,6) nullable | Bei Number/Float |
| value_date | DATE nullable | Bei Date |
| value_flag | BOOLEAN nullable | Bei Flag |
| value_selection_id | FK → value_list_entries.id nullable | Bei Selection/Dictionary |
| unit_id | FK → units.id nullable | Gewählte Einheit |
| comparison_operator_id | FK → comparison_operators.id nullable | |
| language | VARCHAR(5) nullable | NULL=sprachunabhängig, 'de'/'en'/etc. bei übersetzten Werten |
| multiplied_index | INT DEFAULT 0 | Bei vermehrbaren Attributen: 0,1,2,... |
| is_inherited | BOOLEAN DEFAULT false | Wert kommt aus Hierarchie |
| inherited_from_node_id | FK → hierarchy_nodes.id nullable | |
| inherited_from_product_id | FK → products.id nullable | Bei Varianten |
| UNIQUE(product_id, attribute_id, language, multiplied_index) | | |
| INDEX(product_id, attribute_id) | | |
| INDEX(attribute_id, value_string(100)) | | |

### variant_inheritance_rules

```
id, product_id (FK → products), attribute_id (FK → attributes),
inheritance_mode ENUM('inherit','override')
UNIQUE(product_id, attribute_id)
```

### product_relation_types

```
id, technical_name UNIQUE, name_de, name_en, name_json, is_bidirectional BOOLEAN
```

### product_relations

```
id, source_product_id (FK), target_product_id (FK), relation_type_id (FK), sort_order
UNIQUE(source_product_id, target_product_id, relation_type_id)
```

---

## Hierarchiemodell

### hierarchies

```
id, technical_name UNIQUE, name_de, name_en, name_json,
hierarchy_type ENUM('master','output'), description
```

### hierarchy_nodes

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| hierarchy_id | FK → hierarchies.id | |
| parent_node_id | FK → hierarchy_nodes.id nullable | |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| path | VARCHAR(1000) | Materialized Path: `/node-1/node-2/` |
| depth | INT | Tiefe im Baum (0 = Root) |
| sort_order | INT | |
| is_active | BOOLEAN DEFAULT true | |
| INDEX(hierarchy_id, parent_node_id) | | |
| INDEX(path) | | |

### hierarchy_node_attribute_assignments

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | CHAR(36) PK | |
| hierarchy_node_id | FK → hierarchy_nodes.id | |
| attribute_id | FK → attributes.id | |
| collection_name | VARCHAR(255) nullable | Gruppenüberschrift in UI |
| collection_sort | INT DEFAULT 0 | Reihenfolge der Collection (10,20,30) |
| attribute_sort | INT DEFAULT 0 | Reihenfolge innerhalb Collection |
| dont_inherit | BOOLEAN DEFAULT false | Vererbung an Kinder unterdrücken |
| access_hierarchy | ENUM('hidden','visible','editable') DEFAULT 'visible' | |
| access_product | ENUM('hidden','visible','editable') DEFAULT 'editable' | |
| access_variant | ENUM('hidden','visible','editable') DEFAULT 'editable' | |

### output_hierarchy_product_assignments

```
id, hierarchy_node_id (FK), product_id (FK), sort_order
UNIQUE(hierarchy_node_id, product_id)
```

---

## Medienmodell

### media

```
id, file_name, file_path, mime_type, file_size BIGINT,
media_type ENUM('image','document','video','other'),
title_de, title_en, description_de, description_en,
alt_text_de, alt_text_en, width INT, height INT,
created_at, updated_at
```

### product_media_assignments

```
id, product_id (FK), media_id (FK), usage_type ENUM('teaser','gallery','document','technical_drawing'),
sort_order, is_primary BOOLEAN DEFAULT false
```

---

## Preismodell

### price_types

```
id, technical_name UNIQUE, name_de, name_en, name_json
```

### product_prices

```
id, product_id (FK), price_type_id (FK), amount DECIMAL(12,2),
currency VARCHAR(3) (ISO 4217), valid_from DATE, valid_to DATE nullable,
country VARCHAR(2) nullable (ISO 3166-1),
scale_from INT nullable, scale_to INT nullable,
created_at, updated_at
```

---

## Export & PXF

### publixx_export_mappings

```
id, name VARCHAR(255), attribute_view_id (FK nullable), output_hierarchy_id (FK nullable),
mapping_rules JSON, include_media BOOLEAN, include_prices BOOLEAN,
include_variants BOOLEAN, include_relations BOOLEAN,
languages JSON (["de","en"]), flatten_mode ENUM('flat','nested','publixx')
```

### pxf_templates

```
id, name VARCHAR(255), description TEXT nullable, pxf_data JSON (LONGTEXT),
version VARCHAR(10), orientation ENUM('a4hoch','a4quer','custom'),
product_type_id (FK nullable), export_mapping_id (FK nullable),
thumbnail VARCHAR(500) nullable, is_default BOOLEAN, is_active BOOLEAN,
created_at, updated_at
```

---

## Import

### import_jobs

```
id, user_id (FK), file_name, file_path, status ENUM('uploaded','validating','validated','executing','completed','failed'),
sheets_found JSON, summary JSON, result JSON,
started_at DATETIME nullable, completed_at DATETIME nullable, created_at
```

### import_job_errors

```
id, import_job_id (FK), sheet VARCHAR(100), row INT, column VARCHAR(5),
field VARCHAR(100), value TEXT, error TEXT, suggestion TEXT nullable
```

---

## Benutzerverwaltung (Spatie Permission)

### users

```
id, name, email UNIQUE, password (bcrypt), language VARCHAR(5) DEFAULT 'de',
is_active BOOLEAN DEFAULT true, last_login_at DATETIME nullable, created_at, updated_at
```

### roles, permissions, role_has_permissions, model_has_roles

Standard Spatie Permission Schema.

---

## Performance

### products_search_index

```
product_id CHAR(36) PK, sku, ean, product_type, status,
name_de VARCHAR(500), name_en VARCHAR(500), description_de TEXT,
hierarchy_path VARCHAR(1000), primary_image VARCHAR(500),
list_price DECIMAL(12,2), attribute_completeness TINYINT,
phonetic_name_de VARCHAR(100), updated_at TIMESTAMP,
FULLTEXT(name_de, name_en), FULLTEXT(description_de),
INDEX(status), INDEX(product_type), INDEX(sku), INDEX(list_price)
```

---

## System

### audit_logs

```
id, user_id (FK), auditable_type VARCHAR(100), auditable_id CHAR(36),
action ENUM('created','updated','deleted'),
old_values JSON nullable, new_values JSON nullable,
ip_address VARCHAR(45), user_agent TEXT, created_at
INDEX(auditable_type, auditable_id), INDEX(user_id), INDEX(created_at)
```

---

## Vererbungskonzept

### Hierarchie-Vererbung (Reihenfolge)
1. Attribute werden vom Wurzelknoten über Zwischenknoten bis zum Blatt vererbt
2. `dont_inherit = true` unterbricht die Vererbungskette
3. Sortierung: `collection_sort` (Gruppen in 10er-Schritten) → `attribute_sort` (innerhalb Gruppe)
4. Ein Produkt erbt alle Attribute seines `master_hierarchy_node_id` und aller Vorfahren

### Varianten-Vererbung (Auflösungsreihenfolge)
1. Eigener Wert am Produkt (override)
2. Wert vom Elternprodukt (inherit, gesteuert über `variant_inheritance_rules`)
3. Wert aus Hierarchie
4. Leer
