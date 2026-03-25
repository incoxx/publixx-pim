<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAssignmentHistory extends Model
{
    use HasUuids;

    protected $table = 'media_assignment_history';

    protected $fillable = [
        'product_id',
        'original_file_name',
        'file_name',
        'asset_folder_id',
        'usage_type_id',
        'sort_order',
        'is_primary',
        'old_media_id',
        'deleted_by',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
