<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    use HasUuids;

    protected $fillable = [
        'workflow_id',
        'from_status_id',
        'to_status_id',
        'duration_hours',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'to_status_id');
    }
}
