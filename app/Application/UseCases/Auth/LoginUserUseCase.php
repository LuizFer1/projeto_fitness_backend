<?php

namespace App\Application\UseCases\Auth;

use App\Application\Contracts\LoggerInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserUseCase
{
    private const LOCAL_BYPASS_EMAIL = 'teste@coreva.com';

    private const LOCAL_BYPASS_PASSWORD = 'testeCoreva';

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
     * @throws ValidationException
     */
    public function execute(array $credentials): array
    {
        $this->logger->info('Attempting user login', ['email' => $credentials['email']]);

        if ($bypass = $this->tryLocalBypass($credentials)) {
            return $bypass;
        }

        $user = $this->userRepository->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
            $this->logger->warning('Failed login attempt', ['email' => $credentials['email']]);
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Eager-load relations so the frontend already has onboarding/gamification
        // state on the very first request, without depending on a follow-up /me roundtrip.
        $user->load(['onboarding', 'gamification']);

        $this->logger->info('User logged in successfully', ['user_uuid' => $user->id]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Localhost-only shortcut: skip password verification for a hardcoded
     * test account, auto-provisioning the user on first use.
     */
    private function tryLocalBypass(array $credentials): ?array
    {
        if (! app()->environment('local')) {
            return null;
        }

        if (($credentials['email'] ?? null) !== self::LOCAL_BYPASS_EMAIL
            || ($credentials['password'] ?? null) !== self::LOCAL_BYPASS_PASSWORD) {
            return null;
        }

        $user = $this->userRepository->findByEmail(self::LOCAL_BYPASS_EMAIL);

        if (! $user) {
            $user = $this->userRepository->create([
                'name' => 'Teste',
                'last_name' => 'Coreva',
                'email' => self::LOCAL_BYPASS_EMAIL,
                'cpf' => '000.000.000-00',
                'password_hash' => Hash::make(self::LOCAL_BYPASS_PASSWORD),
            ]);
            $user->refresh();

            try {
                $this->userRepository->createGamificationProfile($user->id);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to create gamification profile for bypass user', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->load(['onboarding', 'gamification']);

        $this->logger->warning('Local auth bypass used', ['email' => self::LOCAL_BYPASS_EMAIL]);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
