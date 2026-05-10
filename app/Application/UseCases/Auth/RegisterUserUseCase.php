<?php

namespace App\Application\UseCases\Auth;

use App\Application\Contracts\LoggerInterface;
use App\Domain\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class RegisterUserUseCase
{
    private UserRepositoryInterface $userRepository;

    private LoggerInterface $logger;

    public function __construct(
        UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    /**
     * Executes the registration use case.
     */
    public function execute(array $data): array
    {
        $this->logger->info('Starting user registration', ['email' => $data['email']]);

        // Encode password before saving
        $data['password_hash'] = Hash::make($data['password']);

        // Remove plain text password from array to avoid attempting to insert it if it was passed
        unset($data['password']);
        // and password_confirmation if present
        unset($data['password_confirmation']);

        $user = $this->userRepository->create($data);
        $user->refresh();

        // Create gamification profile. The User PK is a UUID stored on `id`,
        // so `$user->id` is the value we want — `$user->uuid` is null (no such column).
        try {
            $this->userRepository->createGamificationProfile($user->id);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to create gamification profile', [
                'user_uuid' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Create token (Sanctum)
        $token = $user->createToken('auth-token')->plainTextToken;

        $user->load(['onboarding', 'gamification']);

        $this->logger->info('User registered successfully', ['user_uuid' => $user->id]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
