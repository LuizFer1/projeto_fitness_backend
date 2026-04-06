<?php

namespace Tests\Unit\Application\UseCases\Onboarding;

use App\Application\UseCases\Onboarding\CalculateDailyCaloriesUseCase;
use PHPUnit\Framework\TestCase;

class CalculateDailyCaloriesUseCaseTest extends TestCase
{
    private CalculateDailyCaloriesUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new CalculateDailyCaloriesUseCase();
    }

    public function test_it_calculates_bmr_and_tdee_for_male()
    {
        $data = [
            'gender' => 'M', // 10 * 80 + 6.25 * 180 - 5 * 25 + 5 = 800 + 1125 - 125 + 5 = 1805
            'age' => 25,
            'weight_kg' => 80,
            'height_cm' => 180,
            'workouts_per_week' => 4, // Multiplier 1.55
            'work_style' => 'sedentary'
        ];

        $result = $this->useCase->execute($data);

        $this->assertEquals(1805, $result['bmr']);
        
        // 1805 * 1.55 = 2797.75 -> round to 2798
        $this->assertEquals(2798, $result['tdee']);
    }

    public function test_it_calculates_bmr_and_tdee_for_female_active()
    {
        // 10*60 + 6.25*165 - 5*30 - 161 = 600 + 1031.25 - 150 - 161 = 1320.25
        $data = [
            'gender' => 'F', 
            'age' => 30,
            'weight_kg' => 60.0,
            'height_cm' => 165,
            'workouts_per_week' => 1, // Multiplier 1.375
            'work_style' => 'active' // + 0.15 = 1.525
        ];

        $result = $this->useCase->execute($data);

        $this->assertEquals(1320, $result['bmr']); // 1320.25 rounded
        
        // 1320.25 * 1.525 = 2013.38125 -> 2013
        $this->assertEquals(2013, $result['tdee']);
    }

    public function test_it_returns_null_when_data_is_incomplete()
    {
        $data = [
            'gender' => 'M',
            'weight_kg' => 80,
        ];

        $result = $this->useCase->execute($data);

        $this->assertNull($result['bmr']);
        $this->assertNull($result['tdee']);
    }
}
