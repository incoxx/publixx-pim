<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'auditable_type' => 'App\\Models\\Product',
            'auditable_id' => fake()->uuid(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
