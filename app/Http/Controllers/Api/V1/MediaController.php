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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $safeFilename = $this->generateSafeFilename($file);
        $path = $file->storeAs('media', $safeFilename, 'public');

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
            'file_name' => $safeFilename,
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
        $media = Media::where('file_name', $filename)->latest()->firstOrFail();

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

        // Check if source file exists first
        $originalPath = Storage::disk('public')->path($medium->file_path);
        if (!file_exists($originalPath)) {
            \Log::warning('Media file missing on disk', [
                'media_id' => $medium->id,
                'file_path' => $medium->file_path,
                'expected_path' => $originalPath,
            ]);
            return response()->json([
                'message' => 'Datei nicht auf dem Server gefunden.',
                'media_id' => $medium->id,
                'file_path' => $medium->file_path,
            ], 404);
        }

        // Try thumbnail generation
        $thumbPath = null;
        if (extension_loaded('gd') && str_starts_with($medium->mime_type, 'image/')) {
            try {
                $thumbPath = app(ThumbnailService::class)->generate($medium, $width, $height, $fit);
            } catch (\Throwable $e) {
                \Log::error('Thumbnail generation failed', [
                    'media_id' => $medium->id,
                    'file_path' => $medium->file_path,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } elseif (!extension_loaded('gd')) {
            \Log::warning('GD extension not loaded — thumbnails disabled.');
        }

        // Serve thumbnail if generated
        if ($thumbPath && file_exists($thumbPath)) {
            return response()->file($thumbPath, [
                'Content-Type' => $medium->mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // Fallback: serve original for images
        if (str_starts_with($medium->mime_type, 'image/')) {
            return response()->file($originalPath, [
                'Content-Type' => $medium->mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response()->json([
            'message' => 'Thumbnail nicht verfügbar (kein Bild).',
            'media_id' => $medium->id,
        ], 404);
    }

    /**
     * GET /media/diagnostics — check storage, GD, file integrity (admin only).
     */
    public function diagnostics(): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $disk = Storage::disk('public');
        $storagePath = $disk->path('media');
        $symlinkPath = public_path('storage');

        // Count media in DB
        $dbCount = Media::count();

        // Count physical files
        $physicalFiles = 0;
        if (is_dir($storagePath)) {
            $physicalFiles = count(array_filter(scandir($storagePath), fn ($f) => !in_array($f, ['.', '..'])));
        }

        // Find orphaned DB records (file_path in DB but file missing on disk)
        $missingFiles = [];
        Media::select('id', 'file_name', 'file_path')->chunk(100, function ($records) use ($disk, &$missingFiles) {
            foreach ($records as $record) {
                if (!$disk->exists($record->file_path)) {
                    $missingFiles[] = [
                        'id' => $record->id,
                        'file_name' => $record->file_name,
                        'file_path' => $record->file_path,
                    ];
                }
            }
        });

        return response()->json([
            'status' => 'ok',
            'checks' => [
                'gd_extension' => extension_loaded('gd'),
                'gd_info' => extension_loaded('gd') ? gd_info() : null,
                'storage_dir_exists' => is_dir($storagePath),
                'storage_dir_writable' => is_writable($storagePath),
                'storage_symlink_exists' => is_link($symlinkPath),
                'storage_symlink_target' => is_link($symlinkPath) ? readlink($symlinkPath) : null,
            ],
            'counts' => [
                'db_records' => $dbCount,
                'physical_files' => $physicalFiles,
                'missing_files' => count($missingFiles),
            ],
            'missing_files' => array_slice($missingFiles, 0, 20),
            'paths' => [
                'storage_path' => $storagePath,
                'public_storage' => $symlinkPath,
                'base_url' => url('api/v1/media'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'max_upload' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ]);
    }

    /**
     * Generate a safe, readable filename with collision handling.
     * "Mein Bild (1).jpg" → "mein-bild-1.jpg", with _1, _2 suffixes on collision.
     */
    private function generateSafeFilename(UploadedFile $file): string
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin');
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        // Sanitize: lowercase, replace spaces/special chars with hyphens, collapse multiples
        $safe = Str::ascii($baseName);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '-', $safe);
        $safe = preg_replace('/-{2,}/', '-', $safe);
        $safe = trim($safe, '-');
        $safe = mb_strtolower($safe) ?: 'datei';

        $disk = Storage::disk('public');
        $candidate = "{$safe}.{$extension}";
        $counter = 1;

        while ($disk->exists("media/{$candidate}")) {
            $candidate = "{$safe}_{$counter}.{$extension}";
            $counter++;
        }

        return $candidate;
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
