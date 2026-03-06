<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'          => fake()->firstName(),
            'last_name'     => fake()->lastName(),
            'email'         => fake()->unique()->safeEmail(),
            'cpf'           => fake()->unique()->numerify('###.###.###-##'),
            'password_hash' => static::$password ??= Hash::make('password'),
            'avatar_url'    => fake()->optional()->imageUrl(200, 200, 'people'),
        ];
    }
}
