<?php

namespace App\Application\UseCases\Auth;

use App\Application\Contracts\LoggerInterface;
use App\Domain\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserUseCase
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
     * Executes the login use case.
     *
     * @param array $credentials
     * @return array
     * @throws ValidationException
     */
    public function execute(array $credentials): array
    {
        $this->logger->info('Attempting user login', ['email' => $credentials['email']]);

        $user = $this->userRepository->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
            $this->logger->warning('Failed login attempt', ['email' => $credentials['email']]);
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->logger->info('User logged in successfully', ['user_uuid' => $user->uuid]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
