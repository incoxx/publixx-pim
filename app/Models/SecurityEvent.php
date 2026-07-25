<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein vom SecurityMonitor erkanntes verdaechtiges Ereignis
 * (Login-Burst, Bot-Zugriff, 4xx-Enumeration, Geo-Anomalie, ...).
 */
class SecurityEvent extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'type',
        'severity',
        'ip_address',
        'country',
        'user_id',
        'method',
        'path',
        'user_agent',
        'blocked',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'blocked' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
