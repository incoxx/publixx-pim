# Publixx PIM — Vererbungskonzept

> **Zweck:** Attributvererbung über Hierarchien und von Produkten auf Varianten. Verwende diesen Skill beim Implementieren der Vererbungs-Engine und der Datenpflege-Logik.

---

## Zwei Vererbungsarten

### 1. Hierarchie-Vererbung (Attribute an Produkte)

Attribute werden von Hierarchieknoten an darunter liegende Produkte vererbt. Ein Produkt hat einen `master_hierarchy_node_id` und erbt alle Attribute dieses Knotens plus aller Vorfahren.

```
Elektrowerkzeuge (3 Attribute: Produktname, SKU, Gewicht)
└── Akkubohrschrauber (+2: Drehmoment, Drehzahl → gesamt 5)
    └── mit Akku (+2: Akkukapazität, Ladedauer → gesamt 7)
        └── Produkt "ProDrill 18V" → erbt alle 7 Attribute
```

### 2. Varianten-Vererbung (Produkt → Variante)

Attributwerte vererben sich vom Hauptprodukt auf seine Varianten. Die `variant_inheritance_rules` steuern pro Attribut: `inherit` (Wert kommt vom Elternprodukt) oder `override` (Variante hat eigenen Wert).

---

## Hierarchie-Vererbung: Details

### Tabelle: hierarchy_node_attribute_assignments

```
hierarchy_node_id | attribute_id | collection_name | collection_sort | attribute_sort | dont_inherit
node-elektro      | attr-name    | Stammdaten      | 10              | 10             | false
node-elektro      | attr-sku     | Stammdaten      | 10              | 20             | false
node-elektro      | attr-weight  | Technik         | 20              | 10             | false
node-akku-bohr    | attr-torque  | Technik         | 20              | 20             | false
node-akku-bohr    | attr-rpm     | Technik         | 20              | 30             | false
node-mit-akku     | attr-cap     | Akku            | 30              | 10             | false
node-mit-akku     | attr-charge  | Akku            | 30              | 20             | false
```

### Auflösung: Effektive Attribute eines Knotens

```php
function getEffectiveAttributes(HierarchyNode $node): Collection {
    // 1. Alle Vorfahren sammeln (über Materialized Path)
    $ancestors = HierarchyNode::where(function ($q) use ($node) {
        // path des Knotens LIKE CONCAT(ancestor.path, '%')
    })->orderBy('depth')->get();
    
    // 2. Attribute aller Knoten sammeln
    $attributes = collect();
    foreach ([$ancestors, $node] as $n) {
        $nodeAttrs = $n->attributeAssignments()
            ->where('dont_inherit', false)  // Nur vererbbare
            ->get();
        $attributes = $attributes->merge($nodeAttrs);
    }
    
    // 3. Sortierung: collection_sort → attribute_sort
    return $attributes
        ->sortBy('collection_sort')
        ->sortBy('attribute_sort');
}
```

### dont_inherit Flag

Wenn `dont_inherit = true`:
- Das Attribut wird am Knoten selbst angezeigt
- Aber NICHT an Kindknoten weitervererbt
- Use-Case: Ein Attribut nur auf einer bestimmten Ebene pflegen

### Sortierung

- `collection_sort`: Reihenfolge der Gruppen (10, 20, 30...)
- `attribute_sort`: Reihenfolge innerhalb einer Gruppe (10, 20, 30...)
- Zehnerschritte ermöglichen einfaches Einfügen

---

## Varianten-Vererbung: Details

### Tabelle: variant_inheritance_rules

```
product_id (Variante!) | attribute_id | inheritance_mode
variant-2ah             | attr-name    | override    → Variante hat eigenen Namen
variant-2ah             | attr-price   | override    → Eigener Preis
variant-2ah             | attr-weight  | inherit     → Gewicht vom Elternprodukt
variant-2ah             | attr-torque  | inherit     → Drehmoment vom Elternprodukt
```

### Auflösungsreihenfolge (Attributwert eines Produkts)

```
1. Eigener Wert am Produkt (product_attribute_values WHERE product_id = X)
   → Wenn vorhanden: Diesen verwenden
   
2. Bei Varianten mit inheritance_mode = 'inherit':
   → Wert vom parent_product_id laden
   
3. Hierarchie-Vererbung:
   → Default-Wert vom Hierarchieknoten (falls definiert)
   
4. Leer (kein Wert gefunden)
```

### PHP: Wert auflösen

```php
function resolveAttributeValue(Product $product, Attribute $attribute, ?string $lang): mixed {
    // 1. Eigener Wert
    $own = $product->attributeValues()
        ->where('attribute_id', $attribute->id)
        ->where('language', $lang)
        ->first();
    if ($own) return $own;
    
    // 2. Varianten-Vererbung
    if ($product->parent_product_id) {
        $rule = VariantInheritanceRule::where('product_id', $product->id)
            ->where('attribute_id', $attribute->id)
            ->first();
        
        if (!$rule || $rule->inheritance_mode === 'inherit') {
            $parentValue = resolveAttributeValue(
                $product->parentProduct, $attribute, $lang
            );
            if ($parentValue) return $parentValue;
        }
    }
    
    // 3. Hierarchie (optional: Default-Werte auf Knotenebene)
    // ...
    
    // 4. Leer
    return null;
}
```

---

## UI: Vererbung visualisieren

| Situation | Darstellung |
|-----------|-------------|
| Wert selbst gepflegt | Normales Eingabefeld |
| Wert vererbt (Hierarchie) | Grau/Read-Only + Badge: "Vererbt von: Elektrowerkzeuge" |
| Wert vererbt (Produkt→Variante) | Grau/Read-Only + Badge: "Vererbt von: ProDrill 18V" |
| Override möglich | Button "Eigenen Wert setzen" am vererbten Feld |
| Override aktiv | Normales Feld + Badge: "Überschreibt Vererbung" + Button "Vererbung wiederherstellen" |

---

## Cache-Invalidierung bei Vererbung

Wenn sich ein Attributwert ändert, müssen alle Produkte invalidiert werden, die diesen Wert erben:

```php
// Hierarchie-Vererbung: Wenn Knoten-Attribut sich ändert
// → Alle Produkte unter diesem Knoten invalidieren
$productIds = Product::where('master_hierarchy_node_id', function ($q) use ($node) {
    $q->select('id')->from('hierarchy_nodes')
      ->where('path', 'LIKE', $node->path . '%');
})->pluck('id');

Cache::tags($productIds->map(fn($id) => "product:$id")->toArray())->flush();

// Varianten-Vererbung: Wenn Elternprodukt sich ändert
// → Alle Varianten invalidieren
$variantIds = Product::where('parent_product_id', $parentId)->pluck('id');
```
