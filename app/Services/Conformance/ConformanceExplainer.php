<?php

declare(strict_types=1);

namespace App\Services\Conformance;

use App\Models\Attribute;
use App\Models\ConnectorConnection;
use App\Models\Product;
use App\Models\ProductConformanceResult;
use Illuminate\Support\Facades\Http;

/**
 * Schritt 2: KI-gestützte Erklärung der Konformitäts-Abweichungen.
 *
 * Die Abweichungen werden NICHT von der KI gefunden, sondern bereits
 * deterministisch vom ProfileConformanceChecker ermittelt. Die KI erhält den
 * fertigen Report (+ Vorbild-Produkte als Kontext) und erklärt die Abweichungen
 * verständlich samt Korrekturvorschlag. Das hält den Aufruf günstig und robust.
 */
class ConformanceExplainer
{
    /**
     * @return array{text: string, model: string, usage: array<string, int>}
     *
     * @throws \RuntimeException wenn kein API-Key konfiguriert ist
     */
    public function explain(Product $product, ProductConformanceResult $result): array
    {
        $apiKey = $this->resolveApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('Kein Claude-AI API-Key konfiguriert.');
        }

        $deviations = $result->deviations ?? [];
        if (empty($deviations)) {
            return ['text' => 'Keine Abweichungen vorhanden – das Produkt ist konform.', 'model' => '', 'usage' => []];
        }

        $prompt = $this->buildPrompt($product, $result, $deviations);

        $model = config('connectors.claude_ai.model', 'claude-sonnet-4-5-20250929');
        $maxTokens = (int) config('connectors.claude_ai.max_tokens', 1024);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post(config('connectors.claude_ai.base_url', 'https://api.anthropic.com/v1') . '/messages', [
                'model' => $model,
                'max_tokens' => max($maxTokens, 1024),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $response->throw();
        $data = $response->json();

        return [
            'text' => $data['content'][0]['text'] ?? '',
            'model' => $model,
            'usage' => [
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            ],
        ];
    }

    /**
     * API-Key: aktive Claude-AI-Connector-Verbindung, sonst Config-Fallback.
     */
    private function resolveApiKey(): string
    {
        $connection = ConnectorConnection::where('connector_type', 'claude_ai')
            ->where('is_active', true)
            ->first();

        return $connection?->access_token ?: (string) config('connectors.claude_ai.api_key', '');
    }

    /**
     * @param array<int, array<string, mixed>> $deviations
     */
    private function buildPrompt(Product $product, ProductConformanceResult $result, array $deviations): string
    {
        $profile = $product->referenceProfile;
        $profileName = $profile?->name ?? 'Referenz-Profil';

        // Lesbare Attribut-Labels (technical_name → name_de)
        $attrNames = array_values(array_unique(array_filter(array_map(
            static fn ($d) => $d['attribute'] ?? null,
            $deviations
        ))));
        $labels = Attribute::whereIn('technical_name', $attrNames)
            ->pluck('name_de', 'technical_name')
            ->toArray();

        $lines = [];
        foreach ($deviations as $d) {
            $tn = $d['attribute'] ?? '?';
            $label = $labels[$tn] ?? $tn;
            $expected = $this->scalarToText($d['expected'] ?? null);
            $actual = $this->scalarToText($d['actual'] ?? null);
            $unit = !empty($d['unit']) ? ' ' . $d['unit'] : '';
            $lines[] = sprintf(
                '- %s (%s) [%s]: Regel "%s", erwartet: %s%s, vorhanden: %s',
                $label,
                $tn,
                $d['severity'] ?? 'error',
                $d['check'] ?? '',
                $expected,
                $unit,
                $actual
            );
        }
        $deviationText = implode("\n", $lines);

        // Vorbild-Werte aus Golden Samples (nur für betroffene Attribute)
        $goldenText = $this->goldenSampleContext($profile, $attrNames);

        return <<<PROMPT
        Du bist ein Assistent für PIM-Datenqualität. Ein Produkt wurde regelbasiert gegen das
        Referenz-Profil "{$profileName}" geprüft. Die folgenden Abweichungen wurden bereits
        ermittelt – finde sie NICHT neu, sondern erkläre sie verständlich und schlage konkrete,
        umsetzbare Korrekturen vor. Antworte auf Deutsch, knapp und in Markdown. Pro Abweichung
        ein kurzer Absatz: Warum ist das relevant, und welcher Wert/welche Korrektur ist sinnvoll.

        Produkt: {$product->name} (SKU: {$product->sku})
        Status: {$result->status}, Score: {$result->score}%

        Abweichungen:
        {$deviationText}
        {$goldenText}

        Gib am Ende eine kurze Priorisierung (zuerst Fehler, dann Warnungen).
        PROMPT;
    }

    /**
     * Typische Werte aus den Vorbild-Produkten für die betroffenen Attribute.
     *
     * @param array<int, string> $attrNames
     */
    private function goldenSampleContext(?object $profile, array $attrNames): string
    {
        $goldenIds = $profile?->golden_product_ids ?? [];
        if (empty($goldenIds) || empty($attrNames)) {
            return '';
        }

        $samples = Product::whereIn('id', $goldenIds)
            ->with(['attributeValues' => function ($q) use ($attrNames) {
                $q->whereHas('attribute', fn ($a) => $a->whereIn('technical_name', $attrNames))
                    ->with(['attribute:id,technical_name', 'valueListEntry:id,technical_name']);
            }])
            ->get();

        $byAttr = [];
        foreach ($samples as $sample) {
            foreach ($sample->attributeValues as $pav) {
                $tn = $pav->attribute?->technical_name;
                if ($tn === null) {
                    continue;
                }
                $val = $pav->value_number
                    ?? ($pav->value_flag !== null ? ($pav->value_flag ? 'ja' : 'nein') : null)
                    ?? $pav->valueListEntry?->technical_name
                    ?? $pav->value_string;
                if ($val !== null && $val !== '') {
                    $byAttr[$tn][(string) $val] = true;
                }
            }
        }

        if (empty($byAttr)) {
            return '';
        }

        $lines = ['', 'Typische Werte vergleichbarer Vorbild-Produkte:'];
        foreach ($byAttr as $tn => $values) {
            $lines[] = sprintf('- %s: %s', $tn, implode(', ', array_slice(array_keys($values), 0, 5)));
        }

        return implode("\n", $lines);
    }

    private function scalarToText(mixed $val): string
    {
        if ($val === null) {
            return 'kein Wert';
        }
        if (is_array($val)) {
            return implode(', ', $val);
        }
        if (is_bool($val)) {
            return $val ? 'ja' : 'nein';
        }
        return (string) $val;
    }
}
