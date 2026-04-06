<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(['weight_loss', 'muscle_gain', 'distance', 'calories', 'workout_frequency']),
            'target_value' => fake()->randomFloat(2, 10, 100),
            'initial_value' => 0,
            'current_value' => 0,
            'unit' => fake()->randomElement(['kg', 'km', 'kcal', 'sessions']),
            'deadline' => fake()->optional()->dateTimeBetween('+1 week', '+6 months'),
            'visibility' => 'private',
            'status' => 'active',
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => 'public']);
    }

    public function friends(): static
    {
        return $this->state(['visibility' => 'friends']);
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}
