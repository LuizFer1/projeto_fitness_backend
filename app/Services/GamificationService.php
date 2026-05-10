<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyActivityLimit;
use App\Models\MealLog;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserGamification;
use App\Models\WorkoutLog;
use App\Models\XpTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamificationService
{
    // ── Level thresholds — SRS v1.0 section 6.2 (8 levels) ───────────
    private const LEVEL_THRESHOLDS = [
        1 => 0,
        2 => 500,
        3 => 1500,
        4 => 4000,
        5 => 10000,
        6 => 25000,
        7 => 60000,
        8 => 150000,
    ];

    // ── Legacy constants (kept for backward compat with old grants) ───
    private const PENALTY_CALORIES = 15;

    private const PENALTY_WORKOUT = 50;

    // ================================================================
    //  LEGACY GRANTS (backward-compatible)
    // ================================================================

    public function grantDailyLoginXp(User $user): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->login_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit) {
            $tx = $this->creditXpFromConfig($user, 'daily_login', 'Login diário', $today);
            $limit->update(['login_xp_granted' => true]);
            $this->touchActivity($user, $today);

            return $tx;
        });
    }

    public function grantMealLoggedXp(User $user): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->meal_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit) {
            $tx = $this->creditXpFromConfig($user, 'meal_logged', 'Registro de refeição', $today);
            $limit->update(['meal_xp_granted' => true]);
            $this->touchActivity($user, $today);

            return $tx;
        });
    }

    public function grantWorkoutCompletedXp(User $user, string $workoutLogId): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->workout_count >= 1) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit, $workoutLogId) {
            $tx = $this->creditXpFromConfig(
                $user, 'workout_strength', 'Treino concluído', $today,
                $workoutLogId, 'workout_logs', 'workout_completed'
            );
            $limit->increment('workout_count');
            $this->gamification($user)->increment('total_workouts');
            $this->touchActivity($user, $today);

            return $tx;
        });
    }

    public function grantWeightLoggedXp(User $user): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->weight_logged) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit) {
            $tx = $this->creditXpFromConfig($user, 'weight_logged', 'Registro de peso', $today);
            $limit->update(['weight_logged' => true]);
            $this->touchActivity($user, $today);

            return $tx;
        });
    }

    // ================================================================
    //  SRS NEW GRANTS — section 6.1
    // ================================================================

    /** +60 XP base (up to 120) when the user ends the day within calorie target. */
    public function grantCleanDietDayXp(User $user, string $date): ?XpTransaction
    {
        $limit = $this->getOrCreateDailyLimit($user, $date);

        if ($limit->clean_diet_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $date, $limit) {
            $tx = $this->creditXpFromConfig($user, 'clean_diet_day', 'Dia limpo de dieta', $date);
            $limit->update(['clean_diet_xp_granted' => true]);

            return $tx;
        });
    }

    /** +50 XP base (up to 75) when daily protein goal is met. */
    public function grantProteinGoalXp(User $user, string $date): ?XpTransaction
    {
        $limit = $this->getOrCreateDailyLimit($user, $date);

        if ($limit->protein_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $date, $limit) {
            $tx = $this->creditXpFromConfig($user, 'protein_goal_met', 'Meta de proteína atingida', $date);
            $limit->update(['protein_xp_granted' => true]);

            return $tx;
        });
    }

    /** +30 XP base (up to 39) when daily water goal is met. */
    public function grantWaterGoalXp(User $user, string $date): ?XpTransaction
    {
        $limit = $this->getOrCreateDailyLimit($user, $date);

        if ($limit->water_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $date, $limit) {
            $tx = $this->creditXpFromConfig($user, 'water_goal_met', 'Meta de água atingida', $date);
            $limit->update(['water_xp_granted' => true]);

            return $tx;
        });
    }

    /** +80–112 XP for a completed cardio session (GPS or manual distance). */
    public function grantCardioCompletedXp(User $user, string $workoutLogId): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->cardio_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit, $workoutLogId) {
            $tx = $this->creditXpFromConfig(
                $user, 'workout_cardio', 'Treino de cárdio concluído', $today,
                $workoutLogId, 'workout_logs'
            );
            $limit->update(['cardio_xp_granted' => true]);
            $this->gamification($user)->increment('total_workouts');
            $this->touchActivity($user, $today);

            return $tx;
        });
    }

    /** +20 XP (fixed) for registering a progress photo. */
    public function grantProgressPhotoXp(User $user): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->photo_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit) {
            $tx = $this->creditXpFromConfig($user, 'progress_photo', 'Foto de progresso registrada', $today);
            $limit->update(['photo_xp_granted' => true]);

            return $tx;
        });
    }

    /** +15 XP (up to 30/day) for sharing a victory asset. */
    public function grantAssetSharedXp(User $user, string $refId): ?XpTransaction
    {
        $today = $this->userToday($user);
        $config = config('gamification.events.asset_shared');

        // Count today's asset shares
        $sharedToday = XpTransaction::where('user_id', $user->id)
            ->where('type', 'asset_shared')
            ->where('date', $today)
            ->sum('xp_gained');

        if ($sharedToday >= $config['cap']) {
            return null;
        }

        return $this->creditXpFromConfig(
            $user, 'asset_shared', 'Asset de vitória compartilhado', $today,
            $refId, 'workout_logs'
        );
    }

    /** +200 XP unique bonus when a new personal record is set. */
    public function grantPrSetXp(User $user, string $exerciseId): ?XpTransaction
    {
        $today = $this->userToday($user);

        return DB::transaction(function () use ($user, $today, $exerciseId) {
            $tx = $this->creditXpFromConfig(
                $user, 'pr_set', 'Record pessoal de 1RM', $today,
                $exerciseId, 'exercises'
            );
            $this->awardBadge($user, 'pr_broken');

            return $tx;
        });
    }

    // ================================================================
    //  END-OF-DAY / END-OF-WEEK PROCESSING
    // ================================================================

    public function processEndOfDay(User $user, string $dateString): void
    {
        $date = Carbon::parse($dateString);
        $gam = $this->gamification($user);
        $isoWeek = $date->format('o-\WW');

        $hadActivity = DailyActivityLimit::where('user_id', $user->id)
            ->where('date', $dateString)
            ->where(function ($q) {
                $q->where('login_xp_granted', true)
                    ->orWhere('meal_xp_granted', true)
                    ->orWhere('workout_count', '>', 0);
            })
            ->exists();

        if ($hadActivity) {
            $newStreak = $gam->current_streak + 1;
            $gam->update([
                'current_streak' => $newStreak,
                'max_streak' => max($gam->max_streak, $newStreak),
                'last_activity' => $dateString,
            ]);

            if ($newStreak > 0 && $newStreak % 7 === 0) {
                $this->applyStreakBonus($user, $date, $newStreak);
            }

            $this->checkStreakBadges($user, $newStreak);
            $this->checkCaloriePenalty($user, $dateString);
        } else {
            $usedSafetyThisWeek = ($gam->last_week_safety_day_used === $isoWeek);

            if (! $usedSafetyThisWeek) {
                $gam->update(['last_week_safety_day_used' => $isoWeek]);
                Log::info("Gamification: Safety day consumed for user {$user->id} on {$dateString}");
            } else {
                $gam->update(['current_streak' => 0]);
                $this->applyCaloriePenalty($user, $dateString);
            }
        }

        $gam->update(['last_processed_date' => $dateString]);
    }

    public function processEndOfWeek(User $user, string $sundayDateString): void
    {
        $sunday = Carbon::parse($sundayDateString);
        $monday = $sunday->copy()->startOfWeek(Carbon::MONDAY);
        $isoWeek = $sunday->format('o-\WW');

        $weekWorkouts = WorkoutLog::where('user_id', $user->id)
            ->whereBetween('date', [$monday->toDateString(), $sundayDateString])
            ->count();

        if ($weekWorkouts >= 3) {
            return;
        }

        $alreadyPenalized = XpTransaction::where('user_id', $user->id)
            ->where('type', 'penalty_workout')
            ->where('description', 'like', "%{$isoWeek}%")
            ->exists();

        if ($alreadyPenalized) {
            return;
        }

        $penalty = config('gamification.penalties.weekly_workouts', self::PENALTY_WORKOUT);
        $this->debitXp(
            $user, 'penalty_workout', $penalty,
            "Penalidade semanal de treinos ({$isoWeek}): {$weekWorkouts}/3 treinos",
            $sundayDateString
        );
    }

    // ================================================================
    //  LEVEL UP CHECK
    // ================================================================

    public function checkLevelUp(User $user): bool
    {
        $gam = $this->gamification($user);
        $xp = $gam->xp_total;

        $newLevel = 1;
        foreach (self::LEVEL_THRESHOLDS as $level => $minXp) {
            if ($xp >= $minXp) {
                $newLevel = $level;
            }
        }

        if ($newLevel <= $gam->current_level) {
            return false;
        }

        $nextXp = self::LEVEL_THRESHOLDS[$newLevel + 1] ?? null;
        $xpToNext = $nextXp ? ($nextXp - $xp) : 0;

        $gam->update([
            'current_level' => $newLevel,
            'xp_to_next' => max(0, $xpToNext),
        ]);

        $this->checkLevelBadges($user, $newLevel);

        Log::info("Gamification: User {$user->id} leveled up to {$newLevel}");

        return true;
    }

    // ================================================================
    //  BADGE / ACHIEVEMENT AWARD (idempotent)
    // ================================================================

    public function awardBadge(User $user, string $slug): ?UserAchievement
    {
        $achievement = Achievement::where('slug', $slug)->where('is_active', true)->first();
        if (! $achievement) {
            return null;
        }

        $exists = UserAchievement::where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($user, $achievement) {
            $ua = UserAchievement::create([
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'xp_received' => $achievement->xp_reward,
                'is_notified' => false,
            ]);

            if ($achievement->xp_reward > 0) {
                $this->creditXp(
                    $user, 'achievement_unlocked', $achievement->xp_reward,
                    "Badge: {$achievement->name}",
                    $this->userToday($user),
                    $achievement->id, 'achievements'
                );
            }

            return $ua;
        });
    }

    public function checkWorkoutBadges(User $user): void
    {
        $gam = $this->gamification($user);
        $milestones = [10 => 'treinos_10', 50 => 'treinos_50', 100 => 'treinos_100'];

        foreach ($milestones as $count => $slug) {
            if ($gam->total_workouts >= $count) {
                $this->awardBadge($user, $slug);
            }
        }
    }

    // ================================================================
    //  PRIVATE HELPERS
    // ================================================================

    /**
     * Credits XP using the config/gamification.php event table.
     * Applies SRS per-event streak multiplier formula.
     */
    private function creditXpFromConfig(
        User $user, string $eventKey, string $description,
        string $date, ?string $refId = null, ?string $refTable = null,
        ?string $typeOverride = null
    ): XpTransaction {
        $cfg = config("gamification.events.{$eventKey}", []);
        $base = $cfg['base'] ?? 10;
        $maxMult = $cfg['max_mult'] ?? 1.0;
        $fullAt = $cfg['full_at_days'] ?? 1;
        $cap = $cfg['cap'] ?? $base;

        $streak = $this->gamification($user)->current_streak;
        $multiplier = $this->calcEventMultiplier($streak, $maxMult, $fullAt);
        $amount = (int) min(round($base * $multiplier), $cap);

        $bonusPct = $multiplier > 1.0 ? ' (streak +'.round(($multiplier - 1) * 100).'%)' : '';

        return $this->creditXp(
            $user, $typeOverride ?? $eventKey, $amount,
            $description.$bonusPct,
            $date, $refId, $refTable
        );
    }

    /** Base XP credit — updates totals and checks level up. */
    private function creditXp(
        User $user, string $type, int $amount, string $description,
        string $date, ?string $refId = null, ?string $refTable = null
    ): XpTransaction {
        $gam = $this->gamification($user);

        $gam->increment('xp_total', $amount);
        $gam->increment('current_week_xp', $amount);
        $gam->increment('current_month_xp', $amount);
        $gam->refresh();

        $tx = XpTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'xp_gained' => $amount,
            'description' => $description,
            'ref_id' => $refId,
            'ref_table' => $refTable,
            'date' => $date,
            'xp_total_snapshot' => $gam->xp_total,
        ]);

        $this->checkLevelUp($user);

        return $tx;
    }

    /** Debit XP — ensures non-negative balance. */
    private function debitXp(User $user, string $type, int $amount, string $description, string $date): ?XpTransaction
    {
        $gam = $this->gamification($user);

        if ($gam->xp_total <= 0) {
            return null;
        }

        $actual = min($amount, $gam->xp_total);
        $gam->decrement('xp_total', $actual);
        $gam->decrement('current_week_xp', min($actual, $gam->current_week_xp));
        $gam->decrement('current_month_xp', min($actual, $gam->current_month_xp));
        $gam->refresh();

        return XpTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'xp_gained' => -$actual,
            'description' => $description,
            'date' => $date,
            'xp_total_snapshot' => $gam->xp_total,
        ]);
    }

    /**
     * SRS per-event multiplier: linear ramp from 1.0 to max_mult
     * over full_at_days consecutive days.
     */
    private function calcEventMultiplier(int $streak, float $maxMult, int $fullAt): float
    {
        if ($maxMult <= 1.0 || $fullAt <= 0) {
            return 1.0;
        }
        $ratio = min($streak / $fullAt, 1.0);

        return 1.0 + $ratio * ($maxMult - 1.0);
    }

    private function checkCaloriePenalty(User $user, string $dateString): void
    {
        $alreadyPenalized = XpTransaction::where('user_id', $user->id)
            ->where('type', 'penalty_calories')
            ->where('date', $dateString)
            ->exists();

        if ($alreadyPenalized) {
            return;
        }

        $goal = $user->goal;
        if (! $goal || ! $goal->goal_calories_day) {
            return;
        }

        $totalCalories = MealLog::where('user_id', $user->id)
            ->where('date', $dateString)
            ->sum('calories_consumed');

        if ($totalCalories >= $goal->goal_calories_day) {
            return;
        }

        $penalty = config('gamification.penalties.calories_missed', self::PENALTY_CALORIES);
        $this->debitXp($user, 'penalty_calories', $penalty,
            "Meta calórica não atingida ({$dateString}): {$totalCalories}/{$goal->goal_calories_day} kcal",
            $dateString
        );
    }

    private function applyCaloriePenalty(User $user, string $dateString): void
    {
        $alreadyPenalized = XpTransaction::where('user_id', $user->id)
            ->where('type', 'penalty_calories')
            ->where('date', $dateString)
            ->exists();

        if ($alreadyPenalized) {
            return;
        }

        $penalty = config('gamification.penalties.calories_missed', self::PENALTY_CALORIES);
        $this->debitXp($user, 'penalty_calories', $penalty,
            "Meta calórica não atingida - dia ausente ({$dateString})",
            $dateString
        );
    }

    private function applyStreakBonus(User $user, Carbon $date, int $streakDay): void
    {
        $bonusXp = (int) round(config('gamification.events.daily_login.base', 10) * 0.10 * $streakDay / 7);

        if ($bonusXp > 0) {
            $this->creditXp($user, 'streak_bonus', $bonusXp,
                "Bônus de streak: {$streakDay} dias consecutivos",
                $date->toDateString()
            );
        }
    }

    private function checkStreakBadges(User $user, int $streak): void
    {
        // Legacy slugs preserved; new SRS slugs mapped below
        $milestones = [
            7 => ['streak_7',    'clean_week'],
            30 => ['streak_30',   'armored_month'],
            90 => ['streak_90'],
            365 => ['evofit_legendary'],
        ];

        foreach ($milestones as $days => $slugs) {
            if ($streak >= $days) {
                foreach ($slugs as $slug) {
                    $this->awardBadge($user, $slug);
                }
            }
        }
    }

    private function checkLevelBadges(User $user, int $level): void
    {
        if ($level >= 8) {
            $this->awardBadge($user, 'the_immortal');
        }
    }

    private function getOrCreateDailyLimit(User $user, string $date): DailyActivityLimit
    {
        return DailyActivityLimit::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['daily_xp_limit' => config('gamification.daily_cap', 300)]
        );
    }

    private function touchActivity(User $user, string $date): void
    {
        $gam = $this->gamification($user);
        if (! $gam->last_activity || $gam->last_activity->toDateString() < $date) {
            $gam->update(['last_activity' => $date]);
        }
    }

    private function gamification(User $user): UserGamification
    {
        if ($user->gamification) {
            return $user->gamification;
        }

        // Eloquent's `create()` returns a model populated only with the attributes
        // we passed — DB-level column defaults (current_streak=0, xp_total=0, …)
        // are NOT hydrated unless we refresh, so callers would otherwise read null
        // from typed columns and explode in calcEventMultiplier(int $streak, …).
        $gam = UserGamification::create(['user_id' => $user->id]);

        return $gam->refresh();
    }

    private function userToday(User $user): string
    {
        return Carbon::now($user->timezone ?? 'UTC')->toDateString();
    }
}
