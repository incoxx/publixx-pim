<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PxfTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'pxf_data',
        'version',
        'orientation',
        'product_type_id',
        'export_mapping_id',
        'thumbnail',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pxf_data' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function exportMapping(): BelongsTo
    {
        return $this->belongsTo(PublixxExportMapping::class, 'export_mapping_id');
    }
}
