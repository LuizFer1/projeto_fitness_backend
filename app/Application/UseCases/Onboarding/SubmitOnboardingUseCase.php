<?php

namespace App\Application\UseCases\Onboarding;

use App\Application\Contracts\LoggerInterface;
use App\Domain\Nutrition\NutritionRepositoryInterface;
use App\Domain\Onboarding\OnboardingRepositoryInterface;
use App\Models\UserOnboarding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitOnboardingUseCase
{
    private OnboardingRepositoryInterface $onboardingRepository;
    private NutritionRepositoryInterface $nutritionRepository;
    private CalculateDailyCaloriesUseCase $calculateCaloriesUseCase;
    private LoggerInterface $logger;

    public function __construct(
        OnboardingRepositoryInterface $onboardingRepository,
        NutritionRepositoryInterface $nutritionRepository,
        CalculateDailyCaloriesUseCase $calculateCaloriesUseCase,
        LoggerInterface $logger
    ) {
        $this->onboardingRepository = $onboardingRepository;
        $this->nutritionRepository = $nutritionRepository;
        $this->calculateCaloriesUseCase = $calculateCaloriesUseCase;
        $this->logger = $logger;
    }

    /**
     * Orchestrates the onboarding process.
     *
     * @param string $userUuid
     * @param array $data
     * @return UserOnboarding
     * @throws ValidationException
     */
    public function execute(string $userUuid, array $data): UserOnboarding
    {
        $this->logger->info('Starting onboarding submission', ['user_uuid' => $userUuid]);

        if ($this->onboardingRepository->hasOnboarding($userUuid)) {
            $this->logger->warning('Onboarding already exists for user', ['user_uuid' => $userUuid]);
            throw ValidationException::withMessages([
                'onboarding' => ['Onboarding already exists for this user.'],
            ]);
        }

        // Domain logic calculation
        $caloriesData = $this->calculateCaloriesUseCase->execute($data);
        $bmr = $caloriesData['bmr'];
        $tdee = $caloriesData['tdee'];

        $onboardingData = array_merge($data, [
            'completed' => true,
            'bmr'       => $bmr,
        ]);

        return DB::transaction(function () use ($userUuid, $onboardingData, $tdee) {
            $onboarding = $this->onboardingRepository->updateOrCreate($userUuid, $onboardingData);

            if ($tdee) {
                // We create the daily nutrition goal for today
                $today = now()->toDateString();
                $this->nutritionRepository->createOrUpdateDailyGoal($userUuid, $today, $tdee);
                $this->logger->info('Daily nutrition goal created', ['user_uuid' => $userUuid, 'tdee' => $tdee]);
            }

            return $onboarding;
        });
    }
}
