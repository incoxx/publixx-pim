<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rollenspezifisches Cockpit-Layout (Phase 4, vom Admin pflegbar).
 */
class CockpitProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'role_id',
        'layout',
    ];

    protected $casts = [
        'layout' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
