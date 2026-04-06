<?php

namespace App\Domain\User;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Store a new user in the database.
     */
    public function create(array $data): User;

    /**
     * Find a user by their email address.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a gamification profile for a given user UUID.
     */
    public function createGamificationProfile(string $userUuid): void;
}
