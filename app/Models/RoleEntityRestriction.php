<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RoleEntityRestriction extends Model
{
    use HasUuids;

    protected $fillable = [
        'role_id',
        'restrictable_type',
        'restrictable_id',
        'access_level',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function restrictable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForRole($query, string $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    public function scopeForType($query, string $modelClass)
    {
        return $query->where('restrictable_type', $modelClass);
    }
}
