# anyPIM — Hierarchy-Level vs. Node-Level Attribute Assignment

> **Purpose:** Clarify two structurally separate, easily-confused mechanisms that share a
> similar "assign attribute" UI pattern: hierarchy-wide attribute registration
> (`hierarchy_attribute_assignments`) and per-node attribute inheritance
> (`hierarchy_node_attribute_assignments`, see `10-vererbung.md`). Use this when working on
> hierarchy attribute assignment, facets, output-hierarchy relationship attributes, or
> whenever "why isn't my hierarchy-level attribute showing up on the node?" comes up.

---

## TL;DR

**They are two independent tables. Assigning an attribute to a Hierarchy does NOT
automatically create anything on its nodes, and vice versa.** They happen to be edited
through visually similar "+ Attribut zuordnen" pickers, which is the main source of
confusion.

| | Hierarchy-level<br>`hierarchy_attribute_assignments` | Node-level<br>`hierarchy_node_attribute_assignments` |
|---|---|---|
| Assigned to | The `Hierarchy` record itself | An individual `HierarchyNode` |
| Admin UI | "Hierarchie bearbeiten" panel → "Zugeordnete Attribute" | Node detail view → "Attribute" tab |
| Purpose | Registry + `scope`/`is_facet` metadata | Category-tree attribute **inheritance** |
| Cascades to child nodes? | No — there is no inheritance logic for this table | Yes, via materialized-path ancestor walk (unless `dont_inherit`) |
| Key columns | `scope` (`node`\|`relationship`\|`both`), `is_facet`, `sort_order` | `dont_inherit`, `collection_name`, `collection_sort`, `attribute_sort`, `is_facet`, `is_required`, `access_*` |
| Uniqueness | `(hierarchy_id, attribute_id)` | per node/attribute |
| Actually consumed by | Output-hierarchy relationship attributes (`scope`), Asset-Catalog facets (`is_facet`) | `HierarchyInheritanceService`, node/product attribute resolution, `NodeAttributeAssignmentController` |
| Model | `app/Models/HierarchyAttributeAssignment.php` | `app/Models/HierarchyNodeAttributeAssignment.php` |
| Controller | `HierarchyAttributeAssignmentController` (`hierarchies/{hierarchy}/attributes`) | `NodeAttributeAssignmentController` (`hierarchy-nodes/{node}/attributes`) |

---

## 1. Hierarchy-Level Assignment (`hierarchy_attribute_assignments`)

A lightweight registry: "this attribute is meaningful somewhere in this classification
tree," plus two metadata flags that control *how* it's meaningful.

```
hierarchy_id | attribute_id | scope        | is_facet | sort_order
katalog-2025 | technologie  | node         | true     | 10
laender-baum | zulassung    | relationship | false    | 20
```

### `scope` (three values, enforced server-side)

- **`node`** (default, labelled "Knoten" in the UI) — the attribute is meant to be
  maintained on the classification node itself.
- **`relationship`** (labelled "Beziehung") — the attribute is meant to be maintained on
  the **product ↔ output-node edge**, not on the node or the product. Example: "Dachbox
  passt zu Audi A1/A3/A5" — the fitment note or a channel-specific price override only
  makes sense for *that specific* product-in-that-specific-node combination.
- **`both`** — maintainable in either place.

The UI (`HierarchyFormPanel.vue`) renders `scope` as a click-to-cycle badge
(`node → relationship → both → node`) labelled "Knoten"/"Beziehung"/"Beide". "Knoten" is
just the display label for `scope === 'node'` — it is **not** a second flag and does
**not** mean "applies to all nodes" or control any depth restriction.

### `is_facet`

An independent boolean. Toggled via its own badge ("Facette") in the same panel.
Facet display order reuses `sort_order`, filtered to `is_facet = true` rows.

