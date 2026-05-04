<?php

namespace App\Domain\Workout;

/**
 * Epley 1RM formula (SRS RF-08): 1RM = w × (1 + reps / 30)
 */
class EpleyCalculator
{
    public static function calculate(float $weightKg, int $reps): float
    {
        if ($reps <= 0 || $weightKg <= 0) {
            return 0.0;
        }

        // For 1 rep, the formula returns the actual weight
        return round($weightKg * (1 + $reps / 30), 2);
    }
}
