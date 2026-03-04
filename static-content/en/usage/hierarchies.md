---
title: Hierarchies
---

# Hierarchies

Hierarchies are a central organizing principle in Publixx PIM. They structure products in tree structures and control which attributes are available to products in a specific category. The system distinguishes two hierarchy types: **Master hierarchies** for internal product classification and **Output hierarchies** for the export structure.

## Hierarchy Types

### Master Hierarchies

Master hierarchies form the **primary product classification** and serve the following purposes:

- **Product assignment** -- Each product is assigned to exactly one node in the master hierarchy. This assignment determines the product category.
- **Attribute inheritance** -- Attribute groups (with Collection Groups) are assigned to the nodes of a master hierarchy. Products inherit the attributes of their node as well as all parent nodes.
- **Structural classification** -- The master hierarchy defines the product groups and product categories of your assortment.

::: info Example
If the attribute group "Technical Data" is assigned to the node "Electronics" and the group "Display Properties" is assigned to the subnode "Smartphones", then a product in "Smartphones" receives both the attributes from "Technical Data" and from "Display Properties".
:::

### Output Hierarchies

Output hierarchies define the **export structure** and are used to map catalog structures, shop categories, or other external classification systems:

- **Multiple assignment** -- A product can appear in multiple output hierarchies and at multiple positions within an output hierarchy.
- **Export control** -- The structure of the output hierarchy is used as the category tree during export.
- **Independent of attributes** -- Output hierarchies have no influence on the available attributes of a product.

## Example Hierarchy Structure

