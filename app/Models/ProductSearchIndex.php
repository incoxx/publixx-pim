<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSearchIndex extends Model
{
    use HasFactory;

    protected $table = 'products_search_index';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'sku',
        'ean',
        'product_type',
        'status',
        'name_de',
        'name_en',
        'description_de',
        'hierarchy_path',
        'primary_image',
        'list_price',
        'attribute_completeness',
        'phonetic_name_de',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'attribute_completeness' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
