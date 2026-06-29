<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Strukturierte Content-Seite (analog Product).
 * Besteht aus typisierten, sortierten Sektionen. Platzierung im
 * Navigationsbaum erfolgt 1:n über navigation_nodes (Output-Muster).
 */
class ContentPage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'content_type_id',
        'slug',
        'title',
        'status',
        'valid_from',
        'valid_to',
        'seo_title_json',
        'seo_description_json',
        'seo_image_id',
        'workflow_id',
        'current_workflow_status_id',
        'workflow_assignee_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'seo_title_json' => 'array',
            'seo_description_json' => 'array',
        ];
    }

    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ContentSection::class)->orderBy('sort_order');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function currentWorkflowStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'current_workflow_status_id');
    }

    public function seoImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'seo_image_id');
    }

    /**
     * Nur Seiten, die aktuell sichtbar sind: aktiv und innerhalb des
     * Gültigkeitszeitraums (valid_from/valid_to). Für Vorschau und Export.
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 'active')
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now);
            });
    }

    /**
     * Ist die Seite zum aktuellen Zeitpunkt gültig/sichtbar?
     */
    public function isCurrentlyValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->valid_from !== null && $this->valid_from->gt($now)) {
            return false;
        }

        if ($this->valid_to !== null && $this->valid_to->lt($now)) {
            return false;
        }

        return true;
    }
}
