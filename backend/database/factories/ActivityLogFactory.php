<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['user.created', 'user.updated', 'trip.created', 'trip.updated', 'place.added']),
            'details' => [
                'ip' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'data' => fake()->sentence(),
            ],
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Create an activity log for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create an activity log with a specific action.
     */
    public function withAction(string $action): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $action,
        ]);
    }

    /**
     * Create an activity log with custom details.
     */
    public function withDetails(array $details): static
    {
        return $this->state(fn (array $attributes) => [
            'details' => $details,
        ]);
    }
}
