<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'name' => $this->name,
            'url' => url("/access/{$this->token}"),
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'status' => $this->isUsed() ? 'used' : ($this->isExpired() ? 'expired' : 'open'),
            'user' => $this->whenLoaded('createdUser', fn () => $this->createdUser ? [
                'id' => $this->createdUser->id,
                'name' => $this->createdUser->name,
                'email' => $this->createdUser->email,
            ] : null),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
