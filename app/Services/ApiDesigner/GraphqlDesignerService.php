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
     * Führt eine GraphQL-Query oder -Mutation gegen das Template-Schema aus.
     *
     * Bei Mutations (direction = import/bidirectional) wird die Datenpipeline
     * übersprungen — Mutation-Resolver bekommen ihre Daten direkt aus den Args.
     */
    public function execute(ApiTemplate $template, string $query, ?array $variables = null): array
    {
        $direction = $template->direction ?? 'export';
        $isMutation = (bool) preg_match('/^\s*mutation\b/i', $query);

        Log::channel('export')->info("GraphQL-" . ($isMutation ? 'Mutation' : 'Query') . " gestartet: {$template->name}", [
            'template_id' => $template->id,
            'slug' => $template->slug,
        ]);

        $schema = $this->schemaBuilder->build(
            $template->template_json,
            $template->language ?? 'de',
            $direction,
        );

        // Bei Mutations brauchen wir keine Produktdaten zu laden
        if ($isMutation) {
            $rootValue = [];
        } else {
            $searchProfile = $template->searchProfile;
            $data = $this->dataCollector->collect($template, $searchProfile);

            $rootValue = $this->jsonWriter->build(
                $data['grouped'],
                $template->template_json,
                $template->language ?? 'de',
            );
        }

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            rootValue: $rootValue,
            variableValues: $variables,
        );

        Log::channel('export')->info("GraphQL-" . ($isMutation ? 'Mutation' : 'Query') . " abgeschlossen: {$template->name}", [
            'template_id' => $template->id,
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
     * Schema-SDL + Sample-Query (+ Sample-Mutation bei Import) für die Frontend-Vorschau.
     */
    public function schemaPreview(ApiTemplate $template): array
    {
        $direction = $template->direction ?? 'export';

        $result = [
            'sdl' => $this->schemaBuilder->toSdl(
                $template->template_json,
                $template->language ?? 'de',
                $direction,
            ),
            'sample_query' => $this->schemaBuilder->buildSampleQuery($template->template_json),
        ];

        if (in_array($direction, ['import', 'bidirectional'])) {
            $result['sample_mutation'] = $this->schemaBuilder->buildSampleMutation();
        }

        return $result;
    }
}
