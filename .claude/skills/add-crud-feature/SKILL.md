---
name: add-crud-feature
description: Vollständiges CRUD API-Feature anlegen (Controller, Request, Resource, Routes, Frontend)
disable-model-invocation: true
---

# CRUD API-Feature anlegen

Erstelle ein vollständiges CRUD-Feature nach den anyPIM-Konventionen. Ersetze `{Entity}` (PascalCase), `{entity}` (camelCase), `{entities}` (kebab-case Plural).

## Schritt 1: Anforderungen klären

Frage den Nutzer:
1. **Entity-Name** (z.B. "PriceRegion", "Supplier")
2. **Felder** mit Datentypen und Validierung
3. **Beziehungen** (belongsTo, hasMany, etc.)
4. **Verschachtelt unter?** (z.B. Units unter UnitGroups → `unit-groups.units`)
5. **Frontend nötig?** (View + Store + API Client)

## Schritt 2: Backend erstellen

### 2a. Model — `app/Models/{Entity}.php`

```php
<?php
declare(strict_types=1);
namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class {Entity} extends Model
{
    use HasUuids;

    protected $fillable = [...];
    protected $casts = [...];

    // Relationships
}
```

### 2b. Migration — `database/migrations/..._create_{entities}_table.php`

- UUID primary key: `$table->uuid('id')->primary()`
- Timestamps: `$table->timestamps()`
- Foreign Keys mit `->constrained()->cascadeOnDelete()` oder `->nullOnDelete()`

### 2c. Controller — `app/Http/Controllers/Api/V1/{Entity}Controller.php`

Konventionen:
- `index()`: Pagination via `$query->paginate()`, Sorting via `applySorting()`
- `store()`: Validated Request, return 201
- `show()`: Single Resource
- `update()`: `$model->update()` + `$model->fresh()`
- `destroy()`: Gate-Check + `$model->delete()`
- `dependencies()`: Löschbarkeits-Check (wenn nötig)
- Authorization: `$this->authorize('viewAny', {Entity}::class)` etc.

### 2d. Requests — `app/Http/Requests/Api/V1/`

- `Store{Entity}Request.php`: Alle Felder `required` oder `nullable`
- `Update{Entity}Request.php`: Felder mit `sometimes`, Unique-Rules mit `->ignore()`

### 2e. Resource — `app/Http/Resources/Api/V1/{Entity}Resource.php`

- Flat key-value Mapping
- `whenLoaded()` für Relationships (vermeidet N+1)
- Timestamps inkludieren

### 2f. Routes — `routes/api.php`

```php
Route::apiResource('{entities}', {Entity}Controller::class);
// Optional: Nested
Route::apiResource('{parents}.{entities}', {Entity}Controller::class)->shallow();
// Optional: Dependencies
Route::get('{entities}/{entity}/dependencies', [{Entity}Controller::class, 'dependencies']);
```

## Schritt 3: Frontend erstellen (falls gewünscht)

### 3a. API Client — `pim-frontend/src/api/{entities}.js`

```javascript
import client from './client'
export const {entities} = {
    list(params) { return client.get('/{entities}', { params }) },
    get(id) { return client.get(`/{entities}/${id}`) },
    create(data) { return client.post('/{entities}', data) },
    update(id, data) { return client.put(`/{entities}/${id}`, data) },
    delete(id) { return client.delete(`/{entities}/${id}`) },
}
```

### 3b. Pinia Store — `pim-frontend/src/stores/{entities}.js`

Konventionen:
- `defineStore('{entities}', () => { ... })` (Setup-Syntax)
- State: `items`, `current`, `loading`, `error`, `meta`, `filters`, `sort`, `search`
- Actions: `fetchList()`, `fetchOne()`, `create()`, `update()`, `remove()`
- Error-Handling: `error.value = err.response?.data?.title`

### 3c. View — `pim-frontend/src/views/{entities}/`

- `{Entity}ListView.vue` — Tabelle mit Pagination, Suche, Sortierung
- `{Entity}DetailView.vue` — Formular für Create/Edit

## Schritt 4: Checkliste

- [ ] `declare(strict_types=1)` in allen PHP-Dateien
- [ ] UUID Primary Keys (HasUuids Trait)
- [ ] Gate-Authorization in allen Controller-Methoden
- [ ] Separate Store/Update Requests
- [ ] `whenLoaded()` in Resource für Relationships
- [ ] Pagination + Sorting im index()
- [ ] Frontend API Client nutzt `client.js` (nicht axios direkt)
- [ ] Pinia Store mit Error-Handling
- [ ] Route-Namen: kebab-case
