<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaRequest;
use App\Http\Requests\Api\V1\UpdateMediaRequest;
use App\Http\Resources\Api\V1\MediaResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Models\MediaUsageType;
use App\Services\ThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    use ChecksDeletionConstraints;

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

        // Fix EXIF orientation for JPEG images (portrait photos rotated by camera)
        $storedPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
        if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
            $this->fixExifOrientation($storedPath);
        }

        // Auto-detect image dimensions (after EXIF fix)
        $width = $request->input('width');
        $height = $request->input('height');
        if (($width === null || $height === null) && str_starts_with($file->getMimeType(), 'image/')) {
            $dimensions = @getimagesize($storedPath);
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

    public function dependencies(Media $medium): JsonResponse
    {
        $this->authorize('view', $medium);

        return $this->dependenciesResponse($medium);
    }

    public function destroy(Request $request, Media $medium): JsonResponse
    {
        $this->authorize('delete', $medium);

        return $this->destroyWithConstraintCheck($request, $medium);
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
     * POST /media/bulk-move — move multiple media items to a folder.
     */
    public function bulkMove(Request $request): JsonResponse
    {
        $this->authorize('update', Media::class);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'uuid', 'exists:media,id'],
            'asset_folder_id' => ['nullable', 'uuid', 'exists:hierarchy_nodes,id'],
        ]);

        Media::whereIn('id', $validated['media_ids'])
            ->update(['asset_folder_id' => $validated['asset_folder_id']]);

        return response()->json([
            'message' => 'Medien erfolgreich verschoben.',
            'moved' => count($validated['media_ids']),
        ]);
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
     * POST /media/import-url — download an image from a URL and create a media record.
     */
    public function importFromUrl(Request $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validate([
            'url' => 'required|url|max:2000',
            'usage_type_id' => 'nullable|uuid|exists:media_usage_types,id',
            'asset_folder_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
            'usage_purpose' => 'nullable|in:print,web,both',
        ]);

        try {
            $response = Http::timeout(30)->withOptions(['verify' => false])->get($validated['url']);
            if (!$response->successful()) {
                return response()->json(['message' => 'URL konnte nicht geladen werden (HTTP ' . $response->status() . ').'], 422);
            }

            $contentType = $response->header('Content-Type', 'application/octet-stream');
            $contentType = explode(';', $contentType)[0]; // strip charset

            // Extract filename from URL
            $urlPath = parse_url($validated['url'], PHP_URL_PATH);
            $originalName = $urlPath ? basename($urlPath) : 'download';
            if (!pathinfo($originalName, PATHINFO_EXTENSION)) {
                $ext = match (true) {
                    str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                    str_contains($contentType, 'png') => 'png',
                    str_contains($contentType, 'gif') => 'gif',
                    str_contains($contentType, 'webp') => 'webp',
                    str_contains($contentType, 'svg') => 'svg',
                    str_contains($contentType, 'pdf') => 'pdf',
                    default => 'bin',
                };
                $originalName .= '.' . $ext;
            }

            $safeFilename = $this->generateSafeFilenameFromString($originalName);
            $storedPath = Storage::disk('public')->path('media/' . $safeFilename);
            Storage::disk('public')->put('media/' . $safeFilename, $response->body());

            // Fix EXIF orientation for JPEGs
            if (in_array($contentType, ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
                $this->fixExifOrientation($storedPath);
            }

            // Detect dimensions
            $width = null;
            $height = null;
            if (str_starts_with($contentType, 'image/')) {
                $dimensions = @getimagesize($storedPath);
                if ($dimensions) {
                    $width = $dimensions[0];
                    $height = $dimensions[1];
                }
            }

            $media = Media::create([
                'file_name' => $safeFilename,
                'file_path' => 'media/' . $safeFilename,
                'mime_type' => $contentType,
                'file_size' => strlen($response->body()),
                'media_type' => $this->detectMediaType($contentType),
                'title_de' => pathinfo($originalName, PATHINFO_FILENAME),
                'width' => $width,
                'height' => $height,
                'asset_folder_id' => $validated['asset_folder_id'] ?? null,
                'usage_purpose' => $validated['usage_purpose'] ?? 'both',
            ]);

            return (new MediaResource($media))
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage()], 422);
        }
    }

    /**
     * POST /media/bulk-import-urls — upload an Excel file with URLs and import them.
     * Expected Excel columns: url, usage_type (optional)
     */
    public function bulkImportFromUrls(Request $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'usage_type_id' => 'nullable|uuid|exists:media_usage_types,id',
            'asset_folder_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
            'usage_purpose' => 'nullable|in:print,web,both',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Find header row
        $headerRow = array_shift($rows);
        if (!$headerRow) {
            return response()->json(['message' => 'Excel-Datei ist leer.'], 422);
        }

        // Normalize headers
        $headers = array_map(fn ($h) => mb_strtolower(trim((string) ($h ?? ''))), $headerRow);
        $urlCol = array_search('url', $headers);
        if ($urlCol === false) {
            // Try alternative header names
            foreach ($headers as $col => $h) {
                if (in_array($h, ['url', 'bild-url', 'bild_url', 'bildurl', 'image_url', 'image-url', 'link'])) {
                    $urlCol = $col;
                    break;
                }
            }
        }
        if ($urlCol === false) {
            return response()->json(['message' => 'Spalte "URL" nicht gefunden. Erwartet: url, bild-url, image_url oder link.'], 422);
        }

        $defaultUsageTypeId = $request->input('usage_type_id');
        $defaultFolderId = $request->input('asset_folder_id');
        $defaultUsagePurpose = $request->input('usage_purpose', 'both');

        $results = ['imported' => 0, 'failed' => 0, 'errors' => []];

        foreach ($rows as $rowIdx => $row) {
            $url = trim((string) ($row[$urlCol] ?? ''));
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            try {
                $response = Http::timeout(30)->withOptions(['verify' => false])->get($url);
                if (!$response->successful()) {
                    $results['failed']++;
                    $results['errors'][] = "Zeile {$rowIdx}: HTTP {$response->status()} für {$url}";
                    continue;
                }

                $contentType = explode(';', $response->header('Content-Type', 'application/octet-stream'))[0];
                $urlPath = parse_url($url, PHP_URL_PATH);
                $originalName = $urlPath ? basename($urlPath) : 'download';
                if (!pathinfo($originalName, PATHINFO_EXTENSION)) {
                    $ext = match (true) {
                        str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                        str_contains($contentType, 'png') => 'png',
                        str_contains($contentType, 'gif') => 'gif',
                        str_contains($contentType, 'webp') => 'webp',
                        default => 'bin',
                    };
                    $originalName .= '.' . $ext;
                }

                $safeFilename = $this->generateSafeFilenameFromString($originalName);
                Storage::disk('public')->put('media/' . $safeFilename, $response->body());
                $storedPath = Storage::disk('public')->path('media/' . $safeFilename);

                if (in_array($contentType, ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
                    $this->fixExifOrientation($storedPath);
                }

                $width = null;
                $height = null;
                if (str_starts_with($contentType, 'image/')) {
                    $dimensions = @getimagesize($storedPath);
                    if ($dimensions) {
                        $width = $dimensions[0];
                        $height = $dimensions[1];
                    }
                }

                Media::create([
                    'file_name' => $safeFilename,
                    'file_path' => 'media/' . $safeFilename,
                    'mime_type' => $contentType,
                    'file_size' => strlen($response->body()),
                    'media_type' => $this->detectMediaType($contentType),
                    'title_de' => pathinfo($originalName, PATHINFO_FILENAME),
                    'width' => $width,
                    'height' => $height,
                    'asset_folder_id' => $defaultFolderId,
                    'usage_purpose' => $defaultUsagePurpose,
                ]);

                $results['imported']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = "Zeile {$rowIdx}: " . Str::limit($e->getMessage(), 100);
            }
        }

        return response()->json($results);
    }

    /**
     * POST /media/auto-match — match media filenames to product SKUs via regex
     * and create product_media_assignments.
     */
    public function autoMatch(Request $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validate([
            'pattern' => 'required|string|max:500',
            'usage_type_id' => 'nullable|uuid|exists:media_usage_types,id',
            'dry_run' => 'nullable|boolean',
        ]);

        // Validate regex
        $pattern = $validated['pattern'];
        if (@preg_match($pattern, '') === false) {
            return response()->json(['message' => 'Ungültiger regulärer Ausdruck: ' . preg_last_error_msg()], 422);
        }

        $dryRun = $validated['dry_run'] ?? true;
        $usageTypeId = $validated['usage_type_id'] ?? null;

        // Load all unassigned media (or all media)
        $mediaItems = Media::all();
        $products = Product::where('product_type_ref', 'product')
            ->pluck('id', 'sku')
            ->toArray();

        $matches = [];
        $noMatch = [];

        foreach ($mediaItems as $media) {
            $filename = pathinfo($media->file_name, PATHINFO_FILENAME);

            if (preg_match($pattern, $filename, $m)) {
                // Use first capture group as SKU, or full match if no groups
                $sku = $m[1] ?? $m[0];
                $sku = trim($sku);

                if (isset($products[$sku])) {
                    $productId = $products[$sku];
                    // Check if already assigned
                    $exists = ProductMediaAssignment::where('product_id', $productId)
                        ->where('media_id', $media->id)
                        ->exists();

                    if (!$exists) {
                        $matches[] = [
                            'media_id' => $media->id,
                            'file_name' => $media->file_name,
                            'sku' => $sku,
                            'product_id' => $productId,
                        ];

                        if (!$dryRun) {
                            $maxSort = ProductMediaAssignment::where('product_id', $productId)->max('sort_order') ?? 0;
                            ProductMediaAssignment::create([
                                'product_id' => $productId,
                                'media_id' => $media->id,
                                'usage_type_id' => $usageTypeId,
                                'sort_order' => $maxSort + 1,
                                'is_primary' => $maxSort === 0,
                            ]);
                        }
                    }
                } else {
                    $noMatch[] = [
                        'file_name' => $media->file_name,
                        'extracted_sku' => $sku,
                        'reason' => 'SKU nicht gefunden',
                    ];
                }
            }
        }

        return response()->json([
            'dry_run' => $dryRun,
            'matched' => count($matches),
            'no_match' => count($noMatch),
            'total_media' => $mediaItems->count(),
            'matches' => array_slice($matches, 0, 100),
            'unmatched' => array_slice($noMatch, 0, 50),
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
        $maxAttempts = 1000;

        while ($disk->exists("media/{$candidate}")) {
            if ($counter >= $maxAttempts) {
                $candidate = "{$safe}_" . Str::random(8) . ".{$extension}";
                break;
            }
            $candidate = "{$safe}_{$counter}.{$extension}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * Read EXIF orientation from JPEG and rotate the image file accordingly.
     */
    private function fixExifOrientation(string $filePath): void
    {
        try {
            $exif = @exif_read_data($filePath);
            if (!$exif || !isset($exif['Orientation'])) {
                return;
            }

            $orientation = (int) $exif['Orientation'];
            if ($orientation <= 1) {
                return; // Already correct
            }

            $image = @imagecreatefromjpeg($filePath);
            if (!$image) {
                return;
            }

            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => null,
            };

            if ($rotated) {
                imagejpeg($rotated, $filePath, 95);
                imagedestroy($rotated);
            }

            imagedestroy($image);
        } catch (\Throwable) {
            // Silently ignore — image remains as-is
        }
    }

    /**
     * Generate a safe filename from a plain string (for URL imports).
     */
    private function generateSafeFilenameFromString(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        $safe = Str::ascii($baseName);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '-', $safe);
        $safe = preg_replace('/-{2,}/', '-', $safe);
        $safe = trim($safe, '-');
        $safe = mb_strtolower($safe) ?: 'datei';

        $disk = Storage::disk('public');
        $candidate = "{$safe}.{$extension}";
        $counter = 1;
        $maxAttempts = 1000;

        while ($disk->exists("media/{$candidate}")) {
            if ($counter >= $maxAttempts) {
                $candidate = "{$safe}_" . Str::random(8) . ".{$extension}";
                break;
            }
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
