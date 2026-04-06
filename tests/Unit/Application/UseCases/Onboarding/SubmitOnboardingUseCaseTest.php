<?php

namespace Tests\Unit\Application\UseCases\Onboarding;

use App\Application\Contracts\LoggerInterface;
use App\Application\UseCases\Onboarding\CalculateDailyCaloriesUseCase;
use App\Application\UseCases\Onboarding\SubmitOnboardingUseCase;
use App\Domain\Nutrition\NutritionRepositoryInterface;
use App\Domain\Onboarding\OnboardingRepositoryInterface;
use App\Models\UserOnboarding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SubmitOnboardingUseCaseTest extends TestCase
{
    private OnboardingRepositoryInterface|MockInterface $onboardingRepositoryMock;
    private NutritionRepositoryInterface|MockInterface $nutritionRepositoryMock;
    private CalculateDailyCaloriesUseCase|MockInterface $calculateCaloriesMock;
    private LoggerInterface|MockInterface $loggerMock;
    private SubmitOnboardingUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onboardingRepositoryMock = Mockery::mock(OnboardingRepositoryInterface::class);
        $this->nutritionRepositoryMock = Mockery::mock(NutritionRepositoryInterface::class);
        $this->calculateCaloriesMock = Mockery::mock(CalculateDailyCaloriesUseCase::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);

        $this->useCase = new SubmitOnboardingUseCase(
            $this->onboardingRepositoryMock,
            $this->nutritionRepositoryMock,
            $this->calculateCaloriesMock,
            $this->loggerMock
        );
    }

    public function test_it_throws_if_onboarding_already_exists()
    {
        $this->loggerMock->shouldReceive('info')->once();
        
        $this->onboardingRepositoryMock->shouldReceive('hasOnboarding')
            ->with('user-uuid-123')
            ->once()
            ->andReturn(true);

        $this->loggerMock->shouldReceive('warning')->once();

        $this->expectException(ValidationException::class);

        $this->useCase->execute('user-uuid-123', []);
    }

    public function test_it_successfully_creates_onboarding()
    {
        $this->loggerMock->shouldReceive('info')->with('Starting onboarding submission', ['user_uuid' => 'user-uuid-123'])->once();

        $this->onboardingRepositoryMock->shouldReceive('hasOnboarding')
            ->with('user-uuid-123')
            ->once()
            ->andReturn(false);

        $this->calculateCaloriesMock->shouldReceive('execute')
            ->with(['gender' => 'M'])
            ->once()
            ->andReturn(['bmr' => 1500, 'tdee' => 2200]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) {
                return $closure();
            });

        $onboardingMock = Mockery::mock(UserOnboarding::class);

        $this->onboardingRepositoryMock->shouldReceive('updateOrCreate')
            ->with('user-uuid-123', Mockery::on(function ($arg) {
                return $arg['completed'] === true && $arg['bmr'] === 1500 && $arg['gender'] === 'M';
            }))
            ->once()
            ->andReturn($onboardingMock);

        $this->nutritionRepositoryMock->shouldReceive('createOrUpdateDailyGoal')
            ->with('user-uuid-123', now()->toDateString(), 2200)
            ->once();

        $this->loggerMock->shouldReceive('info')->with('Daily nutrition goal created', ['user_uuid' => 'user-uuid-123', 'tdee' => 2200])->once();

        $result = $this->useCase->execute('user-uuid-123', ['gender' => 'M']);

        $this->assertEquals($onboardingMock, $result);
    }
}
