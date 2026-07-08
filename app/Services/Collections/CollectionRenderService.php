<?php

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Collection;
use App\Models\CollectionAttributeValue;
use App\Models\CollectionItem;
use App\Models\CollectionRenderJob;
use App\Models\CollectionType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Rendert eine Collection als PDF/XLSX -- Job-Ausfuehrung mirroring ReportService::executeJob(),
 * Daten-Anreicherung ueber EnrichmentService (Phase 1), Kopf-/Branding-Bereich ueber eine
 * referenzierte PdfTemplate (CollectionPdfHeaderResolver -- Nutzerentscheidung "PdfTemplate
 * als Datenquelle nutzen"). Die Positionstabelle bleibt eigener Code, kein PdfTemplate-Element.
 */
class CollectionRenderService
{
    public function __construct(
        private readonly EnrichmentService $enrichmentService,
        private readonly CollectionXlsxWriter $xlsxWriter,
        private readonly CollectionPdfHeaderResolver $headerResolver,
    ) {}

    /**
     * @return array{path: string, format: string, size: int, duration: float, item_count: int}
     */
    public function render(Collection $collection, string $format, ?int $limit = null): array
    {
        $startTime = microtime(true);

        $collection->load([
            'collectionType.defaultRenderTemplate',
            'organization.logoMedia',
            'items' => function ($query) use ($limit) {
                if ($limit !== null) {
                    $query->limit($limit);
                }
            },
            'items.product',
            'items.unit',
            'items.attributeValues.attribute',
            'attributeValues.attribute',
        ]);

        $headerVm = $this->buildHeaderViewModel($collection);
        $items = $this->buildItemViewModels($collection);

        $grandTotal = array_sum(array_map(
            fn (array $item) => $item['price_warning'] ? 0.0 : (float) ($item['line_total'] ?? 0.0),
            $items
        ));

        $outputDir = storage_path('app/collections/renders');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection->name ?: $collection->id);
        $date = now()->format('Y-m-d_His');
        $extension = $format === 'xlsx' ? 'xlsx' : 'pdf';
        $outputPath = "{$outputDir}/{$slug}_{$date}.{$extension}";

        if ($format === 'xlsx') {
            $this->xlsxWriter->writeToFile($headerVm, $items, $grandTotal, $outputPath);
        } else {
            $this->writePdf($headerVm, $items, $grandTotal, $collection, $outputPath);
        }

        $duration = round(microtime(true) - $startTime, 2);

        return [
            'path' => $outputPath,
            'format' => $format,
            'size' => (int) filesize($outputPath),
            'duration' => $duration,
            'item_count' => count($items),
        ];
    }

    private function writePdf(array $headerVm, array $items, float $grandTotal, Collection $collection, string $outputPath): void
    {
        $viewName = $this->resolveViewName($collection->collectionType);

        $pdf = Pdf::loadView($viewName, [
            'header' => $headerVm,
            'items' => $items,
            'grandTotal' => $grandTotal,
        ]);

        $pdf->setPaper('A4', 'portrait');

        file_put_contents($outputPath, $pdf->output());
    }

    private function resolveViewName(CollectionType $type): string
    {
        $candidate = "collections.pdf.{$type->technical_name}";

        return view()->exists($candidate) ? $candidate : 'collections.pdf.default';
    }

    /**
     * @return array<int, array{position:int,name:?string,sku:?string,quantity:float,unit_label:?string,unit_price:?float,currency:?string,discount_percent:float,line_text:?string,line_total:?float,price_warning:bool}>
     */
    private function buildItemViewModels(Collection $collection): array
    {
        $rules = $this->buildEnrichmentRules($collection->collectionType);
        $currency = $collection->currency ?? $collection->organization?->currency ?? 'EUR';

        return $collection->items->map(function (CollectionItem $item) use ($collection, $rules, $currency) {
            $data = $collection->frozen_at !== null
                ? ($item->snapshot ?? [])
                : $this->enrichmentService->enrichItem($item, $collection, $rules);

            return $this->normalizeItemViewModel($item, $data, $currency);
        })->values()->all();
    }

    private function normalizeItemViewModel(CollectionItem $item, array $data, string $currency): array
    {
        $price = $data['resolved_price'] ?? null;
        $priceWarning = (bool) ($data['price_warning'] ?? false);

        $discountPercent = (float) ($this->findValueByTechnicalNameSuffix($item->attributeValues, '-rabatt') ?? 0);
        $lineText = $this->findValueByTechnicalNameSuffix($item->attributeValues, '-positionstext');

        $unitPrice = $price['amount'] ?? null;
        $lineTotal = ($unitPrice !== null && !$priceWarning)
            ? round($unitPrice * (float) $item->quantity * (1 - $discountPercent / 100), 2)
            : null;

        return [
            'position' => $item->position,
            'name' => $data['name'] ?? $item->product?->name,
            'sku' => $data['sku'] ?? $item->product?->sku,
            'quantity' => (float) $item->quantity,
            'unit_label' => $item->unit?->abbreviation,
            'unit_price' => $unitPrice,
            'currency' => $price['currency'] ?? $currency,
            'discount_percent' => $discountPercent,
            'line_text' => $lineText,
            'line_total' => $lineTotal,
            'price_warning' => $priceWarning,
        ];
    }

    /**
     * @return array<int, array{source:string,target:string,type:string}>
     */
    private function buildEnrichmentRules(CollectionType $type): array
    {
        if (empty($type->default_price_type)) {
            return [];
        }

        return [
            ['source' => "prices:{$type->default_price_type}", 'target' => 'price', 'type' => 'price'],
        ];
    }

    private function buildHeaderViewModel(Collection $collection): array
    {
        $type = $collection->collectionType;
        $organization = $collection->organization;

        $paymentTerms = $this->findValueByTechnicalNameSuffix($collection->attributeValues, '-zahlungsbedingungen');
        $coverText = $this->findValueByTechnicalNameSuffix($collection->attributeValues, '-kopftext');

        $templateElements = $this->headerResolver->resolve(
            $type->defaultRenderTemplate,
            $this->buildHeaderTextContext($collection),
            $organization
        );

        return [
            'name' => $collection->name,
            'reference' => $collection->reference,
            'organization_name' => $organization?->name,
            'address_block' => $organization?->address_block,
            'payment_terms' => $paymentTerms,
            'cover_text' => $coverText,
            'valid_from' => $collection->valid_from?->format('d.m.Y'),
            'valid_until' => $collection->valid_until?->format('d.m.Y'),
            'currency' => $collection->currency ?? $organization?->currency ?? 'EUR',
            'template_elements' => $templateElements,
        ];
    }

    /**
     * @return array<string, string|int|float>
     */
    private function buildHeaderTextContext(Collection $collection): array
    {
        $organization = $collection->organization;

        $context = [
            'collection.name' => $collection->name ?? '',
            'collection.reference' => $collection->reference ?? '',
            'organization.name' => $organization?->name ?? '',
            'organization.address_block' => $organization?->address_block ?? '',
        ];

        if ($collection->valid_until !== null) {
            $context['collection.valid_until'] = $collection->valid_until->format('d.m.Y');
        }

        return $context;
    }

    /**
     * Sucht ueber ein Suffix statt eines fest verdrahteten technical_name -- so bleibt die
     * Aufloesung fuer jeden collection_type generisch nutzbar (z.B. 'angebot-rabatt',
     * kuenftig 'planungsliste-rabatt', ...), solange die Namenskonvention "{typ}-{suffix}"
     * eingehalten wird (siehe DemoCollectionSeeder).
     */
    /**
     * @param  EloquentCollection<int, CollectionAttributeValue>  $attributeValues
     */
    private function findValueByTechnicalNameSuffix(EloquentCollection $attributeValues, string $suffix): ?string
    {
        $match = $attributeValues->first(
            fn (CollectionAttributeValue $value) => $value->attribute !== null && str_ends_with($value->attribute->technical_name, $suffix)
        );

        if ($match === null) {
            return null;
        }

        return $match->value_string ?? ($match->value_number !== null ? (string) $match->value_number : null);
    }

    /**
     * Exakter Spiegel von ReportService::executeJob() -- setzt running/completed/failed,
     * aktualisiert last_result, wirft im Fehlerfall erneut (Queue-Failure-Handling greift
     * zusaetzlich zum Job-Statusfeld).
     */
    public function executeJob(CollectionRenderJob $job): array
    {
        $collection = $job->collection;

        $job->update([
            'last_status' => 'running',
            'last_run_at' => now(),
            'last_error' => null,
        ]);

        try {
            $result = $this->render($collection, $job->format);

            $job->update([
                'last_status' => 'completed',
                'last_duration_seconds' => $result['duration'],
                'last_output_path' => $result['path'],
                'last_result' => [
                    'format' => $result['format'],
                    'size_bytes' => $result['size'],
                    'item_count' => $result['item_count'],
                    'duration_seconds' => $result['duration'],
                ],
            ]);

            return $result;
        } catch (\Throwable $e) {
            $job->update([
                'last_status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
