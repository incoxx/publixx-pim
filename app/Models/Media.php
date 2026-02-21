<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'media';

    protected $fillable = [
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'media_type',
        'title_de',
        'title_en',
        'description_de',
        'description_en',
        'alt_text_de',
        'alt_text_en',
        'width',
        'height',
        'asset_folder_id',
        'usage_purpose',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function assetFolder(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'asset_folder_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(MediaAttributeValue::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_media_assignments')
            ->withPivot(['usage_type', 'sort_order', 'is_primary']);
    }

    public function productAssignments(): HasMany
    {
        return $this->hasMany(ProductMediaAssignment::class);
    }
}
