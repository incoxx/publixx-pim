<?php

declare(strict_types=1);

namespace App\Services\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\SearchProfile;
use GraphQL\GraphQL;
use Illuminate\Support\Facades\Log;

/**
 * Orchestriert GraphQL-Queries gegen dynamisch generierte Schemas.
 *
 * Nutzt dieselbe Datenpipeline wie der JSON-Stream:
 * ApiDataCollector → JsonWriter::build() → Root Value für GraphQL-Resolver
 */
class GraphqlDesignerService
{
    public function __construct(
        private readonly ApiDataCollector $dataCollector,
        private readonly JsonWriter $jsonWriter,
        private readonly GraphqlSchemaBuilder $schemaBuilder,
    ) {}

    /**
     * Führt eine GraphQL-Query gegen das Template-Schema aus.
     */
    public function execute(ApiTemplate $template, string $query, ?array $variables = null): array
    {
        Log::channel('export')->info("GraphQL-Query gestartet: {$template->name}", [
            'template_id' => $template->id,
            'slug' => $template->slug,
        ]);

        $schema = $this->schemaBuilder->build(
            $template->template_json,
            $template->language ?? 'de',
        );

        $searchProfile = $template->searchProfile;

        $data = $this->dataCollector->collect($template, $searchProfile);

        // JsonWriter liefert die perfekte Datenstruktur als Root Value
        $rootValue = $this->jsonWriter->build(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
        );

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            rootValue: $rootValue,
            variableValues: $variables,
        );

        Log::channel('export')->info("GraphQL-Query abgeschlossen: {$template->name}", [
            'template_id' => $template->id,
            'products' => $data['total'],
        ]);

        return $result->toArray();
    }

    /**
     * Vorschau mit begrenzter Produktzahl (für den Designer).
     */
    public function preview(ApiTemplate $template, ?SearchProfile $searchProfile = null, int $limit = 5): array
    {
        $searchProfile = $searchProfile ?? $template->searchProfile;

        $data = $this->dataCollector->collect($template, $searchProfile, $limit);

        $rootValue = $this->jsonWriter->build(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
        );

        $sampleQuery = $this->schemaBuilder->buildSampleQuery($template->template_json);

        $schema = $this->schemaBuilder->build(
            $template->template_json,
            $template->language ?? 'de',
        );

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $sampleQuery,
            rootValue: $rootValue,
        );

        return $result->toArray();
    }

    /**
     * Schema-SDL + Sample-Query für die Frontend-Vorschau.
     */
    public function schemaPreview(ApiTemplate $template): array
    {
        return [
            'sdl' => $this->schemaBuilder->toSdl(
                $template->template_json,
                $template->language ?? 'de',
            ),
            'sample_query' => $this->schemaBuilder->buildSampleQuery($template->template_json),
        ];
    }
}
