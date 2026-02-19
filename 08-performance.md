# Publixx PIM — Performance-Architektur

> **Zweck:** Das System muss rasend schnell sein. Verwende diesen Skill bei Optimierung, Caching, Indizierung und Infrastruktur-Entscheidungen.

---

## Latenz-Budgets

| Operation | Ziel | Strategie |
|-----------|------|-----------|
| Produktliste (50 Produkte) | < 100ms | Redis Cache, Sparse Fields, Paginierung |
| Produktdetail (alle Werte) | < 200ms | Eager Loading, Cache, Denormalisierung |
| PQL-Query (einfach) | < 50ms | FULLTEXT Index, Redis Query-Cache |
| PQL-Query (FUZZY) | < 200ms | FULLTEXT Vorfilter + PHP Fuzzy auf Subset |
| Hierarchie-Baum (komplett) | < 150ms | Materialized Path, Redis Baum-Cache |
| Hierarchie-Knoten öffnen | < 50ms | Lazy-Load Kinder |
| Attributwerte speichern | < 300ms | Bulk-Upsert, Async Cache-Invalidierung |
| Export (1000 Produkte) | < 5s | Streaming JSON, Queue |
| PXF Preview | < 500ms | Client-Side Rendering, Daten vorgeladen |

---

## Redis-Cache

### Cache-Schichten

| Key-Pattern | TTL | Invalidierung | Inhalt |
|------------|-----|---------------|--------|
| `product:{id}:full` | 1h | Produktänderung | Produkt + alle Werte |
| `product:{id}:lang:{lang}` | 1h | Wertänderung | Sprachspezifisch |
| `hierarchy:{id}:tree` | 6h | Baumänderung | Vollständiger Baum JSON |
| `hierarchy:{id}:node:{nid}:attrs` | 6h | Zuordnungsänderung | Attribute inkl. vererbte |
| `pql:hash:{sha256}` | 15min | TTL-basiert | PQL-Query-Ergebnis |
| `products:list:hash:{params}` | 5min | TTL-basiert | Produktliste mit Filtern |
| `attributes:all` | 1h | Attributänderung | Alle Definitionen |
| `export:mapping:{id}:product:{pid}` | 30min | Produkt-/Mappingänderung | Export-Dataset |

### Invalidierung

```php
// Event-basiert via Model Observer
class ProductObserver {
    public function updated(Product $product) {
        Cache::tags(['product:' . $product->id])->flush();
        // Async: Search-Index aktualisieren
        dispatch(new UpdateSearchIndex($product->id));
    }
}

// Tag-basiert: selektiv
Cache::tags(['product:uuid-123'])->flush();   // Nur dieses Produkt
Cache::tags(['hierarchy:uuid-456'])->flush(); // Nur dieser Baum

// Queue-basiert nach Massenoperationen (Import)
dispatch(new WarmupCache($importedProductIds))->afterCommit();
```

---

## Datenbank-Optimierung

### Denormalisierter Suchindex

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
  phonetic_name_de VARCHAR(100),       -- Kölner Phonetik für SOUNDS_LIKE
  updated_at TIMESTAMP,
  FULLTEXT idx_ft_name (name_de, name_en),
  FULLTEXT idx_ft_desc (description_de),
  INDEX idx_status (status),
  INDEX idx_type (product_type),
  INDEX idx_sku (sku),
  INDEX idx_price (list_price)
);
```

PQL-Queries laufen primär gegen diese Tabelle statt gegen die EAV-Tabellen. Wird über Events automatisch aktualisiert.

### Materialized Path (Hierarchien)

```sql
-- Spalten auf hierarchy_nodes:
path VARCHAR(1000)    -- z.B. "/node-1/node-2/node-3/"
depth INT             -- 0 = Root

-- Alle Kinder (beliebige Tiefe): O(1)!
SELECT * FROM hierarchy_nodes WHERE path LIKE '/node-1/node-2/%' ORDER BY path;

-- Alle Vorfahren:
SELECT * FROM hierarchy_nodes WHERE '/node-1/node-2/node-3/' LIKE CONCAT(path, '%');
```

### Indizes (EAV-Tabelle)

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

### MySQL-Konfiguration

```ini
innodb_buffer_pool_size = 70% RAM
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2    # Performance > Durability für Dev
query_cache_type = 0                   # Redis übernimmt
ft_min_word_len = 2                    # Kurze Suchbegriffe erlauben
```

---

## Frontend-Performance

| Pattern | Implementierung | Effekt |
|---------|----------------|--------|
| Virtuelles Scrolling | vue-virtual-scroller | 1000+ Zeilen, nur sichtbare rendern |
| Lazy Loading | Hierarchie-Kinder bei Expand | Initial < 50 Knoten |
| Debounce | 250ms auf Suche/Filter | Keine Calls pro Tastendruck |
| Optimistic Updates | Sofort UI, API im Hintergrund | Gefühlt 0ms |
| Skeleton Loading | Animierte Platzhalter | Gefühlt schneller |
| SWR | Stale-While-Revalidate | Instant Navigation |
| Code-Splitting | Vite Route-based Chunks | Initial < 200KB gzip |
| Web Worker | Client-PQL-Filter | UI bleibt responsive |
| PXF Client Rendering | Kein Server-Roundtrip | Layout sofort |

---

## Infrastruktur

| Komponente | Empfehlung | Skalierung |
|-----------|-----------|-----------|
| PHP | 8.3 + OPcache + JIT | Horizontal (LB) |
| MySQL | 8.0+ InnoDB, Buffer Pool = 70% RAM | Read-Replicas |
| Redis | 7+ Cluster | Getrennt: Cache + Queue + Session |
| Queue | Laravel Horizon, 4-8 Worker | Auto-Scale nach Queue-Länge |
| Medien | S3-kompatibel + CDN | CloudFront / Bunny |
| Frontend | Static Hosting | Global CDN |

---

## Monitoring

```
- Response Time p50, p95, p99 pro Endpunkt
- Redis Hit-Rate (Ziel: > 85%)
- MySQL Slow Query Log (> 100ms)
- Queue-Länge und Processing-Time
- products_search_index Sync-Lag
```
