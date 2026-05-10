<?php

namespace Tests\Unit\Services;

use App\Application\UseCases\Onboarding\CalculateDailyCaloriesUseCase;
use App\Models\UserOnboarding;
use App\Services\Diet\MacroGoalCalculator;
use PHPUnit\Framework\TestCase;

class MacroGoalCalculatorTest extends TestCase
{
    private function calculator(): MacroGoalCalculator
    {
        return new MacroGoalCalculator(new CalculateDailyCaloriesUseCase);
    }

    private function onboardingFor(array $overrides = []): UserOnboarding
    {
        $onboarding = new UserOnboarding;
        $onboarding->forceFill(array_merge([
            'gender' => 'male',
            'age' => 30,
            'height_cm' => 180,
            'weight_kg' => 80,
            'work_style' => 'moderate',
            'exercise_frequency' => 3,
        ], $overrides));

        return $onboarding;
    }

    public function test_returns_null_when_onboarding_lacks_required_fields(): void
    {
        $onboarding = $this->onboardingFor(['weight_kg' => null, 'height_cm' => null]);
        $this->assertNull($this->calculator()->calculateForObjective($onboarding, 'maintenance'));
    }

    public function test_maintenance_macros_sum_close_to_calories(): void
    {
        $result = $this->calculator()->calculateForObjective($this->onboardingFor(), 'maintenance');

        $this->assertNotNull($result);
        $this->assertGreaterThan(1500, $result['calories']);

        $kcalFromMacros = ($result['protein'] * 4) + ($result['carbs'] * 4) + ($result['fat'] * 9);
        $this->assertEqualsWithDelta($result['calories'], $kcalFromMacros, 5.0);
    }

    public function test_weight_loss_calories_below_maintenance(): void
    {
        $maint = $this->calculator()->calculateForObjective($this->onboardingFor(), 'maintenance');
        $cut = $this->calculator()->calculateForObjective($this->onboardingFor(), 'weight_loss');

        $this->assertNotNull($maint);
        $this->assertNotNull($cut);
        $this->assertLessThan($maint['calories'], $cut['calories']);
    }
}
