<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\DocumentPortalProductResource;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 * Öffentliches Dokumentenportal — Land/Sprache → Artikelsuche → Dokumenten-Download.
 *
 * Ermöglicht Endanwendern, Produktdokumentation (Gebrauchsanweisungen, Broschüren etc.)
 * nach Land und Sprache gefiltert abzurufen.
 */
class DocumentPortalController extends BaseController
{
    private const COUNTRY_ATTRIBUTE = 'udx_erbe_laender';
    private const PRIMARY_DOC_TYPE = 'Gebrauchsanweisung';

    /**
     * GET /api/v1/document-portal/countries
     *
     * Verfügbare Länder aus Produkt-Attribut country-restrictions aggregieren.
     */
    public function countries(): JsonResponse
    {
        $countryValues = ProductAttributeValue::query()
            ->whereHas('attribute', fn ($q) => $q->where('technical_name', self::COUNTRY_ATTRIBUTE))
            ->pluck('value_string')
            ->filter()
            ->unique();

        $countryCodes = $countryValues
            ->flatMap(fn (string $v) => explode('|', $v))
            ->map(fn (string $c) => strtoupper(trim($c)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $countryNames = self::COUNTRY_NAMES;
        $countries = $countryCodes->map(fn (string $code) => [
            'code' => $code,
            'name' => $countryNames[$code] ?? $code,
            'region' => self::countryRegion($code),
        ]);

        return response()->json(['data' => $countries->values()]);
    }

    /**
     * GET /api/v1/document-portal/search?q=...&country=DE
     *
     * Suche nach Produkten per SKU, EAN oder Name.
     * Optionaler Länderfilter über das country-restrictions Attribut.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'country' => 'sometimes|string|max:10',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $q = trim($request->input('q'));
        $country = $request->input('country');
        $perPage = (int) $request->input('per_page', 10);

        $query = Product::query()
            ->with([
                'media' => fn ($mq) => $mq->whereIn('media_type', ['document', 'image']),
                'productType',
            ])
            ->where(function ($builder) use ($q) {
                $builder->where('sku', $q)
                    ->orWhere('ean', $q)
                    ->orWhere('sku', 'LIKE', "%{$q}%")
                    ->orWhereHas('attributeValues', function ($avq) use ($q) {
                        $avq->where('value_string', 'LIKE', "%{$q}%")
                            ->whereHas('attribute', fn ($aq) => $aq->where('data_type', 'Dictionary'));
                    });
            });

        // Länderfilter: exakte Prüfung im pipe-separierten Wert (z.B. "AU|DE|US")
        if ($country) {
            $query->where(function ($builder) use ($country) {
                $builder->whereHas('attributeValues', function ($avq) use ($country) {
                    $avq->whereHas('attribute', fn ($aq) => $aq->where('technical_name', self::COUNTRY_ATTRIBUTE))
                        ->whereRaw("FIND_IN_SET(?, REPLACE(value_string, '|', ','))", [$country]);
                })
                ->orWhereDoesntHave('attributeValues', function ($avq) {
                    $avq->whereHas('attribute', fn ($aq) => $aq->where('technical_name', self::COUNTRY_ATTRIBUTE));
                });
            });
        }

        // Exakte SKU-Treffer zuerst
        $query->orderByRaw("CASE WHEN sku = ? THEN 0 WHEN ean = ? THEN 1 ELSE 2 END", [$q, $q]);

        $products = $query->paginate($perPage);

        return DocumentPortalProductResource::collection($products)->response();
    }

    /**
     * GET /api/v1/document-portal/products/{product}/documents?country=DE&lang=de
     *
     * Alle Dokumente eines Produkts, optional gefiltert nach Sprache.
     * Gruppiert nach Dokumenttyp, mit primärem Dokument für gewählte Sprache.
     */
    public function productDocuments(Request $request, string $productId): JsonResponse
    {
        $request->validate([
            'country' => 'sometimes|string|max:10',
            'lang' => 'sometimes|string|max:10',
        ]);

        $lang = $request->input('lang', 'de');

        $product = Product::with([
            'media' => fn ($mq) => $mq->whereIn('media_type', ['document', 'image']),
            'productType',
        ])->findOrFail($productId);

        $allDocuments = $product->media
            ->where('media_type', 'document')
            ->values();

        $availableLanguages = $allDocuments
            ->pluck('language')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $documentsByType = $allDocuments
            ->groupBy(fn ($m) => $m->document_type ?? 'Sonstige')
            ->map(fn ($group) => $group->map(fn ($m) => [
                'id' => $m->id,
                'file_name' => $m->file_name,
                'title' => $m->title_de,
                'language' => $m->language,
                'document_type' => $m->document_type,
                'mime_type' => $m->mime_type,
                'file_size' => $m->file_size,
                'download_url' => url('api/v1/media/file/' . $m->file_name),
            ])->sortBy('language')->values());

        $primaryDoc = $allDocuments
            ->filter(fn ($m) => $m->language === $lang && $m->document_type === self::PRIMARY_DOC_TYPE)
            ->first();

        if (!$primaryDoc) {
            $primaryDoc = $allDocuments->firstWhere('language', $lang);
        }

        return response()->json([
            'data' => [
                'product' => new DocumentPortalProductResource($product),
                'primary_document' => $primaryDoc ? [
                    'id' => $primaryDoc->id,
                    'file_name' => $primaryDoc->file_name,
                    'title' => $primaryDoc->title_de,
                    'language' => $primaryDoc->language,
                    'document_type' => $primaryDoc->document_type,
                    'download_url' => url('api/v1/media/file/' . $primaryDoc->file_name),
                ] : null,
                'documents_by_type' => $documentsByType,
                'available_languages' => $availableLanguages,
                'document_count' => $allDocuments->count(),
                'language_count' => $availableLanguages->count(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  Hilfs-Daten
    // ──────────────────────────────────────────────

    /** Länder-Regionen-Zuordnung. */
    private static function countryRegion(string $code): string
    {
        return match ($code) {
            'US', 'CA', 'MX', 'BR' => 'Americas',
            'AU', 'JP', 'CN', 'KR', 'IN', 'SG', 'TW' => 'Asia Pacific',
            'GB', 'EU', 'DE', 'FR', 'IT', 'ES', 'NL', 'PL', 'CZ', 'HU', 'AT', 'CH',
            'SE', 'NO', 'DK', 'FI', 'BE', 'PT', 'GR', 'RO', 'HR', 'LT', 'LV' => 'Europe',
            'RU' => 'Eastern Europe',
            'ROW' => 'Rest of World',
            default => 'Other',
        };
    }

    /** Länder-Anzeigenamen (erweiterbar). */
    private const COUNTRY_NAMES = [
        'AU' => 'Australia',
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'CH' => 'Switzerland',
        'CN' => 'China',
        'CZ' => 'Czech Republic',
        'DE' => 'Germany',
        'DK' => 'Denmark',
        'ES' => 'Spain',
        'EU' => 'European Union',
        'FI' => 'Finland',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'GR' => 'Greece',
        'HR' => 'Croatia',
        'HU' => 'Hungary',
        'IN' => 'India',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'LT' => 'Lithuania',
        'LV' => 'Latvia',
        'MX' => 'Mexico',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'ROW' => 'Rest of World',
        'RU' => 'Russia',
        'SE' => 'Sweden',
        'SG' => 'Singapore',
        'TW' => 'Taiwan',
        'US' => 'United States',
    ];
}
