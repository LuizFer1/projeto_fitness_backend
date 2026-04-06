<?php

namespace Tests\Unit\Services\Gamification;

use App\Services\Gamification\LevelCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LevelCalculatorTest extends TestCase
{
    #[DataProvider('xpToLevelProvider')]
    public function test_forXp_returns_correct_level(int $xp, int $expectedLevel): void
    {
        $this->assertSame($expectedLevel, LevelCalculator::forXp($xp));
    }

    public static function xpToLevelProvider(): array
    {
        return [
            'xp 0 => level 1'       => [0, 1],
            'xp 499 => level 1'     => [499, 1],
            'xp 500 => level 2'     => [500, 2],
            'xp 1499 => level 2'    => [1499, 2],
            'xp 1500 => level 3'    => [1500, 3],
            'xp 3499 => level 3'    => [3499, 3],
            'xp 3500 => level 4'    => [3500, 4],
            'xp 7499 => level 4'    => [7499, 4],
            'xp 7500 => level 5'    => [7500, 5],
            'xp 14999 => level 5'   => [14999, 5],
            'xp 15000 => level 6'   => [15000, 6],
            'xp 99999 => level 6'   => [99999, 6],
        ];
    }

    #[DataProvider('levelToNameProvider')]
    public function test_nameFor_returns_correct_name(int $level, string $expectedName): void
    {
        $this->assertSame($expectedName, LevelCalculator::nameFor($level));
    }

    public static function levelToNameProvider(): array
    {
        return [
            'level 1 => Iniciante'    => [1, 'Iniciante'],
            'level 2 => Dedicado'     => [2, 'Dedicado'],
            'level 3 => Consistente'  => [3, 'Consistente'],
            'level 4 => Atleta'       => [4, 'Atleta'],
            'level 5 => Elite'        => [5, 'Elite'],
            'level 6 => Campeão'      => [6, 'Campeão'],
        ];
    }

    #[DataProvider('nextLevelXpProvider')]
    public function test_nextLevelXp_returns_correct_xp_needed(int $currentXp, ?int $expectedXpNeeded): void
    {
        $this->assertSame($expectedXpNeeded, LevelCalculator::nextLevelXp($currentXp));
    }

    public static function nextLevelXpProvider(): array
    {
        return [
            'xp 0 needs 500 to level 2'      => [0, 500],
            'xp 499 needs 1 to level 2'       => [499, 1],
            'xp 500 needs 1000 to level 3'    => [500, 1000],
            'xp 1499 needs 1 to level 3'      => [1499, 1],
            'xp 1500 needs 2000 to level 4'   => [1500, 2000],
            'xp 7500 needs 7500 to level 6'   => [7500, 7500],
            'xp 14999 needs 1 to level 6'     => [14999, 1],
            'xp 15000 => null (max level)'    => [15000, null],
            'xp 99999 => null (max level)'    => [99999, null],
        ];
    }
}
