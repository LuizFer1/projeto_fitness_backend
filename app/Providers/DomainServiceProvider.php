<?php

namespace App\Providers;

use App\Application\Contracts\LoggerInterface;
use App\Domain\Nutrition\NutritionRepositoryInterface;
use App\Domain\Onboarding\OnboardingRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Logging\AppLogger;
use App\Infrastructure\Repositories\Nutrition\EloquentNutritionRepository;
use App\Infrastructure\Repositories\Onboarding\EloquentOnboardingRepository;
use App\Infrastructure\Repositories\User\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LoggerInterface::class, AppLogger::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(OnboardingRepositoryInterface::class, EloquentOnboardingRepository::class);
        $this->app->bind(NutritionRepositoryInterface::class, EloquentNutritionRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
