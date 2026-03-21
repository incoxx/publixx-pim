<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleTabPermission extends Model
{
    use HasUuids;

    protected $fillable = [
        'role_id',
        'tab_key',
        'access_level',
    ];

    /**
     * All known product editor tab keys.
     */
    public const TAB_KEYS = [
        'base-data',
        'attributes',
        'variant-attributes',
        'variants',
        'media',
        'prices',
        'relations',
        'output-hierarchies',
        'preview',
        'versions',
        'scheduled-actions',
        'workflow-history',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeForRole($query, string $roleId)
    {
        return $query->where('role_id', $roleId);
    }
}
