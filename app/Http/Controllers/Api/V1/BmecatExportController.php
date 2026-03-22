<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Export\BmecatFormatExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BmecatExportController extends Controller
{
    public function export(Request $request, BmecatFormatExporter $exporter): StreamedResponse
    {
        $request->validate([
            'version' => 'sometimes|string|in:1.2,2005',
            'hierarchy_id' => 'sometimes|nullable|string',
            'product_type_ids' => 'sometimes|array',
            'product_type_ids.*' => 'string',
            'attribute_ids' => 'sometimes|array',
            'attribute_ids.*' => 'string',
            'price_type_ids' => 'sometimes|array',
            'price_type_ids.*' => 'string',
            'relation_type_ids' => 'sometimes|array',
            'relation_type_ids.*' => 'string',
            'etim_version_id' => 'sometimes|nullable|string|exists:etim_versions,id',
        ]);

        $exporter->setVersion($request->input('version', '2005'));

        if ($request->filled('hierarchy_id')) {
            $exporter->setHierarchyId($request->input('hierarchy_id'));
        }
        if ($request->has('product_type_ids')) {
            $exporter->setProductTypeIds($request->input('product_type_ids', []));
        }
        if ($request->has('attribute_ids')) {
            $exporter->setAttributeIds($request->input('attribute_ids', []));
        }
        if ($request->has('price_type_ids')) {
            $exporter->setPriceTypeIds($request->input('price_type_ids', []));
        }
        if ($request->has('relation_type_ids')) {
            $exporter->setRelationTypeIds($request->input('relation_type_ids', []));
        }
        if ($request->filled('etim_version_id')) {
            $exporter->setEtimVersionId($request->input('etim_version_id'));
        }

        $xml = $exporter->export();
        $version = $request->input('version', '2005');
        $fileName = 'bmecat-export-' . $version . '-' . date('Y-m-d') . '.xml';

        return new StreamedResponse(function () use ($xml) {
            echo $xml;
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length' => strlen($xml),
        ]);
    }
}
