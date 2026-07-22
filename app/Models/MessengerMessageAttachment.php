<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessengerMessageAttachment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'attachable_type',
        'attachable_id',
        'attached_via',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessengerMessage::class, 'message_id');
    }

    /**
     * attachable_type-Werte sind ueber Relation::morphMap() (AppServiceProvider::boot())
     * gemappt, z.B. 'product' => Product::class.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo('attachable', 'attachable_type', 'attachable_id');
    }
}
