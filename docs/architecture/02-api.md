# anyPIM — RESTful API

> **Purpose:** Complete API reference. Use this skill when creating controllers, routes, form requests, API resources, and tests.

---

## Conventions

| Convention | Value | Example |
|------------|-------|---------|
| Base URL | `/api/v1` | `https://pim.example.com/api/v1` |
| Auth | Bearer Token (Laravel Sanctum) | `Authorization: Bearer {token}` |
| Content-Type | `application/json` | |
| Pagination | Cursor + Page | `?page=2&per_page=50` |
| Sorting | `?sort=field&order=asc|desc` | `?sort=name_de&order=asc` |
| Filter | `?filter[field]=value` | `?filter[status]=active` |
| Includes | `?include=rel1,rel2` | `?include=unitGroup,valueList` |
| Sparse Fields | `?fields[type]=f1,f2` | `?fields[products]=id,sku,name` |
| Search | `?search=term` | `?search=Bohrmaschine` |
| Language | `Accept-Language` header or `?lang=de` | Multi: `?lang=de,en` |
| Errors | RFC 7807 Problem Details | `{ "type": "...", "title": "...", "status": 422 }` |
| Rate Limit | 60 req/min (default), 600/min (export) | |

---

## Endpoints

### Auth

```
POST   /auth/login              Login → Token
POST   /auth/logout             Logout → Invalidate token
GET    /auth/me                 Current user + permissions
POST   /auth/refresh            Refresh token
```

### Attributes

```
GET    /attributes                         All (paginated, filterable)
POST   /attributes                         Create
GET    /attributes/{id}                    Detail (?include=valueList,unitGroup,children)
PUT    /attributes/{id}                    Update
DELETE /attributes/{id}                    Delete
```

### Attribute Types (Groups)

```
GET    /attribute-types                    All
POST   /attribute-types                    Create
PUT    /attribute-types/{id}               Update
DELETE /attribute-types/{id}               Delete
```

### Units

```
GET    /unit-groups                        All (?include=units)
POST   /unit-groups                        Create group
GET    /unit-groups/{id}                   Detail with units
PUT    /unit-groups/{id}                   Update group
DELETE /unit-groups/{id}                   Delete group
POST   /unit-groups/{id}/units             Add unit
PUT    /units/{id}                         Update unit
DELETE /units/{id}                         Delete unit
```

### Value Lists

```
GET    /value-lists                        All (?include=entries)
POST   /value-lists                        Create
POST   /value-lists/{id}/entries           Add entry
PUT    /value-list-entries/{id}            Update entry
DELETE /value-list-entries/{id}            Delete entry
```

### Attribute Views

```
GET    /attribute-views                    All
POST   /attribute-views                    Create
PUT    /attribute-views/{id}               Update
DELETE /attribute-views/{id}               Delete
POST   /attribute-views/{id}/attributes    Assign attribute
DELETE /attribute-views/{id}/attributes/{attr_id}  Remove assignment
```

### Product Types

```
GET    /product-types                      All
POST   /product-types                      Create
GET    /product-types/{id}                 Detail
PUT    /product-types/{id}                 Update
DELETE /product-types/{id}                 Delete
GET    /product-types/{id}/schema          Effective attribute schema
```

### Hierarchies

```
GET    /hierarchies                        All (?filter[type]=master)
POST   /hierarchies                        Create
PUT    /hierarchies/{id}                   Update
DELETE /hierarchies/{id}                   Delete
GET    /hierarchies/{id}/tree              Complete tree as JSON (?depth=3)
GET    /hierarchies/{id}/nodes             Flat node list
POST   /hierarchies/{id}/nodes             Create node
PUT    /hierarchy-nodes/{id}               Update node
DELETE /hierarchy-nodes/{id}               Delete node (with children)
PUT    /hierarchy-nodes/{id}/move          Move node {parent_node_id, sort_order}
```

### Hierarchy Attribute Assignment

