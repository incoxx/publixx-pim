<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gruppe für Tags — Muster wie AttributeType für Attribute.
 */
class TagGroup extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Bewusst leer: eine Gruppe zu löschen darf ihre Tags nicht blockieren, sie
     * werden per nullOnDelete ungruppiert. Der Tag selbst bleibt an Produkten
     * und Medien hängen — dafür greift die Prüfung am Tag.
     */
    public function deletionConstraints(): array
    {
        return [];
    }
}
