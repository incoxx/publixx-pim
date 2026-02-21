<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaRequest;
use App\Http\Requests\Api\V1\UpdateMediaRequest;
use App\Http\Resources\Api\V1\MediaResource;
use App\Models\Media;
use App\Services\ThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    private const ALLOWED_FILTERS = ['media_type', 'mime_type', 'asset_folder_id', 'usage_purpose'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query();

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        ));
        $this->applySearch($query, $request, ['file_name', 'title_de', 'title_en']);
        $this->applySorting($query, $request, 'created_at', 'desc');

        return MediaResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /media — upload a file (multipart/form-data).
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $file = $request->file('file');
        $path = $file->store('media', 'public');

        if ($path === false) {
            return response()->json([
                'message' => 'Datei konnte nicht gespeichert werden. Bitte prüfen Sie die Storage-Konfiguration.',
            ], 500);
        }

        // Auto-detect image dimensions
        $width = $request->input('width');
        $height = $request->input('height');
        if (($width === null || $height === null) && str_starts_with($file->getMimeType(), 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions) {
                $width = $width ?? $dimensions[0];
                $height = $height ?? $dimensions[1];
            }
        }

        $media = Media::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'media_type' => $this->detectMediaType($file->getMimeType()),
            'title_de' => $request->input('title_de', $file->getClientOriginalName()),
            'title_en' => $request->input('title_en'),
            'description_de' => $request->input('description_de'),
            'description_en' => $request->input('description_en'),
            'alt_text_de' => $request->input('alt_text_de'),
            'alt_text_en' => $request->input('alt_text_en'),
            'width' => $width,
            'height' => $height,
            'asset_folder_id' => $request->input('asset_folder_id'),
            'usage_purpose' => $request->input('usage_purpose', 'both'),
        ]);

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Media $medium): MediaResource
    {
        $this->authorize('view', $medium);

        return new MediaResource($medium);
    }

    public function update(UpdateMediaRequest $request, Media $medium): MediaResource
    {
        $this->authorize('update', $medium);

        $medium->update($request->validated());

        return new MediaResource($medium->fresh());
    }

    public function destroy(Media $medium): JsonResponse
    {
        $this->authorize('delete', $medium);

        // Delete thumbnails
        app(ThumbnailService::class)->clearCache($medium);

        // Delete the physical file
        if (Storage::disk('public')->exists($medium->file_path)) {
            Storage::disk('public')->delete($medium->file_path);
        }

        $medium->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /media/file/{filename} — serve the file directly (for PXF assetBase).
     */
    public function serve(string $filename): BinaryFileResponse
    {
        $media = Media::where('file_name', $filename)->firstOrFail();

        $path = Storage::disk('public')->path($media->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->file($path, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    /**
     * GET /media/thumb/{media}?w=300&h=300&fit=contain — serve a thumbnail.
     */
    public function thumb(Request $request, Media $medium): BinaryFileResponse|JsonResponse
    {
        $width = min(max(1, (int) $request->query('w', '300')), 1200);
        $height = min(max(1, (int) $request->query('h', '300')), 1200);
        $fit = in_array($request->query('fit'), ['contain', 'cover']) ? $request->query('fit') : 'contain';

        try {
            $thumbPath = app(ThumbnailService::class)->generate($medium, $width, $height, $fit);
        } catch (\Throwable $e) {
            \Log::error('Thumbnail generation failed', [
                'media_id' => $medium->id,
                'file_path' => $medium->file_path,
                'error' => $e->getMessage(),
            ]);
            $thumbPath = null;
        }

        if (!$thumbPath) {
            // Fallback: serve original for images, 404 for non-images
            if (str_starts_with($medium->mime_type, 'image/')) {
                $originalPath = Storage::disk('public')->path($medium->file_path);
                if (file_exists($originalPath)) {
                    return response()->file($originalPath, [
                        'Content-Type' => $medium->mime_type,
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }

            return response()->json([
                'message' => 'Thumbnail nicht verfügbar.',
                'media_id' => $medium->id,
                'file_exists' => Storage::disk('public')->exists($medium->file_path),
            ], 404);
        }

        return response()->file($thumbPath, [
            'Content-Type' => $medium->mime_type,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function detectMediaType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            in_array($mimeType, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']) => 'document',
            default => 'other',
        };
    }
}
