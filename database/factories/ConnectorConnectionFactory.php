<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConnectorConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConnectorConnectionFactory extends Factory
{
    protected $model = ConnectorConnection::class;

    public function definition(): array
    {
        return [
            'connector_type' => fake()->randomElement(['shopware', 'deepl']),
            'name' => fake()->words(2, true),
            'access_token' => fake()->sha256(),
            'settings' => ['base_url' => fake()->url()],
            'is_active' => true,
            'connected_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
