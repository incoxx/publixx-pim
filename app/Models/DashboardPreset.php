<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardPreset extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'payload',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
