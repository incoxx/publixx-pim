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
            ->with(['media', 'usageType', 'motif.masterRendition'])
            ->whereIn('id', $request->input('assignment_ids'))
            ->get()
            ->filter(function (ProductMediaAssignment $assignment) use ($user) {
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
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            $this->cleanupTempDir($tempDir);
            abort(500, 'ZIP-Datei konnte nicht erstellt werden.');
        }

        try {
            $usedNames = [];
            $skipped = [];
            $disk = Storage::disk('public');
            foreach ($assignments as $assignment) {
                $media = $assignment->media ?? $assignment->motif?->masterRendition;
                if (! $media) {
                    $skipped[] = $assignment->motif?->title_de ?? $assignment->id;
                    continue;
                }
                $path = $disk->path($media->file_path);
                if (! file_exists($path)) {
                    $skipped[] = $media->file_name ?? $assignment->id;
                    continue;
                }
                $name = $this->uniqueZipEntryName($media->file_name ?? basename($path), $usedNames);
                if (! $zip->addFile($path, $name)) {
                    $skipped[] = $name;
                }
            }

            $zip->close();
        } catch (\Throwable $e) {
            $this->cleanupTempDir($tempDir);
            throw $e;
        }

        $fileName = ($product->sku ?? $product->id) . '-medien-' . date('Y-m-d') . '.zip';

        return response()->streamDownload(function () use ($zipPath, $tempDir) {
            readfile($zipPath);
            $this->cleanupTempDir($tempDir);
        }, $fileName, [
            'Content-Type' => 'application/zip',
            'X-Skipped-Count' => (string) count($skipped),
        ]);
    }

    private function cleanupTempDir(string $tempDir): void
    {
        foreach (glob("{$tempDir}/*") ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tempDir);
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
     * PUT /products/{product}/media/reorder — Reihenfolge der Medien-Zuordnungen persistieren
     * (Drag&Drop-Sortierung in der Produkt-Galerie). Erwartet die vollständige, neue Reihenfolge
     * der assignment_ids; sort_order wird aus der Array-Position abgeleitet.
     */
    public function reorder(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('media');

        $validated = $request->validate([
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'string|uuid',
        ]);

        $assignments = $product->mediaAssignments()
            ->whereIn('id', $validated['assignment_ids'])
            ->get()
            ->keyBy('id');

        // Die übermittelte Liste muss ALLE Zuordnungen des Produkts enthalten — sonst behalten
        // ausgelassene (z.B. dem Nutzer per RoleEntityRestriction verborgene) Zuordnungen ihre alte
        // sort_order und kollidieren mit den hier neu vergebenen 0..N-1-Werten.
        $totalAssignmentCount = $product->mediaAssignments()->count();
        if ($assignments->count() !== count($validated['assignment_ids'])
            || $totalAssignmentCount !== count($validated['assignment_ids'])) {
            return response()->json([
                'message' => 'Die Reihenfolge muss alle Medien-Zuordnungen des Produkts vollständig und ohne unbekannte IDs enthalten.',
            ], 422);
        }

        foreach (array_values($validated['assignment_ids']) as $sortOrder => $assignmentId) {
            $assignments->get($assignmentId)?->update(['sort_order' => $sortOrder]);
        }

        return response()->json(null, 204);
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