**Important:** despite the name, this flag does **not** feed the public product catalog's
facet search — that reads `facet_attribute_ids` from `WebsiteProfile` (per-website theme
config), entirely independent of this table. `is_facet` on
`hierarchy_attribute_assignments` (and its node-level counterpart) is currently only
consumed by the **Asset/DAM catalog** facet search (see §3).

---

## 2. Node-Level Assignment & Inheritance (`hierarchy_node_attribute_assignments`)

This is the actual "classic PIM" mechanism: assign an attribute once on a node (typically
the hierarchy's root), and every descendant node — and by extension every product
classified under it — inherits it automatically.

Fully documented in **`10-vererbung.md`**. Short version:

- `HierarchyInheritanceService::computeEffectiveAttributes()` walks ancestors via the
  materialized `path` column, includes ancestor rows where `dont_inherit = false`, always
  includes the node's own rows, dedupes by `attribute_id` (deepest node wins), sorts by
  `collection_sort` → `attribute_sort`. Cached per node for 1h
  (`Cache::tags(["hierarchy_node:{id}"])`).
- `NodeAttributeAssignmentController::index()` (`GET /hierarchy-nodes/{node}/attributes?inherited=true`)
  implements the same ancestor-walk directly and marks each row `is_inherited_assignment`.
- `NodeAttributeAssignmentResource` surfaces this as `is_inherited` +
  `inherited_from_node_name` — **this is the ancestor node's name**, which is why the
  admin UI badge reads "vererbt von Katalog 2025": that's naming the *node* (usually the
  hierarchy's root node), not the hierarchy-level table from §1.
- `dont_inherit = true` on a row means: shown on this node, but the chain stops here — it
  is not passed down further.

---

## 3. Where each is actually consumed today

| Consumer | Reads | Notes |
|---|---|---|
| `OutputHierarchyProductAssignmentController::relationshipAttributes()` / `updateRelationshipAttributes()` | Hierarchy-level, filtered `scope IN (relationship, both)` | Powers the editable fields on a product↔output-node assignment ("Beziehungs-Attribute") |
| `AssetCatalogController::facets()` | Both tables' `is_facet = true` (hierarchy-level wins on conflict) | Asset/DAM catalog facet search only |
| Public product catalog facets (`CatalogController::facets()`) | `WebsiteProfile.facet_attribute_ids` | **Not** either attribute-assignment table |
| `HierarchyInheritanceService`, product/node attribute resolution | Node-level only | The actual inheritance engine |
| JSON full export (`JsonFormatExporter::exportHierarchyLevelAttributeAssignments()`) | Hierarchy-level: `hierarchy`, `attribute`, `sort_order` only | Does **not** round-trip `scope`/`is_facet` yet |
| BMEcat import (`BmecatFormatImporter::buildHierarchyAttributeAssignments()`) | Produces **node-level** rows despite the method name | Feeds sheet `07_Hierarchie_Attribute` → `HierarchyNodeAttributeAssignment`, not the hierarchy-level table |

---

## 4. Common misconception

> "I assigned the attribute at the hierarchy level, so it should now show up as inherited
> on every node underneath."

**Not true today.** A hierarchy-level `scope=node` row is purely a registry entry — no
code path turns it into a `HierarchyNodeAttributeAssignment` anywhere. If you want an
attribute to actually appear on every node in a tree, assign it on the hierarchy's **root
node** (node-level, §2) and let inheritance carry it down — that is the only mechanism
that cascades.

---

## Known documentation gaps (not yet fixed)

- `docs/architecture/12-json-export-import.md` (lines ~365-391) documents a
  `hierarchy_attribute_assignments` export schema with fields (`node_path`,
  `collection_name`, `collection_sort`, `attribute_sort`, `dont_inherit`) that actually
  belong to the **node-level** table — the real hierarchy-level table has no such columns.
  Worth correcting if someone is actively working from that file.
- `docs/reference/database.md`'s `hierarchy_attribute_assignments` entry has been updated
  alongside this doc to list `scope`/`is_facet`/`sort_order`, but was previously a single
  stale line with no columns at all.
