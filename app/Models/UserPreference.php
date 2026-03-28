<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'group', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getPayload(string $userId, string $group): ?array
    {
        return static::where('user_id', $userId)
            ->where('group', $group)
            ->value('payload');
    }

    public static function setPayload(string $userId, string $group, array $data): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'group' => $group],
            ['payload' => $data],
        );
    }
}
