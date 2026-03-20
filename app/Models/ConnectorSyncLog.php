<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectorSyncLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'connector_connection_id',
        'action',
        'entity_type',
        'entity_id',
        'external_id',
        'status',
        'error_message',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ConnectorConnection::class, 'connector_connection_id');
    }
}
