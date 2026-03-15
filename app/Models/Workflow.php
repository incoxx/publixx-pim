<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'start_status_id',
        'end_status_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function startStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'start_status_id');
    }

    public function endStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'end_status_id');
    }

    public function statusAssignments(): HasMany
    {
        return $this->hasMany(WorkflowStatusAssignment::class)->orderBy('sort_order');
    }

    public function statuses(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowStatus::class, 'workflow_status_assignments')
            ->withPivot(['sort_order'])
            ->orderByPivot('sort_order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function productTypes(): HasMany
    {
        return $this->hasMany(ProductType::class, 'workflow_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'workflow_id');
    }

    public function deletionConstraints(): array
    {
        return [
            'productTypes' => 'Produkttypen',
            'products' => 'Produkte',
        ];
    }
}
