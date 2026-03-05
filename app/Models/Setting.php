<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuids;

    protected $fillable = ['group', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public static function getPayload(string $group): ?array
    {
        return static::where('group', $group)->value('payload');
    }

    public static function setPayload(string $group, array $data): static
    {
        return static::updateOrCreate(
            ['group' => $group],
            ['payload' => $data],
        );
    }
}
