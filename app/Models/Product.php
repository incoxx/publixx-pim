<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use Auditable, HasDeletionConstraints, HasFactory, HasUuids;

    /**
     * Invariante: Ein Produkt darf nie ein abstraktes Referenz-Profil
     * referenzieren (abstrakte Profile sind nur Vererbungs-Basis). Greift auf
     * allen Speicherwegen, nicht nur im Conformance-Endpoint.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (!$product->isDirty('reference_profile_id') || $product->reference_profile_id === null) {
                return;
            }
            $isAbstract = ProductReferenceProfile::whereKey($product->reference_profile_id)
                ->value('is_abstract');
            if ($isAbstract) {
                throw ValidationException::withMessages([
                    'reference_profile_id' => 'Abstrakte Referenz-Profile können einem Produkt nicht zugewiesen werden.',
                ]);
            }
        });
    }


    protected $fillable = [
        'product_type_id',
        'reference_profile_id',
        'sku',
        'ean',
        'name',
        'status',
        'product_type_ref',
        'parent_product_id',
        'master_hierarchy_node_id',
        'manufacturer_id',
        'created_by',
        'updated_by',
        'workflow_id',
        'current_workflow_status_id',
        'workflow_assignee_id',
        'workflow_team_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    // --- Relationships ---

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function referenceProfile(): BelongsTo
    {
        return $this->belongsTo(ProductReferenceProfile::class, 'reference_profile_id');
    }

    public function conformanceResult(): HasOne
    {
        return $this->hasOne(ProductConformanceResult::class);
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_product_id')
            ->where('product_type_ref', 'variant');
    }

    public function masterHierarchyNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'master_hierarchy_node_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function currentWorkflowStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'current_workflow_status_id');
    }

    public function workflowAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workflow_assignee_id');
    }

    public function workflowTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'workflow_team_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_product');
    }

    public function hasActiveWorkflow(): bool
    {
        return $this->workflow_id !== null;
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function variantInheritanceRules(): HasMany
    {
        return $this->hasMany(VariantInheritanceRule::class);
    }

    /**
     * Merkmalsachsen dieses Produkts, wenn es ein Elternprodukt mit Varianten ist.
     */
    public function variantAxes(): HasMany
    {
        return $this->hasMany(ProductVariantAxis::class, 'product_id')->orderBy('position');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media_assignments')
            ->withPivot(['usage_type_id', 'sort_order', 'is_primary'])
            ->orderByPivot('sort_order');
    }

    public function mediaAssignments(): HasMany
    {
        return $this->hasMany(ProductMediaAssignment::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function relations(): HasMany
    {
        return $this->outgoingRelations();
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'source_product_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'target_product_id');
    }

    public function outputHierarchyAssignments(): HasMany
    {
        return $this->hasMany(OutputHierarchyProductAssignment::class);
    }

    public function virtualDefinition(): HasOne
    {
        return $this->hasOne(VirtualProductDefinition::class);
    }

    /**
     * Vererbungsregeln, wenn dieses Produkt virtuell ist (die "Klammer").
     */
    public function inheritanceRules(): HasMany
    {
        return $this->hasMany(VirtualProductInheritanceRule::class, 'virtual_product_id');
    }

    /**
     * Medien-Vererbungsregeln, wenn dieses Produkt virtuell ist (die "Klammer").
     */
    public function mediaInheritanceRules(): HasMany
    {
        return $this->hasMany(VirtualProductMediaInheritanceRule::class, 'virtual_product_id');
    }

    /**
     * Ob der Produkttyp dieses Produkts den "Cluster-Vererbung"-Tab erlaubt
     * (Klammer-Produkt: Mitglieder werden dynamisch aus einem Suchprofil,
     * einer PQL-Abfrage oder einer Merkliste aufgelöst). Eigenschaft des
     * Produkttyps, nicht des einzelnen Produkts — ob ein Produkt tatsächlich
     * als Cluster konfiguriert ist, entscheidet die (optionale) 1:1-Relation
     * virtualDefinition().
     */
    public function isVirtual(): bool
    {
        return $this->productType?->has_dynamic_cluster === true;
    }

    public function searchIndex(): HasOne
    {
        return $this->hasOne(ProductSearchIndex::class, 'product_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class)->orderByDesc('version_number');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(ProductVersion::class)->where('status', 'active');
    }

    public function deletionConstraints(): array
    {
        return [
            'attributeValues'            => 'Attributwerte',
            'variants'                   => 'Varianten',
            'mediaAssignments'           => 'Medien-Zuordnungen',
            'prices'                     => 'Preise',
            'outgoingRelations'          => 'Ausgehende Beziehungen',
            'incomingRelations'          => 'Eingehende Beziehungen',
            'versions'                   => 'Versionen',
        ];
    }
}
