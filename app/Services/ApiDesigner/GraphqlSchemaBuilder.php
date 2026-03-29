<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;

/**
 * Baut ein GraphQL-Schema dynamisch aus der template_json Struktur.
 *
 * Die template_json Gruppen-/Element-Hierarchie wird direkt auf GraphQL-Typen gemappt:
 * - groups[] → GroupLevel{N}Type mit field, value, header, products, footer, count
 * - detail.elements[] → ProductType-Felder mit dataType-Mapping auf GraphQL-Scalars
 * - header/footer.elements[] → ObjectTypes mit String/Int-Feldern
 */
class GraphqlSchemaBuilder
{
    /**
     * Baut ein vollständiges GraphQL-Schema aus template_json.
     */
    public function build(array $templateJson, string $language): Schema
    {
        $groups = $templateJson['groups'] ?? [];

        if (empty($groups)) {
            return $this->buildEmptySchema();
        }

        $groupType = $this->buildGroupType($groups, 1);

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'generated_at' => ['type' => Type::string()],
                'total' => ['type' => Type::int()],
                'groups' => [
                    'type' => Type::listOf($groupType),
                ],
            ],
        ]);

        return new Schema(['query' => $queryType]);
    }

    /**
     * Gibt das Schema als SDL-String zurück (für die Frontend-Vorschau).
     */
    public function toSdl(array $templateJson, string $language): string
    {
        $schema = $this->build($templateJson, $language);

        return SchemaPrinter::doPrint($schema);
    }

    /**
     * Generiert eine Beispiel-GraphQL-Query aus der Template-Struktur.
     */
    public function buildSampleQuery(array $templateJson): string
    {
        $groups = $templateJson['groups'] ?? [];
        $indent = '  ';

        $lines = ['{', "{$indent}generated_at", "{$indent}total"];

        if (!empty($groups)) {
            $groupLines = $this->buildSampleQueryForGroup($groups, 1, $indent);
            $lines[] = "{$indent}groups {";
            foreach ($groupLines as $line) {
                $lines[] = "{$indent}{$indent}{$line}";
            }
            $lines[] = "{$indent}}";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Baut einen GroupType für eine bestimmte Verschachtelungsebene.
     * Max 3 Ebenen (GroupLevel1, GroupLevel2, GroupLevel3).
     */
    private function buildGroupType(array $groupDefs, int $level): ObjectType
    {
        $groupDef = $groupDefs[0] ?? [];

        $fields = [
            'field' => ['type' => Type::string()],
            'value' => ['type' => Type::string()],
            'count' => ['type' => Type::int()],
        ];

        // Header-Felder
        $headerElements = $groupDef['header']['elements'] ?? [];
        if (!empty($headerElements)) {
            $fields['header'] = [
                'type' => $this->buildSectionType("HeaderLevel{$level}", $headerElements),
            ];
        }

        // Produkt-Felder (detail section)
        $detailElements = $groupDef['detail']['elements'] ?? [];
        $productType = $this->buildProductType($detailElements, $level);
        $fields['products'] = ['type' => Type::listOf($productType)];

        // Footer-Felder
        $footerElements = $groupDef['footer']['elements'] ?? [];
        if (!empty($footerElements)) {
            $fields['footer'] = [
                'type' => $this->buildSectionType("FooterLevel{$level}", $footerElements),
            ];
        }

        // Verschachtelte Untergruppen (max 3 Ebenen)
        $subGroups = $groupDef['groups'] ?? [];
        if (!empty($subGroups) && $level < 3) {
            $subGroupType = $this->buildGroupType($subGroups, $level + 1);
            $fields['groups'] = ['type' => Type::listOf($subGroupType)];
        }

        return new ObjectType([
            'name' => "GroupLevel{$level}",
            'fields' => $fields,
        ]);
    }

    /**
     * Baut den ProductType aus den detail.elements.
     */
    private function buildProductType(array $elements, int $level): ObjectType
    {
        $fields = [];

        if (empty($elements)) {
            // Fallback: Standard-Felder wenn keine Elemente definiert
            $fields = [
                'sku' => ['type' => Type::string()],
                'name' => ['type' => Type::string()],
                'status' => ['type' => Type::string()],
            ];
        } else {
            foreach ($elements as $element) {
                $key = $element['jsonKey'] ?? $element['field'] ?? $element['attributeId'] ?? null;
                if (!$key) {
                    continue;
                }

                $fieldName = $this->sanitizeFieldName($key);
                $fields[$fieldName] = [
                    'type' => $this->mapDataType($element['dataType'] ?? 'string'),
                ];

                // Resolver für umbenannte Felder (jsonKey enthält Sonderzeichen)
                if ($fieldName !== $key) {
                    $fields[$fieldName]['resolve'] = fn (array $root) => $root[$key] ?? null;
                }
            }
        }

        // Sicherheitscheck: mindestens ein Feld
        if (empty($fields)) {
            $fields['_empty'] = ['type' => Type::string()];
        }

        $typeName = $level === 1 ? 'Product' : "ProductLevel{$level}";

        return new ObjectType([
            'name' => $typeName,
            'fields' => $fields,
        ]);
    }

    /**
     * Baut einen ObjectType für Header/Footer-Sektionen.
     */
    private function buildSectionType(string $name, array $elements): ObjectType
    {
        $fields = [];

        foreach ($elements as $element) {
            $key = $element['jsonKey'] ?? $element['field'] ?? $element['type'] ?? 'value';
            $fieldName = $this->sanitizeFieldName($key);
            $type = $element['type'] ?? 'text';

            $graphqlType = $type === 'counter' ? Type::int() : Type::string();
            $fields[$fieldName] = ['type' => $graphqlType];

            if ($fieldName !== $key) {
                $fields[$fieldName]['resolve'] = fn (array $root) => $root[$key] ?? null;
            }
        }

        if (empty($fields)) {
            $fields['_empty'] = ['type' => Type::string()];
        }

        return new ObjectType([
            'name' => $name,
            'fields' => $fields,
        ]);
    }

    /**
     * Baut ein minimales Schema wenn keine Gruppen definiert sind.
     */
    private function buildEmptySchema(): Schema
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'generated_at' => ['type' => Type::string()],
                'total' => ['type' => Type::int()],
                'groups' => [
                    'type' => Type::listOf(new ObjectType([
                        'name' => 'Group',
                        'fields' => [
                            'count' => ['type' => Type::int()],
                            'products' => [
                                'type' => Type::listOf(new ObjectType([
                                    'name' => 'Product',
                                    'fields' => [
                                        'sku' => ['type' => Type::string()],
                                        'name' => ['type' => Type::string()],
                                        'status' => ['type' => Type::string()],
                                    ],
                                ])),
                            ],
                        ],
                    ])),
                ],
            ],
        ]);

        return new Schema(['query' => $queryType]);
    }

    /**
     * Mappt template_json dataType auf GraphQL-Scalar-Typen.
     */
    private function mapDataType(string $dataType): Type
    {
        return match ($dataType) {
            'number' => Type::float(),
            'integer' => Type::int(),
            'boolean' => Type::boolean(),
            default => Type::string(),
        };
    }

    /**
     * Sanitiert Feldnamen für GraphQL (nur [_a-zA-Z][_a-zA-Z0-9]*).
     */
    private function sanitizeFieldName(string $name): string
    {
        // Bindestriche und Punkte durch Unterstriche ersetzen
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);

        // Darf nicht mit Zahl beginnen
        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = '_' . $sanitized;
        }

        // Leerer String abfangen
        if ($sanitized === '' || $sanitized === '_') {
            $sanitized = '_field';
        }

        return $sanitized;
    }

    /**
     * Baut die Sample-Query-Zeilen für eine Gruppen-Ebene.
     */
    private function buildSampleQueryForGroup(array $groupDefs, int $level, string $indent): array
    {
        $groupDef = $groupDefs[0] ?? [];
        $lines = ['field', 'value'];

        // Header
        $headerElements = $groupDef['header']['elements'] ?? [];
        if (!empty($headerElements)) {
            $lines[] = 'header {';
            foreach ($headerElements as $el) {
                $key = $el['jsonKey'] ?? $el['field'] ?? $el['type'] ?? 'value';
                $lines[] = "  " . $this->sanitizeFieldName($key);
            }
            $lines[] = '}';
        }

        // Products
        $detailElements = $groupDef['detail']['elements'] ?? [];
        $lines[] = 'products {';
        if (empty($detailElements)) {
            $lines[] = '  sku';
            $lines[] = '  name';
            $lines[] = '  status';
        } else {
            foreach ($detailElements as $el) {
                $key = $el['jsonKey'] ?? $el['field'] ?? $el['attributeId'] ?? null;
                if ($key) {
                    $lines[] = '  ' . $this->sanitizeFieldName($key);
                }
            }
        }
        $lines[] = '}';

        // Footer
        $footerElements = $groupDef['footer']['elements'] ?? [];
        if (!empty($footerElements)) {
            $lines[] = 'footer {';
            foreach ($footerElements as $el) {
                $key = $el['jsonKey'] ?? $el['field'] ?? $el['type'] ?? 'value';
                $lines[] = '  ' . $this->sanitizeFieldName($key);
            }
            $lines[] = '}';
        }

        // Subgroups
        $subGroups = $groupDef['groups'] ?? [];
        if (!empty($subGroups) && $level < 3) {
            $subLines = $this->buildSampleQueryForGroup($subGroups, $level + 1, $indent);
            $lines[] = 'groups {';
            foreach ($subLines as $line) {
                $lines[] = "  {$line}";
            }
            $lines[] = '}';
        }

        $lines[] = 'count';

        return $lines;
    }
}
