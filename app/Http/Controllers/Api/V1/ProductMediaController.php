<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductMediaRequest;
use App\Http\Resources\Api\V1\ProductMediaResource;
use App\Http\Traits\AuditsChanges;
use App\Http\Traits\ChecksInstanceRestrictions;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ProductMediaController extends Controller
{
    use AuditsChanges;
    use ChecksInstanceRestrictions;
    use ChecksTabPermissions;

    /**
     * GET /products/{product}/media — assigned media for a product.
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->mediaAssignments()
            ->with(['media', 'motif.masterRendition', 'usageType'])
            ->orderBy('sort_order', 'asc');

        $this->hideRestrictedUsageTypes($query, $request->user());

        return ProductMediaResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * Blendet Medien mit UsageType `restricted_display_mode = hidden` aus,
     * auf die der Nutzer laut RoleEntityRestriction keinen Zugriff hat.
     */
    private function hideRestrictedUsageTypes($query, ?User $user): void
    {
        if (! $user || $user->hasRole('Admin')) {
            return;
        }

        $restrictions = $this->getRestrictionsForUser($user, MediaUsageType::class);
        if ($restrictions->isEmpty()) {
            return;
        }

        $allowedIds = $restrictions->pluck('restrictable_id');
        $hiddenIds = MediaUsageType::where('restricted_display_mode', 'hidden')
            ->whereNotIn('id', $allowedIds)
            ->pluck('id');

        if ($hiddenIds->isEmpty()) {
            return;
        }

        $query->where(function ($q) use ($hiddenIds) {
            $q->whereNull('usage_type_id')->orWhereNotIn('usage_type_id', $hiddenIds);
        });
    }

    /**
     * POST /products/{product}/media/download-zip — ausgewählte Medien als ZIP herunterladen.
     */
    public function downloadZip(Request $request, Product $product): StreamedResponse
    {
        $this->authorize('view', $product);

        $request->validate([
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'string|uuid',
        ]);

        $user = $request->user();

        $assignments = $product->mediaAssignments()
            ->with(['media', 'usageType'])
            ->whereIn('id', $request->input('assignment_ids'))
            ->get()
            ->filter(function (ProductMediaAssignment $assignment) use ($user) {
                if (! $assignment->media) {
                    return false;
                }
                if (! $assignment->usageType || $user->hasRole('Admin')) {
                    return true;
                }

                return $this->checkInstanceAccess($user, $assignment->usageType, 'read');
            });

        if ($assignments->isEmpty()) {
            abort(403, 'Kein Zugriff auf die ausgewählten Medien.');
        }

        $tempDir = storage_path('app/temp/product-media-' . uniqid());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/medien.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $usedNames = [];
        $disk = Storage::disk('public');
        foreach ($assignments as $assignment) {
            $media = $assignment->media;
            $path = $disk->path($media->file_path);
            if (! file_exists($path)) {
                continue;
            }
            $name = $this->uniqueZipEntryName($media->file_name ?? basename($path), $usedNames);
            $zip->addFile($path, $name);
        }

        $zip->close();

        $fileName = ($product->sku ?? $product->id) . '-medien-' . date('Y-m-d') . '.zip';

        return response()->streamDownload(function () use ($zipPath, $tempDir) {
            readfile($zipPath);
            array_map('unlink', glob("{$tempDir}/*"));
            rmdir($tempDir);
        }, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function uniqueZipEntryName(string $name, array &$usedNames): string
    {
        $original = $name;
        $i = 1;
        while (in_array($name, $usedNames, true)) {
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $base = pathinfo($original, PATHINFO_FILENAME);
            $name = $ext ? "{$base}-{$i}.{$ext}" : "{$base}-{$i}";
            $i++;
        }
        $usedNames[] = $name;

        return $name;
    }

    /**
     * POST /products/{product}/media — assign a medium to the product.
     */
    public function store(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('media');

        $assignment = $product->mediaAssignments()->create($request->validated());

        $this->audit('media_assigned', 'Product', $product->id, null, [
            'assignment_id' => $assignment->id,
            'media_id' => $assignment->media_id,
            'motif_id' => $assignment->motif_id,
            'usage_type_id' => $assignment->usage_type_id,
        ]);

        return (new ProductMediaResource($assignment->load(['media', 'motif.masterRendition', 'usageType'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /product-media/{id} — remove assignment.
     */
    public function destroy(ProductMediaAssignment $productMedium): JsonResponse
    {
        $this->authorize('update', $productMedium->product);
        $this->assertTabWriteAccess('media');

        $snapshot = [
            'assignment_id' => $productMedium->id,
            'media_id' => $productMedium->media_id,
            'motif_id' => $productMedium->motif_id,
            'usage_type_id' => $productMedium->usage_type_id,
        ];
        $productId = $productMedium->product_id;

        $productMedium->delete();

        $this->audit('media_removed', 'Product', $productId, $snapshot);

        return response()->json(null, 204);
    }
}
