<?php

namespace Tests\Unit\Application\UseCases\Auth;

use App\Application\Contracts\LoggerInterface;
use App\Application\UseCases\Auth\RegisterUserUseCase;
use App\Domain\User\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RegisterUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockInterface $userRepositoryMock;
    private LoggerInterface|MockInterface $loggerMock;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepositoryMock = Mockery::mock(UserRepositoryInterface::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);

        $this->useCase = new RegisterUserUseCase(
            $this->userRepositoryMock,
            $this->loggerMock
        );
    }

    public function test_it_registers_a_user_successfully()
    {
        $data = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'cpf' => '12345678901',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        Hash::shouldReceive('make')
            ->once()
            ->with('secret123')
            ->andReturn('hashed_password');

        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->uuid = 'some-uuid-123';
        
        $userMock->shouldReceive('refresh')->once();
        
        $tokenMock = Mockery::mock();
        $tokenMock->plainTextToken = 'some-token-string';
        $userMock->shouldReceive('createToken')->with('auth-token')->once()->andReturn($tokenMock);

        $this->loggerMock->shouldReceive('info')->with('Starting user registration', ['email' => 'john@example.com'])->once();
        $this->userRepositoryMock->shouldReceive('create')->once()->with(Mockery::on(function ($arg) {
            return $arg['email'] === 'john@example.com' 
                && $arg['password_hash'] === 'hashed_password'
                && !isset($arg['password']);
        }))->andReturn($userMock);

        $this->userRepositoryMock->shouldReceive('createGamificationProfile')->once()->with('some-uuid-123');
        $this->loggerMock->shouldReceive('info')->with('User registered successfully', ['user_uuid' => 'some-uuid-123'])->once();

        $result = $this->useCase->execute($data);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($userMock, $result['user']);
        $this->assertEquals('some-token-string', $result['token']);
    }
}
