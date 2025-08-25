<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserExchange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserExchange>
 */
class UserExchangeFactory extends Factory
{
    protected $model = UserExchange::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exchange' => $this->faker->randomElement(['bybit', 'mexc']),
            'api_credentials_encrypted' => Crypt::encryptString(json_encode([
                'api_key' => $this->faker->uuid,
                'secret' => $this->faker->sha256,
            ])),
            'is_active' => $this->faker->boolean(80),
            'last_sync_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'sync_settings' => [
                'auto_sync' => $this->faker->boolean(70),
                'sync_interval_hours' => $this->faker->numberBetween(1, 24),
            ],
        ];
    }

    /**
     * Indicate that the exchange is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the exchange is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the exchange to Bybit.
     */
    public function bybit(): static
    {
        return $this->state(fn(array $attributes) => [
            'exchange' => 'bybit',
        ]);
    }

    /**
     * Set the exchange to MEXC.
     */
    public function mexc(): static
    {
        return $this->state(fn(array $attributes) => [
            'exchange' => 'mexc',
        ]);
    }

    /**
     * Set specific API credentials.
     */
    public function withCredentials(string $apiKey, string $secret): static
    {
        return $this->state(fn(array $attributes) => [
            'api_credentials_encrypted' => Crypt::encryptString(json_encode([
                'api_key' => $apiKey,
                'secret' => $secret,
            ])),
        ]);
    }

    /**
     * Set recently synced.
     */
    public function recentlySync(): static
    {
        return $this->state(fn(array $attributes) => [
            'last_sync_at' => now()->subMinutes($this->faker->numberBetween(5, 30)),
        ]);
    }

    /**
     * Set needs sync.
     */
    public function needsSync(): static
    {
        return $this->state(fn(array $attributes) => [
            'last_sync_at' => now()->subHours($this->faker->numberBetween(2, 48)),
        ]);
    }
}