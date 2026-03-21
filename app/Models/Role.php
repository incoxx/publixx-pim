<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function entityRestrictions(): HasMany
    {
        return $this->hasMany(RoleEntityRestriction::class);
    }

    public function restrictionsForType(string $modelClass): HasMany
    {
        return $this->entityRestrictions()->where('restrictable_type', $modelClass);
    }
}
