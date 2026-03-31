<?php

namespace App\Infrastructure\Repositories\User;

use App\Domain\User\UserRepositoryInterface;
use App\Models\User;
use App\Models\UserGamification;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function createGamificationProfile(string $userUuid): void
    {
        UserGamification::create(['user_uuid' => $userUuid]);
    }
}
