<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AttributeViewAssignment extends Pivot
{
    use HasFactory, HasUuids;

    protected $table = 'attribute_view_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'attribute_id',
        'attribute_view_id',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function attributeView(): BelongsTo
    {
        return $this->belongsTo(AttributeView::class);
    }
}
