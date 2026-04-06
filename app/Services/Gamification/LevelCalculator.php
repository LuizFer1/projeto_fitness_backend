<?php

namespace App\Services\Gamification;

class LevelCalculator
{
    private const THRESHOLDS = [
        1 => 0,
        2 => 500,
        3 => 1500,
        4 => 3500,
        5 => 7500,
        6 => 15000,
    ];

    private const NAMES = [
        1 => 'Iniciante',
        2 => 'Dedicado',
        3 => 'Consistente',
        4 => 'Atleta',
        5 => 'Elite',
        6 => 'Campeão',
    ];

    public static function forXp(int $xp): int
    {
        $level = 1;

        foreach (self::THRESHOLDS as $lvl => $threshold) {
            if ($xp >= $threshold) {
                $level = $lvl;
            }
        }

        return $level;
    }

    public static function nameFor(int $level): string
    {
        return self::NAMES[$level] ?? 'Desconhecido';
    }

    public static function nextLevelXp(int $currentXp): ?int
    {
        $currentLevel = self::forXp($currentXp);

        if ($currentLevel >= 6) {
            return null;
        }

        return self::THRESHOLDS[$currentLevel + 1] - $currentXp;
    }
}
