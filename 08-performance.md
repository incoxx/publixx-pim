# anyPIM — Performance Architecture

> **Purpose:** The system must be blazingly fast. Use this skill when optimizing, caching, indexing, and making infrastructure decisions.

---

## Latency Budgets

| Operation | Target | Strategy |
|-----------|--------|----------|
| Product list (50 products) | < 100ms | Redis cache, sparse fields, pagination |
| Product detail (all values) | < 200ms | Eager loading, cache, denormalization |
| PQL query (simple) | < 50ms | FULLTEXT index, Redis query cache |
| PQL query (FUZZY) | < 200ms | FULLTEXT pre-filter + PHP fuzzy on subset |
| Hierarchy tree (complete) | < 150ms | Materialized path, Redis tree cache |
| Hierarchy node open | < 50ms | Lazy-load children |
| Save attribute values | < 300ms | Bulk upsert, async cache invalidation |
| Export (1000 products) | < 5s | Streaming JSON, queue |
| PDF Preview | < 500ms | Client-side rendering, data preloaded |

---

## Redis Cache

### Cache Layers

| Key Pattern | TTL | Invalidation | Content |
|------------|-----|--------------|---------|
| `product:{id}:full` | 1h | Product change | Product + all values |
| `product:{id}:lang:{lang}` | 1h | Value change | Language-specific |
| `hierarchy:{id}:tree` | 6h | Tree change | Complete tree JSON |
| `hierarchy:{id}:node:{nid}:attrs` | 6h | Assignment change | Attributes incl. inherited |
| `pql:hash:{sha256}` | 15min | TTL-based | PQL query result |
| `products:list:hash:{params}` | 5min | TTL-based | Product list with filters |
| `attributes:all` | 1h | Attribute change | All definitions |
| `export:mapping:{id}:product:{pid}` | 30min | Product/mapping change | Export dataset |

### Invalidation

```php
// Event-based via Model Observer
class ProductObserver {
    public function updated(Product $product) {
        Cache::tags(['product:' . $product->id])->flush();
        // Async: update search index
        dispatch(new UpdateSearchIndex($product->id));
    }
}

// Tag-based: selective
Cache::tags(['product:uuid-123'])->flush();   // Only this product
Cache::tags(['hierarchy:uuid-456'])->flush(); // Only this tree

// Queue-based after bulk operations (import)
dispatch(new WarmupCache($importedProductIds))->afterCommit();
```

---

## Database Optimization

### Denormalized Search Index

```sql
CREATE TABLE products_search_index (
  product_id CHAR(36) PRIMARY KEY,
  sku VARCHAR(100), ean VARCHAR(20),
  product_type VARCHAR(50),
  status ENUM('draft','active','inactive','discontinued'),
  name_de VARCHAR(500), name_en VARCHAR(500),
  description_de TEXT,
  hierarchy_path VARCHAR(1000),
  primary_image VARCHAR(500),
  list_price DECIMAL(12,2),
  attribute_completeness TINYINT,
  phonetic_name_de VARCHAR(100),       -- Cologne phonetics for SOUNDS_LIKE
  updated_at TIMESTAMP,
  FULLTEXT idx_ft_name (name_de, name_en),
  FULLTEXT idx_ft_desc (description_de),
  INDEX idx_status (status),
  INDEX idx_type (product_type),
  INDEX idx_sku (sku),
  INDEX idx_price (list_price)
);
```

PQL queries run primarily against this table instead of the EAV tables. Updated automatically via events.

### Materialized Path (Hierarchies)

```sql
-- Columns on hierarchy_nodes:
path VARCHAR(1000)    -- e.g. "/node-1/node-2/node-3/"
depth INT             -- 0 = root

-- All children (any depth): O(1)!
SELECT * FROM hierarchy_nodes WHERE path LIKE '/node-1/node-2/%' ORDER BY path;

-- All ancestors:
SELECT * FROM hierarchy_nodes WHERE '/node-1/node-2/node-3/' LIKE CONCAT(path, '%');
```

### Indexes (EAV Table)

```sql
-- product_attribute_values
UNIQUE (product_id, attribute_id, language, multiplied_index)
INDEX (product_id, attribute_id)
INDEX (attribute_id, value_string(100))
INDEX (product_id, language)

-- products
UNIQUE (sku)
INDEX (ean)
INDEX (master_hierarchy_node_id)
INDEX (status)
FULLTEXT (name)
```

### MySQL Configuration

```ini
innodb_buffer_pool_size = 70% RAM
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2    # Performance > durability for dev
query_cache_type = 0                   # Redis takes over
ft_min_word_len = 2                    # Allow short search terms
```

---

## Frontend Performance

| Pattern | Implementation | Effect |
|---------|---------------|--------|
| Virtual Scrolling | vue-virtual-scroller | 1000+ rows, only render visible |
| Lazy Loading | Hierarchy children on expand | Initial < 50 nodes |
| Debounce | 250ms on search/filter | No calls per keystroke |
| Optimistic Updates | Immediate UI, API in background | Perceived 0ms |
| Skeleton Loading | Animated placeholders | Perceived faster |
| SWR | Stale-While-Revalidate | Instant navigation |
| Code Splitting | Vite route-based chunks | Initial < 200KB gzip |
| Web Worker | Client PQL filter | UI stays responsive |
| PDF Client Rendering | No server roundtrip | Layout immediately |

---

## Infrastructure

| Component | Recommendation | Scaling |
|-----------|---------------|---------|
| PHP | 8.3 + OPcache + JIT | Horizontal (LB) |
| MySQL | 8.0+ InnoDB, buffer pool = 70% RAM | Read replicas |
| Redis | 7+ Cluster | Separated: cache + queue + session |
| Queue | Laravel Horizon, 4-8 workers | Auto-scale by queue length |
| Media | S3-compatible + CDN | CloudFront / Bunny |
| Frontend | Static hosting | Global CDN |

---

## Monitoring

```
- Response time p50, p95, p99 per endpoint
- Redis hit rate (target: > 85%)
- MySQL slow query log (> 100ms)
- Queue length and processing time
- products_search_index sync lag
```
