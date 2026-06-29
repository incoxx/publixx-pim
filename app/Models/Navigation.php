<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Navigationsbaum / Sitemap (z. B. 'main', 'footer').
 * Knoten zeigen polymorph auf Content-Seiten, Produktkategorien,
 * Produkte oder externe Links.
 */
class Navigation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'locale_set',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'locale_set' => 'array',
            'is_primary' => 'boolean',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(NavigationNode::class);
    }

    public function rootNodes(): HasMany
    {
        return $this->hasMany(NavigationNode::class)
            ->whereNull('parent_node_id')
            ->orderBy('sort_order');
    }
}
