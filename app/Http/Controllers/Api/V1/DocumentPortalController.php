<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\DocumentPortalProductResource;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\WebsiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

/**
 * Öffentliches Dokumentenportal — Land/Sprache → Artikelsuche → Dokumenten-Download.
 *
 * Ermöglicht Endanwendern, Produktdokumentation (Gebrauchsanweisungen, Broschüren etc.)
 * nach Land und Sprache gefiltert abzurufen.
 */
class DocumentPortalController extends BaseController
{
    /**
     * GET /api/v1/document-portal/settings
     *
     * Portal-Einstellungen (Theme, Titel, Logo etc.) — nutzt bestehendes WebsiteProfile.
     */
    public function settings(): JsonResponse
    {
        $payload = WebsiteProfile::getActivePayload();

        return response()->json([
            'data' => [
                'catalog_title' => $payload['catalog_title'] ?? 'Dokumentenportal',
                'logo_url' => !empty($payload['logo_media_id'])
                    ? url('api/v1/media/file/' . $payload['logo_media_id'])
                    : null,
                'color_primary' => $payload['color_primary'] ?? null,
                'color_accent' => $payload['color_accent'] ?? null,
                'font_family' => $payload['font_family'] ?? null,
                'footer_text' => $payload['footer_text'] ?? null,
                'impressum_url' => $payload['impressum_url'] ?? null,
                'kontakt_url' => $payload['kontakt_url'] ?? null,
                'custom_css' => $payload['custom_css'] ?? null,
                'catalog_access_mode' => $payload['catalog_access_mode'] ?? 'public',
            ],
        ]);
    }

    /**
     * GET /api/v1/document-portal/countries
     *
     * Verfügbare Länder aus Produkt-Attribut country-restrictions aggregieren.
     */
    public function countries(): JsonResponse
    {
        // Alle Werte des country-restrictions Attributs sammeln
        $countryValues = ProductAttributeValue::query()
            ->whereHas('attribute', fn ($q) => $q->where('technical_name', 'udx_erbe_laender'))
            ->pluck('value_string')
            ->filter()
            ->unique();

        // Pipe-separierte Werte auflösen und einzigartige Ländercodes sammeln
        $countryCodes = $countryValues
            ->flatMap(fn (string $v) => explode('|', $v))
            ->map(fn (string $c) => strtoupper(trim($c)))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Ländernamen zuordnen
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
        $perPage = min((int) $request->input('per_page', 10), 50);

        $query = Product::query()
            ->with(['media', 'productType'])
            ->where(function ($builder) use ($q) {
                $builder->where('sku', $q)
                    ->orWhere('ean', $q)
                    ->orWhere('sku', 'LIKE', "%{$q}%")
                    ->orWhereHas('attributeValues', function ($avq) use ($q) {
                        $avq->where('value_string', 'LIKE', "%{$q}%")
                            ->whereHas('attribute', fn ($aq) => $aq->where('data_type', 'Dictionary'));
                    });
            });

        // Länderfilter: Produkte mit passenden country-restrictions
        if ($country) {
            $query->where(function ($builder) use ($country) {
                $builder->whereHas('attributeValues', function ($avq) use ($country) {
                    $avq->whereHas('attribute', fn ($aq) => $aq->where('technical_name', 'udx_erbe_laender'))
                        ->where(function ($vq) use ($country) {
                            $vq->where('value_string', 'LIKE', "%{$country}%");
                        });
                })
                // Produkte ohne Länder-Einschränkung auch zeigen
                ->orWhereDoesntHave('attributeValues', function ($avq) {
                    $avq->whereHas('attribute', fn ($aq) => $aq->where('technical_name', 'udx_erbe_laender'));
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

        $product = Product::with(['media', 'productType'])->findOrFail($productId);

        // Alle Dokumente (media_type = document)
        $allDocuments = $product->media
            ->where('media_type', 'document')
            ->values();

        // Verfügbare Sprachen aggregieren
        $availableLanguages = $allDocuments
            ->pluck('language')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Dokumente nach Typ gruppieren
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

        // Primäres Dokument: Gebrauchsanweisung in gewählter Sprache
        $primaryDoc = $allDocuments
            ->filter(fn ($m) => $m->language === $lang && $m->document_type === 'Gebrauchsanweisung')
            ->first();

        // Fallback: erstes Dokument in gewählter Sprache
        if (!$primaryDoc) {
            $primaryDoc = $allDocuments->firstWhere('language', $lang);
        }

        // Produktbild
        $image = $product->media->firstWhere('media_type', 'image');

        return response()->json([
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'ean' => $product->ean,
                    'product_type' => $product->productType?->name_de,
                    'image_url' => $image
                        ? url('api/v1/media/thumb/' . $image->id . '?w=300&h=300')
                        : null,
                ],
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
