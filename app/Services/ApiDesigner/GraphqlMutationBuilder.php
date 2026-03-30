<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Baut GraphQL Input-Types und den Mutation-Root-Type für den API Designer.
 *
 * Ermöglicht externe Systeme (z.B. PIMCORE) Produktdaten per GraphQL-Mutations
 * in anyPIM zu übertragen: createProduct, updateProduct, deleteProduct, importProducts.
 */
class GraphqlMutationBuilder
{
    private ?InputObjectType $attributeInputType = null;
    private ?InputObjectType $priceInputType = null;
    private ?InputObjectType $mediaInputType = null;
    private ?ObjectType $mutationProductType = null;
    private ?ObjectType $mutationResultType = null;
    private ?ObjectType $batchResultType = null;

    /**
     * Baut den kompletten Mutation-Root-Type mit allen Resolve-Funktionen.
     */
    public function build(): ObjectType
    {
        $resolver = app(GraphqlMutationResolver::class);

        $productInputType = $this->buildProductInputType();
        $productUpdateInputType = $this->buildProductUpdateInputType();
        $mutationResultType = $this->getMutationResultType();
        $batchResultType = $this->getBatchResultType();

        return new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'createProduct' => [
                    'type' => $mutationResultType,
                    'args' => [
                        'input' => ['type' => Type::nonNull($productInputType)],
                    ],
                    'resolve' => fn ($root, array $args) => $resolver->resolveCreateProduct($args['input']),
                ],
                'updateProduct' => [
                    'type' => $mutationResultType,
                    'args' => [
                        'id' => ['type' => Type::string()],
                        'sku' => ['type' => Type::string()],
                        'input' => ['type' => Type::nonNull($productUpdateInputType)],
                    ],
                    'resolve' => fn ($root, array $args) => $resolver->resolveUpdateProduct(
                        $args['id'] ?? null,
                        $args['sku'] ?? null,
                        $args['input'],
                    ),
                ],
                'deleteProduct' => [
                    'type' => $mutationResultType,
                    'args' => [
                        'id' => ['type' => Type::string()],
                        'sku' => ['type' => Type::string()],
                    ],
                    'resolve' => fn ($root, array $args) => $resolver->resolveDeleteProduct(
                        $args['id'] ?? null,
                        $args['sku'] ?? null,
                    ),
                ],
                'importProducts' => [
                    'type' => $batchResultType,
                    'args' => [
                        'products' => ['type' => Type::nonNull(Type::listOf(Type::nonNull($productInputType)))],
                    ],
                    'resolve' => fn ($root, array $args) => $resolver->resolveImportProducts($args['products']),
                ],
            ],
        ]);
    }

    /**
     * Generiert eine Beispiel-Mutation für die Frontend-Vorschau.
     */
    public function buildSampleMutation(): string
    {
        return <<<'GRAPHQL'
mutation {
  createProduct(input: {
    sku: "PROD-001"
    name: "Beispielprodukt"
    product_type_id: "<product-type-uuid>"
    status: "draft"
    attributes: [
      { technical_name: "farbe", value: "rot", language: "de" }
      { technical_name: "gewicht", value: "1.5" }
    ]
    prices: [
      { price_type_id: "<price-type-uuid>", amount: 29.99, currency: "EUR" }
    ]
  }) {
    success
    message
    product {
      id
      sku
      name
      status
    }
    errors
  }
}
GRAPHQL;
    }

    /**
     * Generiert eine Beispiel-Batch-Mutation.
     */
    public function buildSampleBatchMutation(): string
    {
        return <<<'GRAPHQL'
mutation {
  importProducts(products: [
    {
      sku: "PROD-001"
      name: "Produkt A"
      product_type_id: "<product-type-uuid>"
      attributes: [
        { technical_name: "farbe", value: "rot", language: "de" }
      ]
    }
    {
      sku: "PROD-002"
      name: "Produkt B"
      product_type_id: "<product-type-uuid>"
    }
  ]) {
    total
    created
    updated
    failed
    results {
      success
      message
      product { sku name }
      errors
    }
  }
}
GRAPHQL;
    }

    private function buildProductInputType(): InputObjectType
    {
        return new InputObjectType([
            'name' => 'ProductInput',
            'fields' => [
                'sku' => ['type' => Type::nonNull(Type::string())],
                'name' => ['type' => Type::nonNull(Type::string())],
                'product_type_id' => ['type' => Type::nonNull(Type::string())],
                'ean' => ['type' => Type::string()],
                'status' => ['type' => Type::string()],
                'master_hierarchy_node_id' => ['type' => Type::string()],
                'manufacturer_id' => ['type' => Type::string()],
                'attributes' => ['type' => Type::listOf($this->getAttributeInputType())],
                'prices' => ['type' => Type::listOf($this->getPriceInputType())],
                'media' => ['type' => Type::listOf($this->getMediaInputType())],
            ],
        ]);
    }

    private function buildProductUpdateInputType(): InputObjectType
    {
        return new InputObjectType([
            'name' => 'ProductUpdateInput',
            'fields' => [
                'name' => ['type' => Type::string()],
                'ean' => ['type' => Type::string()],
                'status' => ['type' => Type::string()],
                'product_type_id' => ['type' => Type::string()],
                'master_hierarchy_node_id' => ['type' => Type::string()],
                'manufacturer_id' => ['type' => Type::string()],
                'attributes' => ['type' => Type::listOf($this->getAttributeInputType())],
                'prices' => ['type' => Type::listOf($this->getPriceInputType())],
                'media' => ['type' => Type::listOf($this->getMediaInputType())],
            ],
        ]);
    }

    private function getAttributeInputType(): InputObjectType
    {
        if ($this->attributeInputType === null) {
            $this->attributeInputType = new InputObjectType([
                'name' => 'AttributeInput',
                'fields' => [
                    'technical_name' => ['type' => Type::nonNull(Type::string())],
                    'value' => ['type' => Type::string()],
                    'language' => ['type' => Type::string()],
                ],
            ]);
        }

        return $this->attributeInputType;
    }

    private function getPriceInputType(): InputObjectType
    {
        if ($this->priceInputType === null) {
            $this->priceInputType = new InputObjectType([
                'name' => 'PriceInput',
                'fields' => [
                    'price_type_id' => ['type' => Type::nonNull(Type::string())],
                    'amount' => ['type' => Type::nonNull(Type::float())],
                    'currency' => ['type' => Type::nonNull(Type::string())],
                    'valid_from' => ['type' => Type::string()],
                    'valid_to' => ['type' => Type::string()],
                    'price_region_id' => ['type' => Type::string()],
                    'scale_from' => ['type' => Type::int()],
                    'scale_to' => ['type' => Type::int()],
                ],
            ]);
        }

        return $this->priceInputType;
    }

    private function getMediaInputType(): InputObjectType
    {
        if ($this->mediaInputType === null) {
            $this->mediaInputType = new InputObjectType([
                'name' => 'MediaInput',
                'fields' => [
                    'media_id' => ['type' => Type::nonNull(Type::string())],
                    'usage_type_id' => ['type' => Type::string()],
                    'sort_order' => ['type' => Type::int()],
                    'is_primary' => ['type' => Type::boolean()],
                ],
            ]);
        }

        return $this->mediaInputType;
    }

    /**
     * Fixer Product-Type für Mutation-Ergebnisse (unabhängig von template_json).
     */
    private function getMutationProductType(): ObjectType
    {
        if ($this->mutationProductType === null) {
            $this->mutationProductType = new ObjectType([
                'name' => 'MutationProduct',
                'fields' => [
                    'id' => ['type' => Type::string()],
                    'sku' => ['type' => Type::string()],
                    'name' => ['type' => Type::string()],
                    'ean' => ['type' => Type::string()],
                    'status' => ['type' => Type::string()],
                    'product_type_id' => ['type' => Type::string()],
                    'master_hierarchy_node_id' => ['type' => Type::string()],
                    'manufacturer_id' => ['type' => Type::string()],
                    'created_at' => ['type' => Type::string()],
                    'updated_at' => ['type' => Type::string()],
                ],
            ]);
        }

        return $this->mutationProductType;
    }

    private function getMutationResultType(): ObjectType
    {
        if ($this->mutationResultType === null) {
            $this->mutationResultType = new ObjectType([
                'name' => 'MutationResult',
                'fields' => [
                    'success' => ['type' => Type::nonNull(Type::boolean())],
                    'message' => ['type' => Type::string()],
                    'product' => ['type' => $this->getMutationProductType()],
                    'errors' => ['type' => Type::listOf(Type::string())],
                ],
            ]);
        }

        return $this->mutationResultType;
    }

    private function getBatchResultType(): ObjectType
    {
        if ($this->batchResultType === null) {
            $this->batchResultType = new ObjectType([
                'name' => 'BatchResult',
                'fields' => [
                    'total' => ['type' => Type::nonNull(Type::int())],
                    'created' => ['type' => Type::nonNull(Type::int())],
                    'updated' => ['type' => Type::nonNull(Type::int())],
                    'failed' => ['type' => Type::nonNull(Type::int())],
                    'results' => ['type' => Type::listOf($this->getMutationResultType())],
                ],
            ]);
        }

        return $this->batchResultType;
    }
}
