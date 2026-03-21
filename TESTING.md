# anyPIM — Teststrategie

## Tech-Stack

| Ebene | Tool | Beschreibung |
|-------|------|-------------|
| Backend Unit/Feature | PHPUnit 11 | Laravel-native Tests mit SQLite in-memory |
| Frontend Unit | Vitest + Vue Test Utils | Vite-native, schnell, jsdom-Umgebung |
| CI/CD | GitHub Actions | Automatische Tests bei Push/PR |
| Mocking (PHP) | Mockery + Http::fake() | Externe APIs mocken |
| Code Style | Laravel Pint + ESLint | Automatische Code-Qualität |

## Tests ausführen

### Backend
```bash
# Alle Tests
vendor/bin/phpunit

# Nur Unit-Tests
vendor/bin/phpunit --testsuite=Unit

# Nur Feature-Tests
vendor/bin/phpunit --testsuite=Feature

# Einzelne Testdatei
vendor/bin/phpunit tests/Feature/Api/AuthControllerTest.php
```

### Frontend
```bash
cd pim-frontend

# Watch-Modus (Entwicklung)
npm test

# Einmalig ausführen (CI)
npm run test:run

# Mit Coverage
npm run test:coverage
```

## Teststruktur

```
tests/
├── Feature/
│   ├── Api/                    # API-Controller-Tests
│   │   ├── AuthControllerTest.php
│   │   ├── ProductControllerTest.php
│   │   ├── AttributeControllerTest.php
│   │   ├── UserControllerTest.php
│   │   ├── HierarchyControllerTest.php
│   │   ├── WorkflowControllerTest.php
│   │   ├── ExportProfileControllerTest.php
│   │   ├── ImportProfileControllerTest.php
│   │   ├── MediaControllerTest.php
│   │   └── PriceTypeControllerTest.php
│   ├── Auth/                   # Authentifizierung & Berechtigungen
│   ├── Import/                 # Import-Engine
│   ├── Export/                 # Export-Engine
│   ├── Inheritance/            # Vererbung
│   ├── Pql/                    # PQL Query Engine
│   ├── Performance/            # Cache & Search
│   └── DeletionConstraints/    # Löschprüfungen
├── Unit/
│   └── Services/
│       ├── CompositeExpressionEvaluatorTest.php
│       ├── DeepLTranslationServiceTest.php
│       ├── ConnectorConnectionTest.php
│       └── ExportProfileScopeTest.php
└── fixtures/                   # Testdaten (BMEcat XML)

pim-frontend/src/
├── stores/__tests__/           # Pinia Store-Tests
│   ├── auth.test.js
│   └── locale.test.js
├── composables/__tests__/      # Composable-Tests
│   └── useFilters.test.js
└── utils/__tests__/            # Utility-Tests
    └── formatting.test.js
```

## Konventionen

### Backend
- **Feature-Tests** für API-Endpunkte: `tests/Feature/Api/{Controller}Test.php`
- **Unit-Tests** für isolierte Logik: `tests/Unit/Services/{Service}Test.php`
- Admin-User via `Role::create(['name' => 'Admin'])` + `Sanctum::actingAs()`
- `RefreshDatabase`-Trait in allen DB-Tests
- Externe APIs mit `Http::fake()` mocken
- Löschung immer mit und ohne `?force=true` testen

### Frontend
- Tests neben dem Code: `__tests__/filename.test.js`
- Pinia-Stores mit `createPinia()` + `setActivePinia()` testen
- API-Module mit `vi.mock()` mocken
- localStorage im Setup zurücksetzen
