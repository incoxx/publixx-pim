<?php

declare(strict_types=1);

namespace App\Services\Pql;

use App\Services\Pql\Ast\ComparisonNode;
use App\Services\Pql\Ast\FuzzyNode;
use App\Services\Pql\Ast\LogicalNode;
use App\Services\Pql\Ast\SearchFieldsNode;
use App\Services\Pql\Ast\SelectNode;
use App\Services\Pql\Ast\SoundsLikeNode;
use App\Services\Pql\Ast\WhereNode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Validates PQL AST fields against the attribute schema.
 *
 * - Resolves field names to attribute definitions
 * - Suggests corrections for typos via Levenshtein distance
 * - Validates operator compatibility with data types
 */
final class PqlValidator
{
    /**
     * Known base fields on products / products_search_index (not EAV).
     */
    private const BASE_FIELDS = [
        'status'      => ['table' => 'products', 'column' => 'status', 'data_type' => 'String'],
        'sku'         => ['table' => 'products', 'column' => 'sku', 'data_type' => 'String'],
        'ean'         => ['table' => 'products', 'column' => 'ean', 'data_type' => 'String'],
        'name'        => ['table' => 'products', 'column' => 'name', 'data_type' => 'String'],
        'hierarchy'   => ['table' => 'products_search_index', 'column' => 'hierarchy_path', 'data_type' => 'String'],
        'product_type' => ['table' => 'products_search_index', 'column' => 'product_type', 'data_type' => 'String'],
        'list_price'  => ['table' => 'products_search_index', 'column' => 'list_price', 'data_type' => 'Number'],
    ];

    /**
     * Numeric operators that only work with Number/Float data types.
     */
    private const NUMERIC_OPERATORS = ['>', '<', '>=', '<=', 'BETWEEN', 'NOT BETWEEN'];

    /**
     * @return array{valid: bool, errors: array<array{position: int, field: string, error: string}>, resolved_fields: array<string, array<string, mixed>>}
     */
    public function validate(SelectNode $ast): array
    {
        $errors = [];
        $resolvedFields = [];
        $availableFields = $this->getAvailableFields();

        // Validate SELECT fields
        if (!$ast->hasWildcardSelect()) {
            foreach ($ast->fields as $field) {
                $resolution = $this->resolveField($field, $availableFields);
                if ($resolution === null) {
                    $errors[] = [
                        'position' => 0,
                        'field' => $field,
                        'error' => $this->buildUnknownFieldError($field, $availableFields),
                    ];
                } else {
                    $resolvedFields[$field] = $resolution;
                }
            }
        }

        // Validate WHERE clause
        if ($ast->hasWhereClause()) {
            $this->validateExpression($ast->where->expression, $availableFields, $errors, $resolvedFields);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'resolved_fields' => $resolvedFields,
        ];
    }

