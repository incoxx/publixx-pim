<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerConversation extends Model
{
    use HasUuids;

    public const STATUSES = ['open', 'done'];

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'created_by',
        'last_message_at',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function latestMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MessengerMessage::class, 'conversation_id')->latestOfMany('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MessengerTask::class, 'conversation_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        });
    }

    /**
     * Liefert den jeweils anderen Teilnehmer der 1:1-Konversation.
     */
    public function otherUserId(string $currentUserId): string
    {
        return $this->user_one_id === $currentUserId ? $this->user_two_id : $this->user_one_id;
    }

    /**
     * Findet oder erstellt die 1:1-Konversation zwischen zwei Nutzern. Die IDs werden
     * kanonisch sortiert (kleinere UUID immer in user_one_id), damit der Unique-Index
     * unabhaengig von der Gespraechsrichtung Duplikate verhindert. $initiatorId wird nur
     * beim tatsächlichen Anlegen als created_by ("Verfasser") übernommen, nicht bei
     * Wiederverwendung einer bestehenden Konversation.
     */
    public static function betweenUsers(string $initiatorId, string $otherUserId): self
    {
        [$userOneId, $userTwoId] = $initiatorId <= $otherUserId ? [$initiatorId, $otherUserId] : [$otherUserId, $initiatorId];

        return static::firstOrCreate(
            ['user_one_id' => $userOneId, 'user_two_id' => $userTwoId],
            ['created_by' => $initiatorId]
        );
    }
}
