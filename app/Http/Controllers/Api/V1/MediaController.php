<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaRequest;
use App\Http\Requests\Api\V1\UpdateMediaRequest;
use App\Http\Resources\Api\V1\MediaResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Http\Traits\ChecksInstanceRestrictions;
use App\Jobs\ImportVideoFromUrl;
use App\Models\HierarchyNodeMediaAssignment;
use App\Models\Media;
use App\Models\MediaAssignmentHistory;
use App\Models\MediaMotif;
use App\Models\MediaRevision;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Models\RoleEntityRestriction;
use App\Models\User;
use App\Services\ThumbnailService;
use App\Support\Media\MediaFileTypes;
use App\Support\Media\VideoImportHosts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    use ChecksDeletionConstraints;
    use ChecksInstanceRestrictions;

    private const ALLOWED_FILTERS = ['media_type', 'asset_folder_id', 'usage_purpose', 'file_status'];

    // Felder, die per Präfix-Suche (LIKE 'wert%') statt Exakt-Match gefiltert werden,
    // für das Quick-Lookup im Medien-Menü bzw. im Medien-Picker-Dialog.
    private const ALLOWED_PREFIX_FILTERS = ['file_name', 'title_de', 'alt_text_de', 'mime_type'];

    private const ALLOWED_EXTENSIONS = MediaFileTypes::ALLOWED_EXTENSIONS;

    private const MAX_BULK_IMPORT_ROWS = 500;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query()->with(['pdfDocument:id,media_id,status', 'tags'])->withCount('revisions');
        $this->applyMediaFilters($query, $request);
        $this->hideRestrictedMedia($query, $request->user());
        $this->applySorting($query, $request, 'created_at', 'desc');

        return MediaResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * Blendet Medien aus, deren UsageType `restricted_display_mode = hidden` ist und
     * auf die der Nutzer laut RoleEntityRestriction keinen Zugriff hat (analog zu
     * ProductMediaController::hideRestrictedUsageTypes, hier ohne Zuordnungskontext).
     */
    private function hideRestrictedMedia($query, ?User $user): void
    {
        $hiddenIds = $this->hiddenUsageTypeIdsForUser($user);
        if ($hiddenIds->isEmpty()) {
            return;
        }

        $query
            ->whereDoesntHave('productAssignments', fn ($q) => $q->whereIn('usage_type_id', $hiddenIds))
            ->whereDoesntHave('hierarchyNodeAssignments', fn ($q) => $q->whereIn('usage_type_id', $hiddenIds));
    }

    /**
     * IDs der UsageTypes, die für den Nutzer per RoleEntityRestriction verborgen sind
     * (leer für Admins/Nutzer ohne Restriktionen). Von hideRestrictedMedia() und
     * suggestKeywords() genutzt, damit auch die Keyword-Vorschläge keine Schlagworte
     * aus für den Nutzer verborgenen Medien verraten.
     */
    private function hiddenUsageTypeIdsForUser(?User $user): \Illuminate\Support\Collection
    {
        if (! $user || $user->hasRole('Admin')) {
            return collect();
        }

        $restrictions = $this->getRestrictionsForUser($user, MediaUsageType::class);
        if ($restrictions->isEmpty()) {
            return collect();
        }

        $allowedIds = $restrictions->pluck('restrictable_id');

        return MediaUsageType::where('restricted_display_mode', 'hidden')
            ->whereNotIn('id', $allowedIds)
            ->pluck('id');
    }

    /**
     * GET /media/all-ids — Alle Media-IDs mit denselben Filtern wie index().
     */
    public function allIds(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query();
        $this->applyMediaFilters($query, $request);
        $this->hideRestrictedMedia($query, $request->user());

        $ids = $query->pluck('id');

        return response()->json([
            'ids' => $ids,
            'total' => $ids->count(),
        ]);
    }

    /**
     * GET /media/keywords/suggest?q=... — Autocomplete-Vorschläge aus bereits vergebenen
     * Keywords (Media + MediaMotif), damit beim Pflegen bestehende Schlagworte wiederverwendet
     * werden statt Duplikate mit Tippfehlern anzulegen. Nach Häufigkeit sortiert.
     */
    public function suggestKeywords(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $hiddenIds = $this->hiddenUsageTypeIdsForUser($request->user());

        $mediaQuery = Media::query()->whereNotNull('keywords');
        $motifQuery = MediaMotif::query()->whereNotNull('keywords');
        if ($hiddenIds->isNotEmpty()) {
            // Motive haben keine Hierarchie-Zuordnung, daher nur productAssignments prüfen.
            $mediaQuery
                ->whereDoesntHave('productAssignments', fn ($q) => $q->whereIn('usage_type_id', $hiddenIds))
                ->whereDoesntHave('hierarchyNodeAssignments', fn ($q) => $q->whereIn('usage_type_id', $hiddenIds));
            $motifQuery->whereDoesntHave('productAssignments', fn ($q) => $q->whereIn('usage_type_id', $hiddenIds));
        }

        // Ein Durchlauf: pro klein geschriebenem Keyword zählen und die zuerst gesehene
        // Schreibweise als Anzeige-Label merken (case-insensitive Dedupe, Original-Case erhalten).
        $counts = collect([$mediaQuery, $motifQuery])
            ->flatMap(fn ($query) => $query->latest()->limit(5000)->pluck('keywords'))
            ->flatMap(fn ($keywords) => (array) $keywords)
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter(fn ($keyword) => $keyword !== '')
            ->reduce(function (array $counts, string $keyword) {
                $key = mb_strtolower($keyword);
                $counts[$key] ??= ['label' => $keyword, 'count' => 0];
                $counts[$key]['count']++;

                return $counts;
            }, []);

        $suggestions = collect($counts)
            ->when($search !== '', fn ($c) => $c->filter(fn ($entry) => str_contains(mb_strtolower($entry['label']), $search)))
            ->sortByDesc('count')
            ->values()
            ->take($limit)
            ->pluck('label');

        return response()->json(['data' => $suggestions]);
    }

    /**
     * GET /media/export-excel — Excel-Export der gefilterten Medienliste.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query();
        $this->applyMediaFilters($query, $request);
        $this->hideRestrictedMedia($query, $request->user());
        $this->applySorting($query, $request, 'created_at', 'desc');

        $columns = $request->query('columns', 'file_name,title_de,mime_type,media_type,usage_purpose,file_size');
        $columnKeys = array_filter(array_map('trim', explode(',', $columns)));

        // Spalten-Definitionen (Key → Label)
        $columnDefs = [
            'file_name' => 'Dateiname',
            'title_de' => 'Titel (DE)',
            'title_en' => 'Titel (EN)',
            'mime_type' => 'MIME-Typ',
            'media_type' => 'Medientyp',
            'usage_purpose' => 'Verwendungszweck',
            'file_size' => 'Dateigröße (Bytes)',
            'width' => 'Breite (px)',
            'height' => 'Höhe (px)',
            'alt_text_de' => 'Alt-Text (DE)',
            'description_de' => 'Beschreibung (DE)',
            'created_at' => 'Erstellt am',
        ];

        // Nur erlaubte Spalten verwenden
        $columnKeys = array_values(array_intersect($columnKeys, array_keys($columnDefs)));
        if (empty($columnKeys)) {
            $columnKeys = ['file_name', 'title_de', 'mime_type', 'file_size'];
        }

        // Broken-Image-Prüfung immer als letzte Spalte
        $checkBroken = filter_var($request->query('check_broken', 'true'), FILTER_VALIDATE_BOOLEAN);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Medien');

        // Header
        $col = 1;
        foreach ($columnKeys as $key) {
            $sheet->setCellValue([$col, 1], $columnDefs[$key]);
            $sheet->getStyle([$col, 1])->getFont()->setBold(true);
            $col++;
        }
        if ($checkBroken) {
            $sheet->setCellValue([$col, 1], 'Broken Image');
            $sheet->getStyle([$col, 1])->getFont()->setBold(true);
        }

        // Daten
        $row = 2;
        $query->chunk(500, function ($media) use (&$row, $sheet, $columnKeys, $checkBroken) {
            foreach ($media as $item) {
                $col = 1;
                foreach ($columnKeys as $key) {
                    $value = $item->{$key};
                    if ($key === 'created_at' && $value) {
                        $value = $value->format('Y-m-d H:i');
                    }
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                if ($checkBroken) {
                    $isBroken = $this->isMediaFileBroken($item);
                    $sheet->setCellValue([$col, $row], $isBroken ? 'Ja' : 'Nein');
                }
                $row++;
            }
        });

        // Auto-Size Spalten
        foreach (range(1, count($columnKeys) + ($checkBroken ? 1 : 0)) as $colIdx) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx)
            )->setAutoSize(true);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'media_export_').'.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tmpFile);

        return response()->download($tmpFile, 'medien-export-'.date('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Prüft ob die physische Datei eines Mediums fehlt.
     */
    private function isMediaFileBroken(Media $media): bool
    {
        if (! $media->file_name) {
            return true;
        }
        $disk = Storage::disk(config('media-library.disk_name', 'public'));

        return ! $disk->exists('media/'.$media->file_name);
    }

    /**
     * Gemeinsame Filter-Logik für index(), allIds() und exportExcel().
     */
    private function applyMediaFilters($query, Request $request): void
    {
        $filters = $request->query('filter', []);

        // include_renditions: automatisch generierte Motiv-Renditions sind standardmäßig
        // ausgeblendet (Pipeline-Artefakte, werden über die Motiv-Verwaltung gepflegt) —
        // opt-in über ?include_renditions=true
        $includeRenditions = filter_var($request->query('include_renditions', 'false'), FILTER_VALIDATE_BOOLEAN);
        if (! $includeRenditions) {
            $query->whereNull('generated_at');
        }

        // include_descendants: Ordner + alle Unterordner
        $includeDescendants = filter_var($request->query('include_descendants', 'true'), FILTER_VALIDATE_BOOLEAN);

        if (! empty($filters['asset_folder_id']) && $includeDescendants) {
            $folderId = $filters['asset_folder_id'];
            $node = \App\Models\HierarchyNode::find($folderId);
            if ($node) {
                $descendantPath = $node->path === '/'
                    ? "/{$node->id}/"
                    : "{$node->path}{$node->id}/";

                $descendantIds = \App\Models\HierarchyNode::where('path', 'like', $descendantPath.'%')
                    ->pluck('id')
                    ->toArray();
                $descendantIds[] = $node->id;

                $query->whereIn('asset_folder_id', $descendantIds);
            } else {
                $query->where('asset_folder_id', $folderId);
            }
            // asset_folder_id aus Filtern entfernen, da wir es manuell behandelt haben
            unset($filters['asset_folder_id']);
        }

        $this->applyPrefixFilters($query, $filters, self::ALLOWED_PREFIX_FILTERS);

        $this->applyFilters($query, array_intersect_key(
            $filters,
            array_flip(self::ALLOWED_FILTERS)
        ));

        // Filter by file extensions (e.g. ?filter[extensions]=jpg,png,pdf)
        if (! empty($filters['extensions'])) {
            $extList = array_map(
                fn ($e) => preg_replace('/[^a-z0-9]/', '', strtolower(trim($e))),
                explode(',', $filters['extensions'])
            );
            $extList = array_filter($extList);
            if (! empty($extList)) {
                $query->where(function ($q) use ($extList) {
                    foreach ($extList as $ext) {
                        $q->orWhere('file_name', 'LIKE', '%.'.$ext);
                    }
                });
            }
        }

        // Exaktes Tag-Filtering (z.B. ?filter[keywords]=akkubohrer,profi — beliebiges der
        // angegebenen Keywords muss vorhanden sein), zusätzlich zur Freitextsuche unten.
        // Akzeptiert sowohl Komma-String (?filter[keywords]=a,b) als auch Array-Query-Syntax
        // (?filter[keywords][]=a&filter[keywords][]=b), da explode() sonst mit einem Array crasht.
        if (! empty($filters['keywords'])) {
            $rawKeywords = $filters['keywords'];
            $keywordList = is_array($rawKeywords)
                ? array_map('trim', $rawKeywords)
                : array_map('trim', explode(',', (string) $rawKeywords));
            $keywordList = array_filter($keywordList, fn ($k) => $k !== '');

            if (! empty($keywordList)) {
                $query->where(function ($q) use ($keywordList) {
                    foreach ($keywordList as $keyword) {
                        // whereJsonContains vergleicht in MySQL case-sensitiv; Keywords werden aber
                        // ohne einheitliche Groß-/Kleinschreibung gepflegt, daher case-insensitiver
                        // Abgleich über den (per LIKE-Escaping abgesicherten) JSON-Text der Spalte.
                        $needle = str_replace(['\\', '%', '_', '"'], ['\\\\', '\\%', '\\_', '\\"'], mb_strtolower($keyword));
                        $q->orWhereRaw('LOWER(CAST(keywords AS CHAR)) LIKE ?', ['%"'.$needle.'"%']);
                    }
                });
            }
        }

        // Tag-Filter (?filter[tags]=<id>,<id> — mindestens einer muss gesetzt sein).
        // Anders als die freien keywords sind Tags eine Zuordnung, daher Subquery
        // statt LIKE auf JSON.
        if (! empty($filters['tags'])) {
            $rawTags = $filters['tags'];
            $tagIds = is_array($rawTags)
                ? array_map('trim', $rawTags)
                : array_map('trim', explode(',', (string) $rawTags));
            $tagIds = array_values(array_filter($tagIds, fn ($id) => $id !== ''));

            if (! empty($tagIds)) {
                $query->whereIn('id', function ($sub) use ($tagIds) {
                    $sub->select('media_id')
                        ->from('media_tag')
                        ->whereIn('tag_id', $tagIds);
                });
            }
        }

        // 'keywords' bewusst NICHT Teil der applySearch()-Spaltenliste: MySQL 8 erlaubt LIKE nicht
        // direkt auf JSON-Spalten ("Cannot compare JSON in the LIKE operator") — Freitextsuche über
        // Keywords läuft daher über denselben CAST-Ansatz wie oben, ergänzt zur normalen Suche.
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                foreach (['file_name', 'title_de', 'title_en'] as $col) {
                    $q->orWhere($col, 'LIKE', "%{$search}%");
                }
                $q->orWhereRaw('LOWER(CAST(keywords AS CHAR)) LIKE ?', ['%'.mb_strtolower($search).'%']);
            });
        }

        // Filter: Medien ohne Datei (file_size = 0 oder NULL) — "Nicht vorhanden"
        if (! empty($filters['is_missing'])) {
            $query->where(function ($q) {
                $q->whereNull('file_size')->orWhere('file_size', 0);
            });
        }
    }

    /**
     * POST /media — upload a file (multipart/form-data).
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $file = $request->file('file');
        $originalFileName = $file->getClientOriginalName();
        $folderId = $request->input('asset_folder_id');

        // Prüfen ob ein Asset mit identischem Dateinamen im selben Ordner existiert
        // Match auf original_file_name ODER file_name (für Legacy-Daten vor Migration)
        $existing = Media::where('asset_folder_id', $folderId)
            ->where(function ($q) use ($originalFileName) {
                $q->where('original_file_name', $originalFileName)
                    ->orWhere('file_name', $originalFileName);
            })
            ->first();

        if ($existing) {
            return $this->replaceExistingMedia($request, $existing, $file, $originalFileName);
        }

        $response = $this->createNewMedia($request, $file, $originalFileName);

        // Auto-Relink: Prüfen ob die History Zuordnungen für diesen Dateinamen hat
        $this->autoRelinkFromHistory($originalFileName, $folderId);

        return $response;
    }

    /**
     * Neues Media-Asset erstellen (kein Duplikat im Ordner).
     */
    private function createNewMedia(StoreMediaRequest $request, UploadedFile $file, string $originalFileName): JsonResponse
    {
        $safeFilename = $this->generateSafeFilename($file);

        $disk = Storage::disk('public');
        if (! $disk->exists('media')) {
            $disk->makeDirectory('media');
        }

        $path = $file->storeAs('media', $safeFilename, 'public');

        if ($path === false) {
            return response()->json([
                'message' => 'Datei konnte nicht gespeichert werden. Bitte prüfen Sie die Storage-Konfiguration.',
            ], 500);
        }

        $storedPath = Storage::disk('public')->path($path);

        try {
            $this->processUploadedImage($file, $storedPath);

            [$width, $height] = $this->detectDimensions($request, $file, $storedPath);

            $media = Media::create([
                'file_name' => $safeFilename,
                'original_file_name' => $originalFileName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'media_type' => $this->detectMediaType($file->getMimeType()),
                'title_de' => $request->input('title_de', $originalFileName),
                'title_en' => $request->input('title_en'),
                'description_de' => $request->input('description_de'),
                'description_en' => $request->input('description_en'),
                'alt_text_de' => $request->input('alt_text_de'),
                'alt_text_en' => $request->input('alt_text_en'),
                'keywords' => $request->input('keywords'),
                'width' => $width,
                'height' => $height,
                'asset_folder_id' => $request->input('asset_folder_id'),
                'usage_purpose' => $request->input('usage_purpose', 'both'),
                'last_uploaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Cleanup: Datei auf Disk löschen wenn DB-Insert fehlschlägt
            Storage::disk('public')->delete($path);
            throw $e;
        }

        $this->generateEagerThumbnail($media);

        return (new MediaResource($media))
            ->additional(['replaced' => false])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Bestehendes Media-Asset ersetzen (gleicher original_file_name im selben Ordner).
     * Alte Datei wird als Revision archiviert.
     */
    private function replaceExistingMedia(StoreMediaRequest $request, Media $existing, UploadedFile $file, string $originalFileName): JsonResponse
    {
        // Berechtigung zum Bearbeiten prüfen
        $this->authorize('update', $existing);

        $disk = Storage::disk('public');

        // DB-Transaktion mit Row-Lock gegen Race Conditions bei parallelen Uploads
        $oldFileExisted = ! empty($existing->file_path) && $disk->exists($existing->file_path);

        $nextRevision = DB::transaction(function () use ($existing, $request, $file, $disk, $oldFileExisted) {
            // Row-Lock: verhindert gleichzeitiges Replace desselben Assets
            $existing = Media::lockForUpdate()->find($existing->id);

            // 1. Alte Datei als Revision archivieren
            $nextRevision = ($existing->revisions()->max('revision_number') ?? 0) + 1;
            $archiveDir = 'media-revisions/'.$existing->id;
            $archivePath = $archiveDir.'/rev-'.$nextRevision.'-'.$existing->file_name;

            if ($oldFileExisted) {
                $disk->copy($existing->file_path, $archivePath);

                MediaRevision::create([
                    'media_id' => $existing->id,
                    'revision_number' => $nextRevision,
                    'file_name' => $existing->file_name,
                    'file_path' => $archivePath,
                    'original_file_name' => $existing->original_file_name,
                    'mime_type' => $existing->mime_type,
                    'file_size' => $existing->file_size,
                    'width' => $existing->width,
                    'height' => $existing->height,
                    'replaced_by' => $request->user()?->id,
                    'replaced_at' => now(),
                ]);
            }

            // 2. Neue Datei unter gleichem Pfad speichern
            if ($oldFileExisted) {
                $disk->delete($existing->file_path);
            }

            // Korrekten Pfad sicherstellen (Broken Assets haben ggf. leeren file_path)
            $correctPath = 'media/'.$existing->file_name;
            $file->storeAs('media', $existing->file_name, 'public');

            $storedPath = $disk->path($correctPath);
            $this->processUploadedImage($file, $storedPath);

            [$width, $height] = $this->detectDimensions($request, $file, $storedPath);

            // 3. Media-Record updaten (inkl. file_path falls fehlend/falsch)
            $existing->update([
                'file_path' => $correctPath,
                'file_status' => 'ok',
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'media_type' => $this->detectMediaType($file->getMimeType()),
                'width' => $width,
                'height' => $height,
                'last_uploaded_at' => now(),
            ]);

            return $nextRevision;
        });

        // 4. Thumbnail-Cache löschen und neu generieren (außerhalb Transaction)
        app(ThumbnailService::class)->clearCache($existing);
        $this->generateEagerThumbnail($existing);

        return (new MediaResource($existing->fresh()))
            ->additional([
                'replaced' => true,
                'replaced_broken' => ! $oldFileExisted,
                'revision_number' => $nextRevision,
            ])
            ->response();
    }

    /**
     * EXIF-Korrektur und Image-Processing nach Upload.
     */
    private function processUploadedImage(UploadedFile $file, string $storedPath): void
    {
        if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
            $this->fixExifOrientation($storedPath);
        }
    }

    /**
     * Bild-Dimensionen ermitteln (aus Request oder automatisch).
     */
    private function detectDimensions(StoreMediaRequest $request, UploadedFile $file, string $storedPath): array
    {
        $width = $request->input('width');
        $height = $request->input('height');
        if (($width === null || $height === null) && str_starts_with($file->getMimeType(), 'image/')) {
            $dimensions = @getimagesize($storedPath);
            if ($dimensions) {
                $width = $width ?? $dimensions[0];
                $height = $height ?? $dimensions[1];
            }
        }

        return [$width, $height];
    }

    /**
     * Thumbnail sofort nach Upload generieren (für sofortige Vorschau).
     */
    private function generateEagerThumbnail(Media $media): void
    {
        if (! str_starts_with($media->mime_type ?? '', 'image/') || ! extension_loaded('gd')) {
            return;
        }
        try {
            app(ThumbnailService::class)->generate($media, 300, 300, 'contain');
        } catch (\Throwable $e) {
            \Log::debug('Eager thumbnail generation failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Auto-Relink: Nach Upload eines neuen Assets prüfen ob die History
     * Produkt-Zuordnungen für diesen Dateinamen enthält und wiederherstellen.
     */
    private function autoRelinkFromHistory(string $originalFileName, ?string $folderId): int
    {
        $media = Media::where('original_file_name', $originalFileName)
            ->where('asset_folder_id', $folderId)
            ->first();

        if (! $media) {
            return 0;
        }

        return $this->relinkMediaFromHistory($media);
    }

    /**
     * Für ein Media-Asset: History-Einträge mit passendem Dateinamen finden
     * und Produkt-Zuordnungen wiederherstellen.
     */
    private function relinkMediaFromHistory(Media $media): int
    {
        $fileName = $media->original_file_name ?? $media->file_name;

        // History-Einträge finden die zum Dateinamen passen
        $historyEntries = MediaAssignmentHistory::where(function ($q) use ($fileName) {
            $q->where('original_file_name', $fileName)
                ->orWhere('file_name', $fileName);
        })
            ->where(function ($q) use ($media) {
                // Gleicher Ordner oder kein Ordner-Filter
                $q->where('asset_folder_id', $media->asset_folder_id)
                    ->orWhereNull('asset_folder_id');
            })
            ->get();

        if ($historyEntries->isEmpty()) {
            return 0;
        }

        $relinked = 0;

        foreach ($historyEntries as $entry) {
            // Prüfen ob Zuordnung bereits existiert
            $exists = ProductMediaAssignment::where('product_id', $entry->product_id)
                ->where('media_id', $media->id)
                ->where('usage_type_id', $entry->usage_type_id)
                ->exists();

            if ($exists) {
                continue;
            }

            // Prüfen ob Produkt noch existiert
            if (! \App\Models\Product::where('id', $entry->product_id)->exists()) {
                continue;
            }

            ProductMediaAssignment::create([
                'product_id' => $entry->product_id,
                'media_id' => $media->id,
                'usage_type_id' => $entry->usage_type_id,
                'sort_order' => $entry->sort_order,
                'is_primary' => $entry->is_primary,
            ]);

            $relinked++;

            // History-Eintrag als wiederhergestellt markieren (löschen)
            $entry->delete();
        }

        if ($relinked > 0) {
            \Log::info('Media auto-relinked from history', [
                'media_id' => $media->id,
                'file_name' => $fileName,
                'relinked_count' => $relinked,
            ]);
        }

        return $relinked;
    }

    /**
     * POST /media/relink — Für ausgewählte Medien die History durchsuchen
     * und Produkt-Zuordnungen wiederherstellen.
     */
    public function relink(Request $request): JsonResponse
    {
        $this->authorize('update', Media::class);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1', 'max:100'],
            'media_ids.*' => ['required', 'uuid', 'exists:media,id'],
        ]);

        $mediaItems = Media::whereIn('id', $validated['media_ids'])->get();
        $totalRelinked = 0;
        $results = [];

        foreach ($mediaItems as $media) {
            $count = $this->relinkMediaFromHistory($media);
            $totalRelinked += $count;
            if ($count > 0) {
                $results[] = [
                    'media_id' => $media->id,
                    'file_name' => $media->original_file_name ?? $media->file_name,
                    'relinked' => $count,
                ];
            }
        }

        return response()->json([
            'message' => $totalRelinked > 0
                ? "{$totalRelinked} Zuordnungen wiederhergestellt."
                : 'Keine passenden History-Einträge gefunden.',
            'total_relinked' => $totalRelinked,
            'details' => $results,
        ]);
    }

    /**
     * GET /media/{media}/relink-preview — Zeigt welche Zuordnungen
     * aus der History wiederhergestellt werden könnten.
     */
    public function relinkPreview(Media $medium): JsonResponse
    {
        $this->authorize('view', $medium);

        $fileName = $medium->original_file_name ?? $medium->file_name;

        $historyEntries = MediaAssignmentHistory::where(function ($q) use ($fileName) {
            $q->where('original_file_name', $fileName)
                ->orWhere('file_name', $fileName);
        })
            ->where(function ($q) use ($medium) {
                $q->where('asset_folder_id', $medium->asset_folder_id)
                    ->orWhereNull('asset_folder_id');
            })
            ->with('product:id,sku')
            ->get()
            ->map(fn ($entry) => [
                'product_id' => $entry->product_id,
                'product_sku' => $entry->product?->sku,
                'usage_type_id' => $entry->usage_type_id,
                'is_primary' => $entry->is_primary,
                'deleted_at' => $entry->deleted_at,
            ]);

        return response()->json([
            'data' => $historyEntries,
            'count' => $historyEntries->count(),
        ]);
    }

    public function show(Media $medium): MediaResource
    {
        $this->authorize('view', $medium);

        return new MediaResource($medium->load('tags'));
    }

    public function update(UpdateMediaRequest $request, Media $medium): MediaResource
    {
        $this->authorize('update', $medium);

        $medium->update($request->validated());

        return new MediaResource($medium->fresh()->load('tags'));
    }

    public function dependencies(Media $medium): JsonResponse
    {
        $this->authorize('view', $medium);

        return $this->dependenciesResponse($medium);
    }

    /**
     * GET /media/{medium}/usage — Verwendungsnachweis: Produkte + Hierarchieknoten.
     */
    public function usage(Request $request, Media $medium): JsonResponse
    {
        $this->authorize('view', $medium);

        $perPage = min(max(1, (int) $request->query('per_page', '20')), 100);
        $page = max(1, (int) $request->query('page', '1'));

        // Produkte, die dieses Medium verwenden
        $productsQuery = $medium->products()
            ->select('products.id', 'products.sku', 'products.ean');

        $hasSearchIndex = DB::getSchemaBuilder()->hasTable('products_search_index');
        if ($hasSearchIndex) {
            $productsQuery->leftJoin('products_search_index', 'products_search_index.product_id', '=', 'products.id')
                ->addSelect('products_search_index.name_de', 'products_search_index.name_en', 'products_search_index.primary_image');
        }

        $paginated = $productsQuery->paginate($perPage, ['*'], 'page', $page);

        $products = collect($paginated->items())->map(function ($product) use ($hasSearchIndex) {
            $imageUrl = null;
            if ($hasSearchIndex && $product->primary_image) {
                $imageUrl = url('api/v1/media/thumb/'.$product->primary_image.'?w=80&h=80');
            }

            return [
                'id' => $product->id,
                'name' => $product->name_de ?? $product->sku,
                'sku' => $product->sku,
                'ean' => $product->ean,
                'image_url' => $imageUrl,
            ];
        });

        // Hierarchieknoten, an denen dieses Medium zugeordnet ist
        $nodeAssignments = $medium->hierarchyNodeAssignments()
            ->with(['hierarchyNode.hierarchy'])
            ->get();

        $nodes = $nodeAssignments->map(function ($assignment) {
            $node = $assignment->hierarchyNode;
            if (! $node) {
                return null;
            }

            return [
                'node_id' => $node->id,
                'node_name' => $node->name_de,
                'hierarchy_name' => $node->hierarchy?->name_de,
            ];
        })->filter()->values();

        return response()->json([
            'data' => [
                'products' => $products,
                'nodes' => $nodes,
            ],
            'meta' => [
                'products_total' => $paginated->total(),
                'products_current_page' => $paginated->currentPage(),
                'products_last_page' => $paginated->lastPage(),
                'nodes_total' => $nodes->count(),
            ],
        ]);
    }

    public function destroy(Request $request, Media $medium): JsonResponse
    {
        $this->authorize('delete', $medium);

        return $this->destroyWithConstraintCheck($request, $medium);
    }

    /**
     * GET /media/file/{filename} — serve the file directly.
     */
    public function serve(Request $request, string $filename): BinaryFileResponse
    {
        $media = Media::where('file_name', $filename)->latest()->firstOrFail();
        $this->assertSignedIfRestrictionSensitive($request, $media);

        // Noch kein file_path (z.B. Video-Import per URL, das noch läuft) — $disk->path('')
        // zeigt sonst auf das Disk-Wurzelverzeichnis, und file_exists() liefert dafür
        // faelschlich true (anders als is_file()/$disk->exists()), was response()->file()
        // mit einer FileNotFoundException crashen laesst.
        if (empty($media->file_path)) {
            abort(404, 'Datei wird noch verarbeitet.');
        }

        $disk = Storage::disk('public');
        $path = $disk->path($media->file_path);

        if (! file_exists($path)) {
            // Fallback: Datei könnte unter media/{filename} liegen (Import-Pfad-Korrektur)
            $correctedPath = 'media/'.$media->file_name;
            if ($media->file_path !== $correctedPath && $disk->exists($correctedPath)) {
                $media->update(['file_path' => $correctedPath]);
                $path = $disk->path($correctedPath);
            } else {
                abort(404, 'File not found.');
            }
        }

        return response()->file($path, $this->safeFileHeaders($media, [
            'Content-Type' => $media->mime_type,
        ]));
    }

    /**
     * GET /media/thumb/{media}?w=300&h=300&fit=contain — serve a thumbnail.
     */
    public function thumb(Request $request, Media $medium): BinaryFileResponse|JsonResponse
    {
        $this->assertSignedIfRestrictionSensitive($request, $medium);

        $width = min(max(1, (int) $request->query('w', '300')), 1200);
        $height = min(max(1, (int) $request->query('h', '300')), 1200);
        $fit = in_array($request->query('fit'), ['contain', 'cover']) ? $request->query('fit') : 'contain';

        // Noch kein file_path (z.B. Video-Import per URL, das noch läuft) — $disk->path('')
        // zeigt sonst auf das Disk-Wurzelverzeichnis, und file_exists() liefert dafür
        // faelschlich true (anders als is_file()/$disk->exists()), was die Thumbnail-Erzeugung
        // weiter unten mit einer FileNotFoundException crashen laesst.
        if (empty($medium->file_path)) {
            return response()->json([
                'message' => 'Datei wird noch verarbeitet.',
                'media_id' => $medium->id,
                'av_processing_status' => $medium->av_processing_status,
            ], 404);
        }

        // Check if source file exists first
        $disk = Storage::disk('public');
        $originalPath = $disk->path($medium->file_path);
        if (! file_exists($originalPath)) {
            // Fallback: Datei könnte unter media/{filename} liegen (Import-Pfad-Korrektur)
            $correctedPath = 'media/'.$medium->file_name;
            if ($medium->file_path !== $correctedPath && $disk->exists($correctedPath)) {
                // DB-Record korrigieren
                $medium->update(['file_path' => $correctedPath]);
                $originalPath = $disk->path($correctedPath);
            } else {
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
        }

        // Try thumbnail generation. Kein Vorab-Filter auf mime_type — ThumbnailService prüft bei
        // Bedarf den echten Dateiinhalt und heilt einen fehlerhaften mime_type (z.B. aus einem
        // Bulk-/BMEcat-Import mit falsch geratener Endung) selbst, statt hier fälschlich
        // abzubrechen.
        $thumbPath = null;
        if (extension_loaded('gd')) {
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
        } elseif (! extension_loaded('gd')) {
            \Log::warning('GD extension not loaded — thumbnails disabled.');
        }

        // Serve thumbnail if generated. Bei Video ist das generierte Thumbnail immer ein
        // JPEG-Frame (siehe ThumbnailService) — nicht den mime_type des Videos selbst
        // (z.B. "video/mp4") als Content-Type verwenden, sonst zeigen <img>-Tags nichts an.
        if ($thumbPath && file_exists($thumbPath)) {
            $thumbContentType = $medium->media_type === 'video' ? 'image/jpeg' : $medium->mime_type;

            return response()->file($thumbPath, $this->safeFileHeaders($medium, [
                'Content-Type' => $thumbContentType,
                'Cache-Control' => 'public, max-age=86400',
            ]));
        }

        // Fallback: serve original for images (inkl. SVG — daher sichere Header, Audit H-6)
        if (str_starts_with($medium->mime_type, 'image/')) {
            return response()->file($originalPath, $this->safeFileHeaders($medium, [
                'Content-Type' => $medium->mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ]));
        }

        return response()->json([
            'message' => 'Thumbnail nicht verfügbar (kein Bild).',
            'media_id' => $medium->id,
        ], 404);
    }

    /**
     * POST /admin/media/clear-thumbnail-cache — löscht den kompletten Bild-Thumbnail-Cache.
     * Behebt "broken" Thumbnails durch fehlerhafte/veraltete Cache-Dateien; die
     * Neuerzeugung passiert danach lazy beim nächsten Aufruf von thumb().
     */
    public function clearThumbnailCache(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $count = app(ThumbnailService::class)->clearAllCache();

        return response()->json([
            'message' => "{$count} zwischengespeicherte Thumbnails gelöscht. Sie werden beim nächsten Aufruf neu erzeugt.",
            'cleared' => $count,
        ]);
    }

    /**
     * POST /admin/media/fix-mime-types — korrigiert mime_type/media_type anhand des echten
     * Dateiinhalts für alle Medien, deren gespeicherter mime_type nicht auf "image/" beginnt
     * (typische Ursache für "Thumbnail nicht verfügbar (kein Bild)" bei tatsächlichen Bildern,
     * z.B. durch fehlerhaft geratene Endungen bei Bulk-/BMEcat-Importen).
     */
    public function fixMimeTypes(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        \Artisan::call('media:fix-mime-types');
        $output = trim(\Artisan::output());

        return response()->json([
            'message' => $output !== '' ? $output : 'MIME-Typen geprüft.',
        ]);
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
     * POST /media/bulk-delete — mehrere Medien löschen (kein Limit, intern in 100er-Chunks).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('delete', Media::class);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'uuid'],
            'force' => ['nullable', 'boolean'],
        ]);

        $force = $request->boolean('force');
        $deleted = 0;
        $skipped = 0;
        $errors = [];

        $mediaIds = $validated['media_ids'];

        // Constraint-Check in 1 Query statt N einzelne exists()-Calls
        $blockedIds = [];
        if (! $force) {
            $blockedIds = \App\Models\ProductMediaAssignment::whereIn('media_id', $mediaIds)
                ->pluck('media_id')
                ->flip()
                ->toArray();
        }

        // Medien in Chunks laden und löschen (verhindert Memory-Exhaustion bei vielen IDs)
        foreach (array_chunk($mediaIds, 100) as $chunk) {
            $mediaItems = Media::whereIn('id', $chunk)->get()->keyBy('id');

            foreach ($chunk as $mediaId) {
                $media = $mediaItems->get($mediaId);
                if (! $media) {
                    $skipped++;

                    continue;
                }

                if (isset($blockedIds[$mediaId])) {
                    $skipped++;
                    $errors[] = "{$media->file_name}: Hat Produkt-Zuordnungen";

                    continue;
                }

                try {
                    try {
                        app(ThumbnailService::class)->clearCache($media);
                    } catch (\Throwable) {
                    }

                    // Bei force=true: Zuordnungen vor dem Löschen explizit aufheben
                    if ($force) {
                        $media->productAssignments()->delete();
                        $media->hierarchyNodeAssignments()->delete();
                        $media->attributeValues()->delete();
                    }

                    $disk = Storage::disk('public');
                    if ($disk->exists('media-revisions/'.$media->id)) {
                        $disk->deleteDirectory('media-revisions/'.$media->id);
                    }
                    if ($disk->exists($media->file_path)) {
                        $disk->delete($media->file_path);
                    }

                    $media->delete();
                    $deleted++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = "{$media->file_name}: {$e->getMessage()}";
                }
            }
        }

        return response()->json([
            'message' => "{$deleted} Medien gelöscht".($skipped > 0 ? ", {$skipped} übersprungen." : '.'),
            'deleted' => $deleted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * POST /media/bulk-recover-url — fehlende Dateien von einem Base-URL-Pfad wiederherstellen.
     */
    public function bulkRecoverUrl(Request $request): JsonResponse
    {
        $this->authorize('update', Media::class);

        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['required', 'uuid'],
            'base_url' => ['required', 'url', 'max:2000'],
        ]);

        $baseUrl = rtrim($validated['base_url'], '/');

        if ($this->isInternalUrl($baseUrl)) {
            return response()->json(['message' => 'Interne oder private URLs sind nicht erlaubt.'], 422);
        }

        $disk = Storage::disk('public');
        $recovered = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_chunk($validated['media_ids'], 50) as $chunk) {
            $mediaItems = Media::whereIn('id', $chunk)->get()->keyBy('id');

            foreach ($chunk as $mediaId) {
                $media = $mediaItems->get($mediaId);
                if (! $media) {
                    $skipped++;

                    continue;
                }

                // Dateinamen-Kandidaten: original zuerst, dann sanitized
                $candidates = array_values(array_unique(array_filter([
                    $media->original_file_name ? basename($media->original_file_name) : null,
                    basename($media->file_name),
                ])));

                $success = false;
                foreach ($candidates as $candidate) {
                    $url = $baseUrl.'/'.rawurlencode($candidate);

                    try {
                        $response = $this->fetchExternalUrl($url);
                        if (! $response->successful()) {
                            continue;
                        }

                        $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION) ?: 'bin');
                        if (! $this->validateExtension($extension)) {
                            if (count($errors) < 100) {
                                $errors[] = "{$media->file_name}: Dateityp \"{$extension}\" nicht erlaubt.";
                            }
                            $skipped++;
                            $success = true; // verhindert doppelten failed-Zähler
                            break;
                        }

                        // Unter gleichem file_path speichern
                        $disk->put($media->file_path, $response->body());
                        $storedPath = $disk->path($media->file_path);

                        $actualMime = $this->detectMimeFromFile($storedPath)
                            ?? explode(';', $response->header('Content-Type', 'application/octet-stream'))[0];

                        if (in_array($actualMime, ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
                            $this->fixExifOrientation($storedPath);
                        }

                        $width = null;
                        $height = null;
                        if (str_starts_with($actualMime, 'image/')) {
                            $dimensions = @getimagesize($storedPath);
                            if ($dimensions) {
                                [$width, $height] = $dimensions;
                            }
                        }

                        $media->update([
                            'file_size' => $disk->size($media->file_path),
                            'mime_type' => $actualMime,
                            'width' => $width,
                            'height' => $height,
                            'file_status' => 'ok',
                            'last_uploaded_at' => now(),
                        ]);

                        try {
                            app(ThumbnailService::class)->clearCache($media);
                            $this->generateEagerThumbnail($media->fresh());
                        } catch (\Throwable) {
                        }

                        $recovered++;
                        $success = true;
                        break;
                    } catch (\Illuminate\Http\Client\ConnectionException) {
                        // Nächsten Kandidaten versuchen
                    }
                }

                if (! $success) {
                    $failed++;
                    if (count($errors) < 100) {
                        $errors[] = "{$media->file_name}: Nicht gefunden unter {$baseUrl}/";
                    }
                }
            }
        }

        return response()->json([
            'message' => "{$recovered} wiederhergestellt"
                .($failed > 0 ? ", {$failed} fehlgeschlagen" : '')
                .($skipped > 0 ? ", {$skipped} übersprungen" : '').'.',
            'recovered' => $recovered,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * GET /media/{media}/revisions — Datei-Historie abrufen.
     */
    public function revisions(Media $medium): JsonResponse
    {
        $this->authorize('view', $medium);

        $revisions = $medium->revisions()
            ->with('replacedByUser:id,name')
            ->get()
            ->map(fn (MediaRevision $rev) => [
                'id' => $rev->id,
                'revision_number' => $rev->revision_number,
                'file_name' => $rev->file_name,
                'original_file_name' => $rev->original_file_name,
                'file_size' => $rev->file_size,
                'mime_type' => $rev->mime_type,
                'width' => $rev->width,
                'height' => $rev->height,
                'replaced_by' => $rev->replacedByUser?->name,
                'replaced_at' => $rev->replaced_at,
                'reason' => $rev->reason,
                'download_url' => url('api/v1/media/revision/'.$rev->id.'/download'),
            ]);

        return response()->json(['data' => $revisions]);
    }

    /**
     * GET /media/revision/{revision}/download — Revisions-Datei herunterladen.
     */
    public function downloadRevision(Request $request, MediaRevision $revision): BinaryFileResponse
    {
        $this->authorize('view', $revision->media);
        $this->assertUsageTypeAccess($request->user(), $revision->media);

        $path = Storage::disk('public')->path($revision->file_path);

        if (! file_exists($path)) {
            abort(404, 'Revisions-Datei nicht gefunden.');
        }

        return response()->file($path, [
            'Content-Type' => $revision->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$revision->file_name.'"',
        ]);
    }

    /**
     * Prüft, ob der Nutzer über mindestens eine der Verwendungen dieses Mediums
     * (Produkt- oder Hierarchie-Zuordnung) Zugriff hat. Ein Medium ist grundsätzlich
     * downloadbar, sobald es dem Nutzer über irgendeinen legitimen Kontext zugänglich ist.
     */
    private function assertUsageTypeAccess(?User $user, Media $media): void
    {
        if (! $user || $user->hasRole('Admin')) {
            return;
        }

        // Kein Nutzer mit Rolleneinschränkungen für MediaUsageType → voller Zugriff (Default).
        $restrictions = $this->getRestrictionsForUser($user, MediaUsageType::class);
        if ($restrictions->isEmpty()) {
            return;
        }

        $usageTypeIds = $this->mediaUsageTypeIds($media);
        if ($usageTypeIds->isEmpty()) {
            return;
        }

        $usageTypes = MediaUsageType::whereIn('id', $usageTypeIds)->get();
        $hasAccessToAny = $usageTypes->contains(
            fn (MediaUsageType $usageType) => $this->checkInstanceAccess($user, $usageType, 'read')
        );

        if (! $hasAccessToAny) {
            abort(403, 'Kein Zugriff auf diesen Medientyp.');
        }
    }

    /**
     * Alle UsageType-IDs, mit denen dieses Medium irgendwo (Produkt- oder
     * Hierarchie-Zuordnung) verwendet wird.
     */
    private function mediaUsageTypeIds(Media $media): \Illuminate\Support\Collection
    {
        return ProductMediaAssignment::where('media_id', $media->id)->pluck('usage_type_id')
            ->merge(HierarchyNodeMediaAssignment::where('media_id', $media->id)->pluck('usage_type_id'))
            ->filter()
            ->unique();
    }

    /**
     * Öffentliche Datei-Auslieferung (serve()/thumb()) ist standardmäßig ohne Auth,
     * da sie von <img src> genutzt wird (kein Bearer-Token anhängbar). Sobald für einen
     * UsageType dieses Mediums irgendwo eine RoleEntityRestriction existiert, ist die
     * Route nicht mehr "für alle offen" und verlangt eine signierte URL (die nur von
     * bereits Bearer-Token-authentifizierten, rechtegeprüften Endpunkten ausgestellt wird).
     */
    private function assertSignedIfRestrictionSensitive(Request $request, Media $media): void
    {
        $usageTypeIds = $this->mediaUsageTypeIds($media);
        if ($usageTypeIds->isEmpty()) {
            return;
        }

        $isRestrictionSensitive = RoleEntityRestriction::where('restrictable_type', MediaUsageType::class)
            ->whereIn('restrictable_id', $usageTypeIds)
            ->exists();

        if ($isRestrictionSensitive && ! $request->hasValidSignature()) {
            abort(403, 'Für diesen Medientyp ist eine signierte URL erforderlich.');
        }
    }

    /**
     * GET /media/processing-status — Hintergrund-Verarbeitungsstatus (PDF-Jobs, Thumbnails).
     * Zeigt aktive, wartende und kürzlich abgeschlossene Verarbeitungen.
     */
    public function processingStatus(): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        // PDF-Status in 1 Query statt 4 separate COUNT-Queries
        $statusCounts = \App\Models\PdfDocument::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'processing'])
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'ready')->where('updated_at', '>=', now()->subMinutes(5));
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'error')->where('updated_at', '>=', now()->subHour());
                    });
            })
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $pdfPending = $statusCounts->get('pending', 0);
        $pdfProcessing = $statusCounts->get('processing', 0);
        $pdfReady = $statusCounts->get('ready', 0);
        $pdfError = $statusCounts->get('error', 0);

        // Kürzlich verarbeitete PDFs (letzte 10 Minuten)
        $recentPdfs = \App\Models\PdfDocument::with('media:id,file_name,original_file_name')
            ->whereIn('status', ['ready', 'error', 'processing', 'pending'])
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'media_id' => $doc->media_id,
                'file_name' => $doc->media?->original_file_name ?? $doc->media?->file_name ?? '—',
                'status' => $doc->status,
                'page_count' => $doc->page_count,
                'error_message' => $doc->error_message,
                'updated_at' => $doc->updated_at,
            ]);

        // Audio/Video-Status (ffmpeg/ffprobe-Verarbeitung), analog zum PDF-Block
        $avStatusCounts = Media::query()
            ->whereIn('media_type', ['audio', 'video'])
            ->whereNotNull('av_processing_status')
            ->where(function ($q) {
                $q->whereIn('av_processing_status', ['pending', 'processing'])
                    ->orWhere(function ($q2) {
                        $q2->where('av_processing_status', 'ready')->where('updated_at', '>=', now()->subMinutes(5));
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('av_processing_status', 'error')->where('updated_at', '>=', now()->subHour());
                    });
            })
            ->selectRaw('av_processing_status, COUNT(*) as cnt')
            ->groupBy('av_processing_status')
            ->pluck('cnt', 'av_processing_status');

        $avPending = $avStatusCounts->get('pending', 0);
        $avProcessing = $avStatusCounts->get('processing', 0);
        $avReady = $avStatusCounts->get('ready', 0);
        $avError = $avStatusCounts->get('error', 0);

        $recentAv = Media::whereIn('media_type', ['audio', 'video'])
            ->whereNotNull('av_processing_status')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'file_name', 'original_file_name', 'av_processing_status', 'duration_seconds', 'av_error_message', 'updated_at'])
            ->map(fn ($media) => [
                'id' => $media->id,
                'media_id' => $media->id,
                'file_name' => $media->original_file_name ?? $media->file_name ?? '—',
                'status' => $media->av_processing_status,
                'duration_seconds' => $media->duration_seconds,
                'error_message' => $media->av_error_message,
                'updated_at' => $media->updated_at,
            ]);

        // Kürzlich hochgeladene Medien (letzte 5 Minuten)
        $recentUploads = Media::where('last_uploaded_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_uploaded_at')
            ->limit(10)
            ->get(['id', 'file_name', 'original_file_name', 'media_type', 'last_uploaded_at']);

        return response()->json([
            'data' => [
                'pdf' => [
                    'pending' => $pdfPending,
                    'processing' => $pdfProcessing,
                    'recently_ready' => $pdfReady,
                    'recent_errors' => $pdfError,
                    'active' => $pdfPending + $pdfProcessing > 0,
                    'items' => $recentPdfs,
                ],
                'av' => [
                    'pending' => $avPending,
                    'processing' => $avProcessing,
                    'recently_ready' => $avReady,
                    'recent_errors' => $avError,
                    'active' => $avPending + $avProcessing > 0,
                    'items' => $recentAv,
                ],
                'recent_uploads' => $recentUploads,
                'has_activity' => ($pdfPending + $pdfProcessing) > 0
                    || ($avPending + $avProcessing) > 0
                    || $recentUploads->isNotEmpty(),
            ],
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
            $physicalFiles = count(array_filter(scandir($storagePath), fn ($f) => ! in_array($f, ['.', '..'])));
        }

        // Find orphaned DB records (file_path in DB but file missing on disk)
        $missingFiles = [];
        Media::select('id', 'file_name', 'file_path')->chunk(100, function ($records) use ($disk, &$missingFiles) {
            foreach ($records as $record) {
                if (! $disk->exists($record->file_path)) {
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

        // SSRF protection: reject internal/private URLs
        if ($this->isInternalUrl($validated['url'])) {
            return response()->json(['message' => 'Interne oder private URLs sind nicht erlaubt.'], 422);
        }

        try {
            $response = $this->fetchExternalUrl($validated['url']);
            if (! $response->successful()) {
                return response()->json(['message' => 'URL konnte nicht geladen werden (HTTP '.$response->status().').'], 422);
            }

            $contentType = $response->header('Content-Type', 'application/octet-stream');
            $contentType = explode(';', $contentType)[0]; // strip charset

            // Extract filename from URL
            $urlPath = parse_url($validated['url'], PHP_URL_PATH);
            $originalName = $urlPath ? basename($urlPath) : 'download';
            if (! pathinfo($originalName, PATHINFO_EXTENSION)) {
                $ext = match (true) {
                    str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                    str_contains($contentType, 'png') => 'png',
                    str_contains($contentType, 'gif') => 'gif',
                    str_contains($contentType, 'webp') => 'webp',
                    str_contains($contentType, 'svg') => 'svg',
                    str_contains($contentType, 'pdf') => 'pdf',
                    // audio/mpeg (mp3) VOR video/mpeg prüfen, sonst würde mp3 als "mpeg" (Video) erkannt
                    str_contains($contentType, 'audio/mpeg'), str_contains($contentType, 'audio/mp3') => 'mp3',
                    str_contains($contentType, 'video/mp4') => 'mp4',
                    str_contains($contentType, 'video/mpeg') => 'mpeg',
                    default => 'bin',
                };
                $originalName .= '.'.$ext;
            }

            // Validate extension
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin');
            if (! $this->validateExtension($extension)) {
                return response()->json(['message' => 'Dateityp "'.$extension.'" ist nicht erlaubt.'], 422);
            }

            $safeFilename = $this->generateSafeFilenameFromString($originalName);
            $storedPath = Storage::disk('public')->path('media/'.$safeFilename);
            Storage::disk('public')->put('media/'.$safeFilename, $response->body());

            // Detect actual MIME type from file content (don't trust remote server)
            $actualMime = $this->detectMimeFromFile($storedPath) ?? $contentType;

            // Fix EXIF orientation for JPEGs
            if (in_array($actualMime, ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
                $this->fixExifOrientation($storedPath);
            }

            // Detect dimensions
            $width = null;
            $height = null;
            if (str_starts_with($actualMime, 'image/')) {
                $dimensions = @getimagesize($storedPath);
                if ($dimensions) {
                    $width = $dimensions[0];
                    $height = $dimensions[1];
                }
            }

            $media = Media::create([
                'file_name' => $safeFilename,
                'original_file_name' => $originalName,
                'file_path' => 'media/'.$safeFilename,
                'mime_type' => $actualMime,
                'file_size' => Storage::disk('public')->size('media/'.$safeFilename),
                'media_type' => $this->detectMediaType($actualMime),
                'title_de' => pathinfo($originalName, PATHINFO_FILENAME),
                'width' => $width,
                'height' => $height,
                'asset_folder_id' => $validated['asset_folder_id'] ?? null,
                'usage_purpose' => $validated['usage_purpose'] ?? 'both',
                'last_uploaded_at' => now(),
            ]);

            return (new MediaResource($media))
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Media URL import connection failed', ['url' => $validated['url'], 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Verbindung zur URL fehlgeschlagen.'], 422);
        }
    }

    /**
     * POST /media/import-video-url — Video von einer erlaubten Plattform (aktuell nur
     * YouTube, siehe VideoImportHosts) per yt-dlp importieren. Läuft asynchron: legt sofort
     * einen Platzhalter-Media-Eintrag an (Status "pending") und dispatcht
     * ImportVideoFromUrl; das Frontend pollt den Verarbeitungsstatus wie beim normalen
     * Video-Upload (av_processing_status/duration_seconds).
     */
    public function importVideoFromUrl(Request $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validate([
            'url' => 'required|url|max:2000',
            'asset_folder_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
            'usage_purpose' => 'nullable|in:print,web,both',
        ]);

        // SSRF-Schutz (gleicher Guard wie beim Bild-URL-Import)
        if ($this->isInternalUrl($validated['url'])) {
            return response()->json(['message' => 'Interne oder private URLs sind nicht erlaubt.'], 422);
        }

        if (! VideoImportHosts::isAllowed($validated['url'])) {
            return response()->json([
                'message' => 'Diese Video-Plattform wird nicht unterstützt. Erlaubt: '
                    . implode(', ', VideoImportHosts::ALLOWED_HOSTS),
            ], 422);
        }

        // Platzhalter: file_path bleibt leer, bis ImportVideoFromUrl die echte Datei liefert.
        // handleAvProcessing() im MediaObserver überspringt leere file_path-Werte, dispatcht
        // ProcessAudioVideoMedia also erst nach dem tatsächlichen Download.
        $media = Media::create([
            'file_name' => Str::uuid()->toString() . '.mp4',
            'file_path' => '',
            'mime_type' => 'video/mp4',
            'file_size' => 0,
            'media_type' => 'video',
            'source_url' => $validated['url'],
            'asset_folder_id' => $validated['asset_folder_id'] ?? null,
            'usage_purpose' => $validated['usage_purpose'] ?? 'both',
            'av_processing_status' => 'pending',
        ]);

        ImportVideoFromUrl::dispatch($media->id, $validated['url'])->afterCommit();

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
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
        if (! $headerRow) {
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

        // Enforce row limit to prevent DoS
        if (count($rows) > self::MAX_BULK_IMPORT_ROWS) {
            return response()->json([
                'message' => 'Maximal '.self::MAX_BULK_IMPORT_ROWS.' Zeilen erlaubt. Die Datei enthält '.count($rows).' Zeilen.',
            ], 422);
        }

        $results = ['imported' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $rowIdx => $row) {
            $url = trim((string) ($row[$urlCol] ?? ''));
            if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            // SSRF protection
            if ($this->isInternalUrl($url)) {
                $results['skipped']++;
                if (count($results['errors']) < 50) {
                    $results['errors'][] = "Zeile {$rowIdx}: Interne URL übersprungen.";
                }

                continue;
            }

            try {
                $response = $this->fetchExternalUrl($url);
                if (! $response->successful()) {
                    $results['failed']++;
                    if (count($results['errors']) < 50) {
                        $results['errors'][] = "Zeile {$rowIdx}: HTTP {$response->status()}";
                    }

                    continue;
                }

                $contentType = explode(';', $response->header('Content-Type', 'application/octet-stream'))[0];
                $urlPath = parse_url($url, PHP_URL_PATH);
                $originalName = $urlPath ? basename($urlPath) : 'download';
                if (! pathinfo($originalName, PATHINFO_EXTENSION)) {
                    $ext = match (true) {
                        str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                        str_contains($contentType, 'png') => 'png',
                        str_contains($contentType, 'gif') => 'gif',
                        str_contains($contentType, 'webp') => 'webp',
                        // audio/mpeg (mp3) VOR video/mpeg prüfen, sonst würde mp3 als "mpeg" (Video) erkannt
                        str_contains($contentType, 'audio/mpeg'), str_contains($contentType, 'audio/mp3') => 'mp3',
                        str_contains($contentType, 'video/mp4') => 'mp4',
                        str_contains($contentType, 'video/mpeg') => 'mpeg',
                        default => 'bin',
                    };
                    $originalName .= '.'.$ext;
                }

                // Validate extension
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin');
                if (! $this->validateExtension($extension)) {
                    $results['skipped']++;
                    if (count($results['errors']) < 50) {
                        $results['errors'][] = "Zeile {$rowIdx}: Dateityp \"{$extension}\" nicht erlaubt.";
                    }

                    continue;
                }

                $safeFilename = $this->generateSafeFilenameFromString($originalName);
                Storage::disk('public')->put('media/'.$safeFilename, $response->body());
                $storedPath = Storage::disk('public')->path('media/'.$safeFilename);

                // Detect actual MIME type from file content
                $actualMime = $this->detectMimeFromFile($storedPath) ?? $contentType;

                if (in_array($actualMime, ['image/jpeg', 'image/jpg']) && function_exists('exif_read_data')) {
                    $this->fixExifOrientation($storedPath);
                }

                $width = null;
                $height = null;
                if (str_starts_with($actualMime, 'image/')) {
                    $dimensions = @getimagesize($storedPath);
                    if ($dimensions) {
                        $width = $dimensions[0];
                        $height = $dimensions[1];
                    }
                }

                Media::create([
                    'file_name' => $safeFilename,
                    'file_path' => 'media/'.$safeFilename,
                    'mime_type' => $actualMime,
                    'file_size' => strlen($response->body()),
                    'media_type' => $this->detectMediaType($actualMime),
                    'title_de' => pathinfo($originalName, PATHINFO_FILENAME),
                    'width' => $width,
                    'height' => $height,
                    'asset_folder_id' => $defaultFolderId,
                    'usage_purpose' => $defaultUsagePurpose,
                ]);

                $results['imported']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                \Log::error('Bulk import row failed', ['row' => $rowIdx, 'error' => $e->getMessage()]);
                if (count($results['errors']) < 50) {
                    $results['errors'][] = "Zeile {$rowIdx}: Import fehlgeschlagen.";
                }
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
            return response()->json(['message' => 'Ungültiger regulärer Ausdruck: '.preg_last_error_msg()], 422);
        }

        $dryRun = $validated['dry_run'] ?? true;
        $usageTypeId = $validated['usage_type_id'] ?? null;

        // Pre-load SKU→ID map (lightweight)
        $products = Product::where('product_type_ref', 'product')
            ->pluck('id', 'sku')
            ->toArray();

        $matches = [];
        $noMatch = [];
        $totalMedia = 0;

        // Set backtrack limit to prevent ReDoS from user-supplied patterns
        $previousLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '10000');

        // Process media in chunks to avoid memory exhaustion
        // Generierte Motiv-Renditions (Pipeline-Artefakte, z.B. "{motiv-uuid}-web-jpeg-rgb.jpg")
        // dürfen nie gegen SKU-Muster gematcht werden.
        Media::select('id', 'file_name')->whereNull('generated_at')->chunk(500, function ($mediaChunk) use ($pattern, $products, $usageTypeId, $dryRun, &$matches, &$noMatch, &$totalMedia) {
            foreach ($mediaChunk as $media) {
                $totalMedia++;
                $filename = pathinfo($media->file_name, PATHINFO_FILENAME);

                $result = @preg_match($pattern, $filename, $m);
                if ($result === false) {
                    continue; // Pattern caused error (e.g., backtrack limit hit)
                }
                if ($result === 0) {
                    continue;
                }

                // Use first capture group as SKU, or full match if no groups
                $sku = $m[1] ?? $m[0];
                $sku = trim($sku);

                if (isset($products[$sku])) {
                    $productId = $products[$sku];
                    $exists = ProductMediaAssignment::where('product_id', $productId)
                        ->where('media_id', $media->id)
                        ->exists();

                    if (! $exists) {
                        $matches[] = [
                            'media_id' => $media->id,
                            'file_name' => $media->file_name,
                            'sku' => $sku,
                            'product_id' => $productId,
                        ];

                        if (! $dryRun) {
                            $existingCount = ProductMediaAssignment::where('product_id', $productId)->count();
                            $maxSort = ProductMediaAssignment::where('product_id', $productId)->max('sort_order') ?? 0;
                            ProductMediaAssignment::create([
                                'product_id' => $productId,
                                'media_id' => $media->id,
                                'usage_type_id' => $usageTypeId,
                                'sort_order' => $maxSort + 1,
                                'is_primary' => $existingCount === 0,
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
        });

        // Restore original backtrack limit
        ini_set('pcre.backtrack_limit', $previousLimit);

        return response()->json([
            'dry_run' => $dryRun,
            'matched' => count($matches),
            'no_match' => count($noMatch),
            'total_media' => $totalMedia,
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
                $candidate = "{$safe}_".Str::random(8).".{$extension}";
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
            if (! $exif || ! isset($exif['Orientation'])) {
                return;
            }

            $orientation = (int) $exif['Orientation'];
            if ($orientation <= 1) {
                return; // Already correct
            }

            $image = @imagecreatefromjpeg($filePath);
            if (! $image) {
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
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin');
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
                $candidate = "{$safe}_".Str::random(8).".{$extension}";
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
            str_starts_with($mimeType, 'audio/') => 'audio',
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/csv',
            ]) => 'document',
            default => 'other',
        };
    }

    /**
     * MIME-Typen, die aktives Script tragen koennen. Solche Dateien duerfen bei
     * direkter Navigation nie inline gerendert werden (Stored XSS auf gleicher
     * Origin wie das Session-Cookie, Audit H-6). Als Attachment ausgeliefert
     * fuehrt der Browser sie nicht aus; die Einbettung via <img> rendert weiterhin
     * (SVG in <img> kann per Browser-Policy ohnehin kein Script ausfuehren).
     */
    private const INLINE_UNSAFE_MIMES = [
        'image/svg+xml',
        'text/html',
        'application/xhtml+xml',
        'text/xml',
        'application/xml',
    ];

    /**
     * Baut sichere Response-Header fuer die Datei-Auslieferung: immer nosniff,
     * und fuer script-faehige MIME-Typen zusaetzlich Content-Disposition: attachment.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function safeFileHeaders(Media $media, array $extra = []): array
    {
        $headers = array_merge(['X-Content-Type-Options' => 'nosniff'], $extra);

        if (in_array(strtolower((string) $media->mime_type), self::INLINE_UNSAFE_MIMES, true)) {
            $headers['Content-Disposition'] = 'attachment; filename="' . basename((string) $media->file_name) . '"';
        }

        return $headers;
    }

    /**
     * Laedt eine externe URL herunter und schuetzt dabei zusaetzlich vor SSRF ueber
     * Redirects (Audit H-5): isInternalUrl() prueft nur die urspruengliche URL —
     * ein Angreifer koennte sonst eine oeffentliche URL hosten, die per 302 auf
     * http://169.254.169.254/ (Cloud-Metadata) oder interne Dienste weiterleitet.
     * Jeder Redirect-Hop wird hier erneut gegen interne/private Netze geprueft.
     */
    private function fetchExternalUrl(string $url): \Illuminate\Http\Client\Response
    {
        return Http::withOptions([
            'allow_redirects' => [
                'max' => 3,
                'strict' => true,
                'referer' => false,
                'protocols' => ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri): void {
                    if ($this->isInternalUrl((string) $uri)) {
                        throw new \RuntimeException('Redirect auf interne/private URL blockiert.');
                    }
                },
            ],
        ])->timeout(30)->get($url);
    }

    /**
     * Validate that a URL does not point to internal/private networks (SSRF protection).
     */
    private function isInternalUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['host'])) {
            return true;
        }

        $host = $parsed['host'];
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'])) {
            return true;
        }

        $ip = gethostbyname($host);
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return true; // DNS resolution failed
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Validate file extension against allowlist.
     */
    private function validateExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS);
    }

    /**
     * Detect actual MIME type from file content instead of trusting remote headers.
     */
    private function detectMimeFromFile(string $filePath): ?string
    {
        return \App\Services\MimeTypeDetector::detectFromFile($filePath);
    }
}
