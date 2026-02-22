<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ScheduleProductVersionRequest;
use App\Http\Requests\Api\V1\StoreProductVersionRequest;
use App\Http\Resources\Api\V1\ProductVersionResource;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Services\ProductVersioningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductVersionController extends Controller
{
    public function __construct(
        private readonly ProductVersioningService $versioningService,
    ) {}

    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [ProductVersion::class, $product]);

        $versions = $product->versions()
            ->with('creator')
            ->paginate($this->getPerPage($request));

        return ProductVersionResource::collection($versions);
    }

    public function store(StoreProductVersionRequest $request, Product $product): JsonResponse
    {
        $this->authorize('create', [ProductVersion::class, $product]);

        $version = $this->versioningService->createVersion(
            $product,
            $request->validated('change_reason'),
            $request->user()?->id,
        );

        return (new ProductVersionResource($version->load('creator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, ProductVersion $version): ProductVersionResource
    {
        $this->authorize('view', $version);

        return new ProductVersionResource($version->load('creator'));
    }

    public function activate(Product $product, ProductVersion $version): ProductVersionResource
    {
        $this->authorize('activate', $version);

        if (! in_array($version->status, ['draft', 'scheduled'])) {
            abort(422, 'Nur Entwürfe oder geplante Versionen können aktiviert werden.');
        }

        $this->versioningService->activateVersion($version);

        return new ProductVersionResource($version->fresh()->load('creator'));
    }

    public function schedule(ScheduleProductVersionRequest $request, Product $product, ProductVersion $version): ProductVersionResource
    {
        $this->authorize('schedule', $version);

        if ($version->status !== 'draft') {
            abort(422, 'Nur Entwürfe können geplant werden.');
        }

        $this->versioningService->scheduleVersion(
            $version,
            Carbon::parse($request->validated('publish_at')),
        );

        return new ProductVersionResource($version->fresh()->load('creator'));
    }

    public function cancelSchedule(Product $product, ProductVersion $version): ProductVersionResource
    {
        $this->authorize('schedule', $version);

        if ($version->status !== 'scheduled') {
            abort(422, 'Nur geplante Versionen können aufgehoben werden.');
        }

        $this->versioningService->cancelSchedule($version);

        return new ProductVersionResource($version->fresh()->load('creator'));
    }

    public function revert(Product $product, ProductVersion $version): ProductVersionResource
    {
        $this->authorize('revert', $version);

        $newVersion = $this->versioningService->revertToVersion($version, auth()->id());

        return new ProductVersionResource($newVersion->load('creator'));
    }

    public function compare(Request $request, Product $product): JsonResponse
    {
        $this->authorize('viewAny', [ProductVersion::class, $product]);

        $request->validate([
            'from' => ['required', function ($attribute, $value, $fail) {
                if ($value !== 'current' && !preg_match('/^[0-9a-f-]{36}$/i', $value)) {
                    $fail('The from field must be a valid UUID or "current".');
                }
            }],
            'to' => ['required', function ($attribute, $value, $fail) {
                if ($value !== 'current' && !preg_match('/^[0-9a-f-]{36}$/i', $value)) {
                    $fail('The to field must be a valid UUID or "current".');
                }
            }],
        ]);

        $fromId = $request->query('from');
        $toId = $request->query('to');

        if ($fromId === 'current' && $toId === 'current') {
            abort(422, 'Mindestens eine Seite muss eine gespeicherte Version sein.');
        }

        if ($toId === 'current') {
            $v1 = ProductVersion::where('product_id', $product->id)->findOrFail($fromId);
            $v1->load('creator');
            $diff = $this->versioningService->compareWithCurrent($v1, $product);
        } elseif ($fromId === 'current') {
            $v2 = ProductVersion::where('product_id', $product->id)->findOrFail($toId);
            $v2->load('creator');
            $diff = $this->versioningService->compareWithCurrent($v2, $product);
            $diff = $this->swapDiffSides($diff);
        } else {
            $v1 = ProductVersion::where('product_id', $product->id)->findOrFail($fromId);
            $v2 = ProductVersion::where('product_id', $product->id)->findOrFail($toId);
            $diff = $this->versioningService->compareVersions($v1, $v2);
        }

        return response()->json(['data' => $diff]);
    }

    private function swapDiffSides(array $diff): array
    {
        [$diff['left'], $diff['right']] = [$diff['right'], $diff['left']];
        foreach ($diff['fields'] as &$field) {
            [$field['old_value'], $field['new_value']] = [$field['new_value'], $field['old_value']];
        }

        return $diff;
    }
}
