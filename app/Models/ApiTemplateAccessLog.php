<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiTemplateAccessLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'api_template_id',
        'ip_address',
        'response_time_ms',
        'product_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'integer',
            'product_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function apiTemplate(): BelongsTo
    {
        return $this->belongsTo(ApiTemplate::class);
    }
}