    /**
     * Validate a single expression node recursively.
     *
     * @param array<string, array<string, mixed>> $availableFields
     * @param array<array{position: int, field: string, error: string}> &$errors
     * @param array<string, array<string, mixed>> &$resolvedFields
     */
    private function validateExpression(
        ComparisonNode|LogicalNode|FuzzyNode|SoundsLikeNode|SearchFieldsNode $node,
        array $availableFields,
        array &$errors,
        array &$resolvedFields,
    ): void {
        if ($node instanceof LogicalNode) {
            $this->validateExpression($node->left, $availableFields, $errors, $resolvedFields);
            $this->validateExpression($node->right, $availableFields, $errors, $resolvedFields);
            return;
        }

        if ($node instanceof SearchFieldsNode) {
            foreach ($node->fields as $field => $boost) {
                $this->validateFieldName($field, $availableFields, $errors, $resolvedFields);
            }
            // Also validate the inner condition field (if it references a specific field)
            if ($node->condition instanceof FuzzyNode || $node->condition instanceof SoundsLikeNode) {
                // SEARCH_FIELDS inner condition's field is one of the search fields — already validated
                return;
            }
            if ($node->condition instanceof ComparisonNode) {
                // The field in the condition comes from SEARCH_FIELDS first field — already validated
                return;
            }
            return;
        }

        if ($node instanceof FuzzyNode) {
            $this->validateFieldName($node->field, $availableFields, $errors, $resolvedFields);
            return;
        }

        if ($node instanceof SoundsLikeNode) {
            $this->validateFieldName($node->field, $availableFields, $errors, $resolvedFields);
            return;
        }

        if ($node instanceof ComparisonNode) {
            $this->validateFieldName($node->field, $availableFields, $errors, $resolvedFields);

            // Validate operator/type compatibility
            if (isset($resolvedFields[$node->field]) && in_array($node->operator, self::NUMERIC_OPERATORS, true)) {
                $dataType = $resolvedFields[$node->field]['data_type'] ?? 'String';
                if (!in_array($dataType, ['Number', 'Float'], true)) {
                    $errors[] = [
                        'position' => 0,
                        'field' => $node->field,
                        'error' => "Operator {$node->operator} requires a numeric field, but '{$node->field}' is {$dataType}",
                    ];
                }
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $availableFields
     * @param array<array{position: int, field: string, error: string}> &$errors
     * @param array<string, array<string, mixed>> &$resolvedFields
     */
    private function validateFieldName(
        string $field,
        array $availableFields,
        array &$errors,
        array &$resolvedFields,
    ): void {
        if (isset($resolvedFields[$field])) {
            return; // Already resolved
        }

        $resolution = $this->resolveField($field, $availableFields);
        if ($resolution === null) {
            $errors[] = [
                'position' => 0,
                'field' => $field,
                'error' => $this->buildUnknownFieldError($field, $availableFields),
            ];
        } else {
            $resolvedFields[$field] = $resolution;
        }
    }

    /**
     * Resolve a PQL field name to a database column or EAV attribute.
     *
     * @param array<string, array<string, mixed>> $availableFields
     * @return array<string, mixed>|null
     */
    private function resolveField(string $field, array $availableFields): ?array
    {
        // Check base fields first
        if (isset(self::BASE_FIELDS[$field])) {
            return self::BASE_FIELDS[$field];
        }

        // Check EAV attributes by technical_name
        if (isset($availableFields[$field])) {
            return $availableFields[$field];
        }

        // Dot-notation: specs.weight.value → resolve hierarchical attribute
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $rootField = $parts[0];
            if (isset($availableFields[$rootField])) {
                return [
                    'table' => 'product_attribute_values',
                    'technical_name' => $rootField,
                    'data_type' => $availableFields[$rootField]['data_type'] ?? 'String',
                    'dot_path' => $parts,
                ];
            }
        }

        return null;
    }

    /**
     * Build a helpful error message with typo suggestions.
     *
     * @param array<string, array<string, mixed>> $availableFields
     */
    private function buildUnknownFieldError(string $field, array $availableFields): string
    {
        $allFieldNames = array_merge(
            array_keys(self::BASE_FIELDS),
            array_keys($availableFields),
        );

        $suggestions = $this->findSimilarFields($field, $allFieldNames, 3);

        $msg = "Unbekanntes Feld '{$field}'.";
        if (!empty($suggestions)) {
            $msg .= ' Meinten Sie: ' . implode(', ', $suggestions) . '?';
        } else {
            $msg .= ' Verfügbar: ' . implode(', ', array_slice($allFieldNames, 0, 10));
            if (count($allFieldNames) > 10) {
                $msg .= ' ...';
            }
        }

        return $msg;
    }

    /**
     * Find similar field names using Levenshtein distance.
     *
     * @param array<string> $candidates
     * @return array<string>
     */
    private function findSimilarFields(string $input, array $candidates, int $maxSuggestions): array
    {
        $inputLower = strtolower($input);
        $scored = [];

        foreach ($candidates as $candidate) {
            $candidateLower = strtolower($candidate);
            $distance = levenshtein($inputLower, $candidateLower);
            $maxLen = max(strlen($inputLower), strlen($candidateLower));

            // Only suggest if within 40% edit distance
            if ($maxLen > 0 && ($distance / $maxLen) <= 0.4) {
                $scored[$candidate] = $distance;
            }
        }

        asort($scored);
        return array_slice(array_keys($scored), 0, $maxSuggestions);
    }

    /**
     * Get all available field names from the attribute schema (cached).
     *
     * @return array<string, array<string, mixed>>
     */
    private function getAvailableFields(): array
    {
        return Cache::remember('pql:available_fields', 3600, function (): array {
            $fields = [];

            try {
                $attributes = DB::table('attributes')
                    ->where('status', 'active')
                    ->select(['id', 'technical_name', 'data_type', 'is_searchable'])
                    ->get();

                foreach ($attributes as $attr) {
                    $fields[$attr->technical_name] = [
                        'table' => 'product_attribute_values',
                        'attribute_id' => $attr->id,
                        'technical_name' => $attr->technical_name,
                        'data_type' => $attr->data_type,
                        'is_searchable' => (bool) $attr->is_searchable,
                    ];
                }
            } catch (\Exception) {
                // If DB not available (e.g. during tests), return empty
            }

            return $fields;
        });
    }

    /**
     * Get base fields for external consumers (e.g. explain).
     *
     * @return array<string, array<string, string>>
     */
    public static function getBaseFields(): array
    {
        return self::BASE_FIELDS;
    }
}