```
GET    /hierarchy-nodes/{id}/attributes    Assigned attributes (?inherited=true)
POST   /hierarchy-nodes/{id}/attributes    Assign attribute
PUT    /node-attribute-assignments/{id}    Modify (sort, collection)
DELETE /node-attribute-assignments/{id}    Remove
PUT    /node-attribute-assignments/bulk-sort  Drag & drop order
```

### Products

```
GET    /products                           All (paginated, filterable, searchable)
POST   /products                           Create {product_type_id, sku, name, ...}
GET    /products/{id}                      Detail (?include=attributeValues,variants,media,prices)
PUT    /products/{id}                      Update
DELETE /products/{id}                      Delete
```

### Variants

```
GET    /products/{id}/variants             Variants of a product
POST   /products/{id}/variants             Create variant
GET    /products/{id}/variant-rules        Inheritance rules
PUT    /products/{id}/variant-rules        Set rules {rules: [{attribute_id, mode}]}
```

### Attribute Values

```
GET    /products/{id}/attribute-values     All values (?view=eshop_view&lang=de)
PUT    /products/{id}/attribute-values     Bulk save {values: [{attribute_id, value, ...}]}
```

### Media

```
GET    /media                              All (?filter[media_type]=image)
POST   /media                              Upload (multipart/form-data)
GET    /media/{id}                         Detail
PUT    /media/{id}                         Update metadata
DELETE /media/{id}                         Delete
GET    /media/file/{filename}              Serve file directly (for assetBase)
```

### Product Media

```
GET    /products/{id}/media                Assigned media
POST   /products/{id}/media                Assign medium
DELETE /product-media/{id}                 Remove assignment
```

### Prices

```
GET    /products/{id}/prices               Prices of a product
POST   /products/{id}/prices               Create price
PUT    /product-prices/{id}                Update
DELETE /product-prices/{id}                Delete
GET    /price-types                        All price types (CRUD)
```

### Relations

```
GET    /products/{id}/relations            All relations
POST   /products/{id}/relations            Create
DELETE /product-relations/{id}             Delete
GET    /relation-types                     All relation types (CRUD)
```

### Users & Roles

```
GET    /users                              All
POST   /users                              Create {name, email, password, role_id}
PUT    /users/{id}                         Update
DELETE /users/{id}                         Delete
GET    /roles                              All (?include=permissions)
POST   /roles                              Create {name, permissions: [...]}
PUT    /roles/{id}                         Update
DELETE /roles/{id}                         Delete
PUT    /roles/{id}/permissions             Set permissions
```

### Export

```
GET    /export/products                    Products as JSON (filter via query params)
GET    /export/products/{id}               Single product as dataset
POST   /export/products/bulk               Bulk export by filter
GET    /export/products/{id}/publixx       In Publixx dataset format
POST   /export/query                       Export with PQL filter
```

### PQL

```
POST   /pql/query                          Execute PQL query → JSON array
POST   /pql/query/count                    Count only
POST   /pql/query/validate                 Validate query
POST   /pql/query/explain                  Query plan + cost
```

### Publixx Integration

```
GET    /publixx/datasets/{mapping_id}                All products as dataset array
GET    /publixx/datasets/{mapping_id}/{product_id}   Single product dataset
POST   /publixx/datasets/{mapping_id}/pql            Datasets with PQL query
POST   /publixx/webhook                              Webhook from Publixx
```


### Import

```
POST   /imports                            Upload Excel → Start validation
GET    /imports/{id}                        Status + validation result
GET    /imports/{id}/preview                Preview (create/update/skip)
POST   /imports/{id}/execute                Execute import
GET    /imports/{id}/result                 Result report
GET    /imports/templates/{type}            Download empty Excel template
DELETE /imports/{id}                        Cancel / Delete
```

---

## Permission Schema

Format: `{entity}.{action}` with optional restriction:

```
products.view              View products
products.create            Create products
products.edit              Edit products
products.edit:eshop_view   Edit only e-shop attributes
products.edit:{node-uuid}  Edit only products under a node
products.delete            Delete products
attributes.*               All attribute operations
export.*                   All export operations
users.*                    User management
```

Predefined roles: Admin, Data Steward, Product Manager, Viewer, Export Manager