<svg viewBox="0 0 800 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .h-bg { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 2; rx: 12; }
      .h-node { fill: #ffffff; stroke: #6366f1; stroke-width: 1.5; rx: 6; }
      .h-node-root { fill: #6366f1; stroke: none; rx: 6; }
      .h-node-l1 { fill: #ffffff; stroke: #6366f1; stroke-width: 2; rx: 6; }
      .h-node-l2 { fill: #ffffff; stroke: #818cf8; stroke-width: 1.5; rx: 6; }
      .h-node-l3 { fill: #ffffff; stroke: #a5b4fc; stroke-width: 1; rx: 6; }
      .h-text-root { fill: #ffffff; font-family: system-ui, sans-serif; font-size: 13px; font-weight: 700; }
      .h-text-l1 { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .h-text-l2 { fill: #334155; font-family: system-ui, sans-serif; font-size: 11px; font-weight: 500; }
      .h-text-l3 { fill: #475569; font-family: system-ui, sans-serif; font-size: 11px; }
      .h-line { stroke: #cbd5e1; stroke-width: 1.5; fill: none; }
      .h-badge { fill: #dbeafe; rx: 8; }
      .h-badge-text { fill: #1e40af; font-family: system-ui, sans-serif; font-size: 9px; font-weight: 600; }
      .h-label { fill: #94a3b8; font-family: system-ui, sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
      .h-heading { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 15px; font-weight: 700; }
    </style>
  </defs>
  <rect class="h-bg" x="0" y="0" width="800" height="520" />

  <!-- Title -->
  <text class="h-heading" x="24" y="30">Master Hierarchy: Product Catalog (Example)</text>
  <text class="h-label" x="24" y="48">Up to 6 levels deep. Attributes are inherited from top to bottom.</text>

  <!-- Root node -->
  <rect class="h-node-root" x="320" y="68" width="160" height="36" />
  <text class="h-text-root" x="348" y="91">All Products</text>

  <!-- Lines from root to level 1 -->
  <path class="h-line" d="M 400 104 L 400 120 L 150 120 L 150 140" />
  <path class="h-line" d="M 400 104 L 400 140" />
  <path class="h-line" d="M 400 104 L 400 120 L 650 120 L 650 140" />

  <!-- Level 1 nodes -->
  <rect class="h-node-l1" x="70" y="140" width="160" height="36" />
  <text class="h-text-l1" x="108" y="163">Electronics</text>
  <rect class="h-badge" x="178" y="146" width="42" height="18" />
  <text class="h-badge-text" x="186" y="159">12 Attr</text>

  <rect class="h-node-l1" x="320" y="140" width="160" height="36" />
  <text class="h-text-l1" x="347" y="163">Household</text>
  <rect class="h-badge" x="418" y="146" width="42" height="18" />
  <text class="h-badge-text" x="426" y="159">8 Attr</text>

  <rect class="h-node-l1" x="570" y="140" width="160" height="36" />
  <text class="h-text-l1" x="598" y="163">Clothing</text>
  <rect class="h-badge" x="680" y="146" width="42" height="18" />
  <text class="h-badge-text" x="688" y="159">6 Attr</text>

  <!-- Lines from Elektronik to level 2 -->
  <path class="h-line" d="M 150 176 L 150 192 L 80 192 L 80 210" />
  <path class="h-line" d="M 150 176 L 150 192 L 230 192 L 230 210" />

  <!-- Level 2 under Electronics -->
  <rect class="h-node-l2" x="16" y="210" width="130" height="32" />
  <text class="h-text-l2" x="32" y="231">Smartphones</text>

  <rect class="h-node-l2" x="168" y="210" width="130" height="32" />
  <text class="h-text-l2" x="192" y="231">Laptops</text>

  <!-- Lines from Smartphones to level 3 -->
  <path class="h-line" d="M 80 242 L 80 256 L 44 256 L 44 272" />
  <path class="h-line" d="M 80 242 L 80 256 L 130 256 L 130 272" />

  <!-- Level 3 under Smartphones -->
  <rect class="h-node-l3" x="4" y="272" width="85" height="28" />
  <text class="h-text-l3" x="16" y="290">Android</text>

  <rect class="h-node-l3" x="100" y="272" width="85" height="28" />
  <text class="h-text-l3" x="120" y="290">iOS</text>

  <!-- Lines from Laptops to level 3 -->
  <path class="h-line" d="M 230 242 L 230 256 L 195 256 L 195 272" />
  <path class="h-line" d="M 230 242 L 230 256 L 275 256 L 275 272" />

  <rect class="h-node-l3" x="150" y="272" width="95" height="28" />
  <text class="h-text-l3" x="162" y="290">Notebooks</text>

  <rect class="h-node-l3" x="256" y="272" width="95" height="28" />
  <text class="h-text-l3" x="268" y="290">Ultrabooks</text>

  <!-- Lines from Household to level 2 -->
  <path class="h-line" d="M 400 176 L 400 192 L 355 192 L 355 210" />
  <path class="h-line" d="M 400 176 L 400 192 L 455 192 L 455 210" />

  <!-- Level 2 under Household -->
  <rect class="h-node-l2" x="290" y="210" width="130" height="32" />
  <text class="h-text-l2" x="304" y="231">Kitchen Appliances</text>

  <rect class="h-node-l2" x="438" y="210" width="130" height="32" />
  <text class="h-text-l2" x="468" y="231">Furniture</text>

  <!-- Lines from Clothing to level 2 -->
  <path class="h-line" d="M 650 176 L 650 192 L 600 192 L 600 210" />
  <path class="h-line" d="M 650 176 L 650 192 L 710 192 L 710 210" />

  <!-- Level 2 under Clothing -->
  <rect class="h-node-l2" x="538" y="210" width="130" height="32" />
  <text class="h-text-l2" x="564" y="231">Outerwear</text>

  <rect class="h-node-l2" x="680" y="210" width="100" height="32" />
  <text class="h-text-l2" x="700" y="231">Shoes</text>

  <!-- Legend -->
  <rect fill="#f1f5f9" x="16" y="330" width="768" height="176" rx="8" />
  <text class="h-heading" x="32" y="356">Legend</text>

  <rect class="h-node-root" x="32" y="370" width="18" height="18" />
  <text class="h-text-l2" x="60" y="384">Root node (Level 0)</text>

  <rect class="h-node-l1" x="32" y="396" width="18" height="18" />
  <text class="h-text-l2" x="60" y="410">Level 1 -- Main categories</text>

  <rect class="h-node-l2" x="32" y="422" width="18" height="18" />
  <text class="h-text-l2" x="60" y="436">Level 2 -- Subcategories</text>

  <rect class="h-node-l3" x="32" y="448" width="18" height="18" />
  <text class="h-text-l2" x="60" y="462">Level 3+ -- Fine structure (up to Level 6)</text>

  <rect class="h-badge" x="32" y="476" width="42" height="16" />
  <text class="h-badge-text" x="40" y="488">N Attr</text>
  <text class="h-text-l2" x="84" y="489">Number of directly assigned attribute groups</text>
</svg>

## Tree Structure and Navigation

You can access the hierarchy management via the **Hierarchies** menu item in the sidebar. The view shows the hierarchy tree as an interactive tree component (PimTree).

### Tree View

- **Expand/Collapse** -- Click on the arrow next to a node to show or hide its child nodes.
- **Node selection** -- Click on the node name to load its details in the right panel.
- **Context menu** -- A right-click or the three-dot menu on the node offers actions such as rename, move, and delete.

### Drag-and-Drop

Nodes can be moved within the hierarchy via drag-and-drop:

1. Click and hold a node.
2. Drag it to the desired target node.
3. The node is inserted as a child node of the target node.
4. Alternatively, you can place the node between two sibling nodes to reorder it at the same level.

::: warning Note
When moving a node, all child nodes are moved along with it. The attribute assignments are preserved, but the inherited attributes of the products may change, as they now inherit from a different parent path.
:::

## Node Management

### Creating a Node

1. Select the parent node under which the new node should be created.
2. Click **+ New Node** or use the context menu.
3. Enter the **Name (DE/EN)** of the node in the panel (HierarchyNodeFormPanel).
4. Save. The node appears as the last child node of the selected parent node.

### Renaming a Node

Open the editing panel of the node and change the display name. The name can be maintained in German and English.

### Moving a Node

In addition to drag-and-drop, you can also move nodes via the editing panel by selecting a new parent node.

### Deleting a Node

When deleting a node, you have two options:

| Option | Behavior |
|---|---|
| **Only this node** | The node is removed. Child nodes are moved up one level. |
| **With all child nodes** | The entire subtree is deleted. |

::: danger Warning
Products assigned to a deleted node lose their hierarchy assignment and thereby potentially their attribute definitions. Check which products are affected before deleting.
:::

## Assigning Attributes to Hierarchy Nodes

A central feature of master hierarchies is the **assignment of attributes to nodes**. This assignment determines which attributes are available for products in a specific category.

### Creating an Assignment

1. Select a node in the master hierarchy.
2. Open the **Attribute Assignments** section.
3. Click **+ Assign Attribute Group**.
4. Select an attribute group (AttributeType).
5. Optional: Define a **Collection Group** if the attributes should be created as a repeatable block.

### Inheritance Along the Path

Attributes are inherited along the hierarchy path. A product in the node "Smartphones > Android" receives:

1. Attributes of the **All Products** node (root)
2. Attributes of the **Electronics** node
3. Attributes of the **Smartphones** node
4. Attributes of the **Android** node

The attributes of all parent nodes are cumulatively passed on to the products. There is no way to remove inherited attributes at a deeper level -- but they can be set to optional or hidden.

### Collection Groups

Collection Groups allow assigning an attribute group as a **repeatable block**. Example:

- The attribute group "Certification" contains the attributes `cert_name`, `cert_number`, `cert_valid_until`.
- When assigned as a Collection Group, the user can create any number of certification entries for the product.

## Assigning Products to Hierarchy Nodes

### Master Hierarchy

In the master hierarchy, each product is assigned to **exactly one** node. The assignment is done:

- **During product creation** -- In the creation panel, the master hierarchy node can optionally be selected.
- **In the product detail view** -- The assigned node can be changed via the hierarchy dropdown.
- **In the hierarchy tree** -- In the node detail, you can search for and assign products.

### Output Hierarchy

In output hierarchies, a product can be assigned to **multiple nodes**. The assignment is done via the node detail view, where you can add and remove products.

## Hierarchy Depth

Hierarchies support up to **six levels** (root + 5 sublevels). This restriction ensures that the performance of recursive queries (CTEs) remains optimal and the navigation stays clear.

| Level | Typical Usage |
|---|---|
| 0 | Root node (entire catalog) |
| 1 | Main categories (Electronics, Household, ...) |
| 2 | Subcategories (Smartphones, Laptops, ...) |
| 3 | Fine structure (Android, iOS, ...) |
| 4 | Special categories |
| 5 | Detail categories |

## Next Steps

- Learn how [Attributes](./attributes) are defined and organized into groups.
- Learn how [Products](./products) are assigned and maintained in the hierarchy.
- Configure [User permissions](./users) based on hierarchy nodes.
