<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON API Resource for export datasets.
 *
 * Since datasets are already plain arrays (not Eloquent models),
 * this resource simply passes through the data with optional wrapping.
 */
class DatasetResource extends JsonResource
{
    /**
     * The resource instance is an array, not a model.
     *
     * @var array
     */
    public $resource;

    /**
     * Create a new resource instance.
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }

    /**
     * Create a collection of resources from an array of datasets.
     *
     * @param  array  $datasets
     * @return array
     */
    public static function collection($datasets): array
    {
        return array_map(
            fn (array $dataset) => (new static($dataset))->toArray(request()),
            is_array($datasets) ? $datasets : $datasets->toArray()
        );
    }
}
