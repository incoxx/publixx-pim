# anyPIM — Inheritance Concept

> **Purpose:** Attribute inheritance across hierarchies and from products to variants. Use this skill when implementing the inheritance engine and data maintenance logic.

> **Not to be confused with:** hierarchy-level attribute assignment (`hierarchy_attribute_assignments`,
> the "Hierarchie bearbeiten" panel) is a *separate* mechanism that does not cascade to nodes —
> see `docs/architecture/24-hierarchie-attribut-zuordnung.md` for the distinction. Only the
> node-level table described below actually inherits.

---

## Two Types of Inheritance

### 1. Hierarchy Inheritance (Attributes to Products)

Attributes are inherited from hierarchy nodes to the products beneath them. A product has a `master_hierarchy_node_id` and inherits all attributes of that node plus all ancestors.

```
Power Tools (3 attributes: Product Name, SKU, Weight)
└── Cordless Drills (+2: Torque, RPM → total 5)
    └── With Battery (+2: Battery Capacity, Charging Time → total 7)
        └── Product "ProDrill 18V" → inherits all 7 attributes
```

### 2. Variant Inheritance (Product → Variant)

Attribute values are inherited from the main product to its variants. The `variant_inheritance_rules` control per attribute: `inherit` (value comes from parent product) or `override` (variant has its own value).

---

## Hierarchy Inheritance: Details

### Table: hierarchy_node_attribute_assignments

```
hierarchy_node_id | attribute_id | collection_name | collection_sort | attribute_sort | dont_inherit
node-elektro      | attr-name    | Master Data     | 10              | 10             | false
node-elektro      | attr-sku     | Master Data     | 10              | 20             | false
node-elektro      | attr-weight  | Technical       | 20              | 10             | false
node-akku-bohr    | attr-torque  | Technical       | 20              | 20             | false
node-akku-bohr    | attr-rpm     | Technical       | 20              | 30             | false
node-mit-akku     | attr-cap     | Battery         | 30              | 10             | false
node-mit-akku     | attr-charge  | Battery         | 30              | 20             | false
```

### Resolution: Effective Attributes of a Node

```php
function getEffectiveAttributes(HierarchyNode $node): Collection {
    // 1. Collect all ancestors (via Materialized Path)
    $ancestors = HierarchyNode::where(function ($q) use ($node) {
        // node's path LIKE CONCAT(ancestor.path, '%')
    })->orderBy('depth')->get();

    // 2. Collect attributes from all nodes
    $attributes = collect();
    foreach ([$ancestors, $node] as $n) {
        $nodeAttrs = $n->attributeAssignments()
            ->where('dont_inherit', false)  // Only inheritable
            ->get();
        $attributes = $attributes->merge($nodeAttrs);
    }

    // 3. Sorting: collection_sort → attribute_sort
    return $attributes
        ->sortBy('collection_sort')
        ->sortBy('attribute_sort');
}
```

### dont_inherit Flag

When `dont_inherit = true`:
- The attribute is displayed on the node itself
- But is NOT inherited to child nodes
- Use case: Maintain an attribute only at a specific level

### Sorting

- `collection_sort`: Order of groups (10, 20, 30...)
- `attribute_sort`: Order within a group (10, 20, 30...)
- Steps of ten allow easy insertion

---

## Variant Inheritance: Details

### Table: variant_inheritance_rules

```
product_id (Variant!) | attribute_id | inheritance_mode
variant-2ah             | attr-name    | override    → Variant has its own name
variant-2ah             | attr-price   | override    → Own price
variant-2ah             | attr-weight  | inherit     → Weight from parent product
variant-2ah             | attr-torque  | inherit     → Torque from parent product
```

### Resolution Order (Attribute Value of a Product)

```
1. Own value on the product (product_attribute_values WHERE product_id = X)
   → If present: Use this value

2. For variants with inheritance_mode = 'inherit':
   → Load value from parent_product_id

3. Hierarchy inheritance:
   → Default value from hierarchy node (if defined)

4. Empty (no value found)
```

### PHP: Resolve Value

```php
function resolveAttributeValue(Product $product, Attribute $attribute, ?string $lang): mixed {
    // 1. Own value
    $own = $product->attributeValues()
        ->where('attribute_id', $attribute->id)
        ->where('language', $lang)
        ->first();
    if ($own) return $own;

    // 2. Variant inheritance
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

    // 3. Hierarchy (optional: default values at node level)
    // ...

    // 4. Empty
    return null;
}
```

---

## UI: Visualizing Inheritance

| Situation | Display |
|-----------|---------|
| Value maintained directly | Normal input field |
| Value inherited (hierarchy) | Gray/Read-Only + Badge: "Inherited from: Power Tools" |
| Value inherited (Product→Variant) | Gray/Read-Only + Badge: "Inherited from: ProDrill 18V" |
| Override possible | Button "Set own value" on inherited field |
| Override active | Normal field + Badge: "Overrides inheritance" + Button "Restore inheritance" |

---

## Cache Invalidation on Inheritance

When an attribute value changes, all products that inherit this value must be invalidated:

```php
// Hierarchy inheritance: When node attribute changes
// → Invalidate all products under this node
$productIds = Product::where('master_hierarchy_node_id', function ($q) use ($node) {
    $q->select('id')->from('hierarchy_nodes')
      ->where('path', 'LIKE', $node->path . '%');
})->pluck('id');

Cache::tags($productIds->map(fn($id) => "product:$id")->toArray())->flush();

// Variant inheritance: When parent product changes
// → Invalidate all variants
$variantIds = Product::where('parent_product_id', $parentId)->pluck('id');
```
