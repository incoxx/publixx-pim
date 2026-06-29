<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMaterializedTree;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Knoten im Navigationsbaum (Sitemap). Materialized Path + sort_order
 * über den HasMaterializedTree-Trait. Polymorphes Ziel via target_type.
 */
class NavigationNode extends Model
{
    use HasFactory, HasMaterializedTree, HasUuids;

    protected $fillable = [
        'navigation_id',
        'parent_node_id',
        'path',
        'depth',
        'sort_order',
        'label_de',
        'label_en',
        'label_json',
        'slug',
        'icon',
        'is_visible',
        'target_type',
        'content_page_id',
        'hierarchy_node_id',
        'product_id',
        'external_url',
        'auto_expand',
        'expand_depth',
    ];

    protected function casts(): array
    {
        return [
            'label_json' => 'array',
            'depth' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'auto_expand' => 'boolean',
            'expand_depth' => 'integer',
        ];
    }

    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class);
    }

    public function parentNode(): BelongsTo
    {
        return $this->belongsTo(NavigationNode::class, 'parent_node_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NavigationNode::class, 'parent_node_id')->orderBy('sort_order');
    }

    public function contentPage(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'content_page_id');
    }

    public function hierarchyNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'hierarchy_node_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Verwaiste Knoten bereinigen: Ein Ziel-Knoten, dessen referenziertes
     * Objekt gelöscht wurde (FK per ON DELETE SET NULL → id ist NULL, aber
     * target_type zeigt weiter auf content_page/product/product_category),
     * ist ein „Artefakt". Blatt-Knoten werden gelöscht; Knoten mit Kindern
     * werden zu Ordnern degradiert (Menüstruktur bleibt erhalten).
     *
     * @return int  Anzahl gelöschter Knoten
     */
    public static function pruneOrphans(?string $navigationId = null): int
    {
        $query = static::query()->where(function ($q) {
            $q->where(fn ($q) => $q->where('target_type', 'content_page')->whereNull('content_page_id'))
                ->orWhere(fn ($q) => $q->where('target_type', 'product')->whereNull('product_id'))
                ->orWhere(fn ($q) => $q->where('target_type', 'product_category')->whereNull('hierarchy_node_id'));
        });

        if ($navigationId !== null) {
            $query->where('navigation_id', $navigationId);
        }

        $deleted = 0;
        foreach ($query->get() as $node) {
            if ($node->children()->exists()) {
                $node->update([
                    'target_type' => 'folder',
                    'content_page_id' => null,
                    'product_id' => null,
                    'hierarchy_node_id' => null,
                ]);
            } else {
                $node->delete();
                $deleted++;
            }
        }

        return $deleted;
    }
}
