<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyActivityLimit;
use App\Models\MealLog;
use App\Models\UserAchievement;
use App\Models\UserGamification;
use App\Models\WorkoutLog;
use App\Models\XpTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamificationService
{
    // ── XP Constants ──────────────────────────────────────────────
    private const XP_LOGIN          = 10;
    private const XP_MEAL_LOGGED    = 20;
    private const XP_WORKOUT        = 30;
    private const PENALTY_CALORIES  = 15;
    private const PENALTY_WORKOUT   = 50;
    private const STREAK_BONUS_PCT  = 0.10;   // +10% per 7 days
    private const STREAK_BONUS_CAP  = 0.50;   // max +50%

    // ── Level Thresholds (from level_definitions seed) ────────────
    private const LEVEL_THRESHOLDS = [
        1  => 0,
        2  => 200,
        3  => 500,
        4  => 900,
        5  => 1500,
        6  => 2200,
        7  => 3100,
        8  => 4300,
        9  => 5700,
        10 => 8100,
    ];

    // ================================================================
    //  RF-01 — Daily Login XP (+10)
    // ================================================================
    public function grantDailyLoginXp(User $user): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->login_xp_granted) {
            return null; // already granted today
        }

        return DB::transaction(function () use ($user, $today, $limit) {
            $tx = $this->creditXp($user, 'daily_login', self::XP_LOGIN, 'Login diário', $today);
            $limit->update(['login_xp_granted' => true]);
            $this->touchActivity($user, $today);
            return $tx;
        });
    }

    // ================================================================
    //  RF-01 — Meal Logged XP (+20, once/day)
    // ================================================================
    public function grantMealLoggedXp(User $user): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        if ($limit->meal_xp_granted) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit) {
            $tx = $this->creditXp($user, 'meal_logged', self::XP_MEAL_LOGGED, 'Registro de refeição', $today);
            $limit->update(['meal_xp_granted' => true]);
            $this->touchActivity($user, $today);
            return $tx;
        });
    }

    // ================================================================
    //  RF-01 — Workout Completed XP (+30, once/day)
    // ================================================================
    public function grantWorkoutCompletedXp(User $user, string $workoutLogId): ?XpTransaction
    {
        $today = $this->userToday($user);
        $limit = $this->getOrCreateDailyLimit($user, $today);

        // Max 1 workout XP per day
        if ($limit->workout_count >= 1) {
            return null;
        }

        return DB::transaction(function () use ($user, $today, $limit, $workoutLogId) {
            $tx = $this->creditXp(
                $user, 'workout_completed', self::XP_WORKOUT,
                'Treino concluído', $today, $workoutLogId, 'workout_logs'
            );
            $limit->increment('workout_count');
            $gam = $this->gamification($user);
            $gam->increment('total_workouts');
            $this->touchActivity($user, $today);
            return $tx;
        });
    }

    // ================================================================
    //  RF-02 — Streak Bonus (called by end-of-day cron)
    // ================================================================
    public function processEndOfDay(User $user, string $dateString): void
    {
        $date     = Carbon::parse($dateString);
        $gam      = $this->gamification($user);
        $isoWeek  = $date->format('o-\WW'); // ISO week e.g. 2026-W12

        // Did the user perform any activity that day?
        $hadActivity = DailyActivityLimit::where('user_id', $user->id)
            ->where('date', $dateString)
            ->where(function ($q) {
                $q->where('login_xp_granted', true)
                  ->orWhere('meal_xp_granted', true)
                  ->orWhere('workout_count', '>', 0);
            })
            ->exists();

        if ($hadActivity) {
            // ── Streak continues ──
            $newStreak = $gam->current_streak + 1;
            $gam->update([
                'current_streak' => $newStreak,
                'max_streak'     => max($gam->max_streak, $newStreak),
                'last_activity'  => $dateString,
            ]);

            // ── RF-02: Streak Bonus every 7 days ──
            if ($newStreak > 0 && $newStreak % 7 === 0) {
                $this->applyStreakBonus($user, $date, $newStreak);
            }

            // ── RF-08: Streak Badges ──
            $this->checkStreakBadges($user, $newStreak);
        } else {
            // ── No activity — try safety day (RF-03) ──
            $usedSafetyThisWeek = ($gam->last_week_safety_day_used === $isoWeek);

            if (!$usedSafetyThisWeek) {
                // Consume safety day — streak preserved, penalties exempted
                $gam->update(['last_week_safety_day_used' => $isoWeek]);
                Log::info("Gamification: Safety day consumed for user {$user->id} on {$dateString}");
                // No penalty, streak unchanged
            } else {
                // No safety day available — streak reset + penalties apply
                $gam->update(['current_streak' => 0]);

                // RF-05 — Calorie penalty (only if no safety day)
                $this->applyCaloriePenalty($user, $dateString);
            }
        }

        // RF-05 — Calorie penalty (even if activity exists, check meta)
        if ($hadActivity) {
            $this->checkCaloriePenalty($user, $dateString);
        }

        // Mark day as processed
        $gam->update(['last_processed_date' => $dateString]);
    }

    // ================================================================
    //  RF-04 — Weekly Workout Penalty (called on Sunday processing)
    // ================================================================
    public function processEndOfWeek(User $user, string $sundayDateString): void
    {
        $sunday  = Carbon::parse($sundayDateString);
        $monday  = $sunday->copy()->startOfWeek(Carbon::MONDAY);
        $isoWeek = $sunday->format('o-\WW');

        // Count workouts this week
        $weekWorkouts = WorkoutLog::where('user_id', $user->id)
            ->whereBetween('date', [$monday->toDateString(), $sundayDateString])
            ->count();

        if ($weekWorkouts >= 3) {
            return; // Met the goal
        }

        // Check if safety day was used this week — safety does NOT exempt
        // workout penalty (it only covers daily absence/calories).
        // Explicitly: the weekly training goal stands regardless of the shield.

        // Idempotency: check if penalty already applied for this week
        $alreadyPenalized = XpTransaction::where('user_id', $user->id)
            ->where('type', 'penalty_workout')
            ->where('description', 'like', "%{$isoWeek}%")
            ->exists();

        if ($alreadyPenalized) {
            return;
        }

        $this->debitXp($user, 'penalty_workout', self::PENALTY_WORKOUT,
            "Penalidade semanal de treinos ({$isoWeek}): {$weekWorkouts}/3 treinos",
            $sundayDateString
        );

        Log::info("Gamification: Weekly workout penalty for user {$user->id} ({$weekWorkouts}/3 workouts in {$isoWeek})");
    }

    // ================================================================
    //  RF-07 — Level Up Check
    // ================================================================
    public function checkLevelUp(User $user): bool
    {
        $gam = $this->gamification($user);
        $xp  = $gam->xp_total;

        $newLevel = 1;
        foreach (self::LEVEL_THRESHOLDS as $level => $minXp) {
            if ($xp >= $minXp) {
                $newLevel = $level;
            }
        }

        if ($newLevel <= $gam->current_level) {
            return false;
        }

        $nextLevelXp = self::LEVEL_THRESHOLDS[$newLevel + 1] ?? null;
        $xpToNext    = $nextLevelXp ? ($nextLevelXp - $xp) : 0;

        $gam->update([
            'current_level' => $newLevel,
            'xp_to_next'    => max(0, $xpToNext),
        ]);

        // RF-08: Level Badges
        $this->checkLevelBadges($user, $newLevel);

        Log::info("Gamification: User {$user->id} leveled up to {$newLevel}");
        return true;
    }

    // ================================================================
    //  RF-08 — Award Badge (idempotent)
    // ================================================================
    public function awardBadge(User $user, string $slug): ?UserAchievement
    {
        $achievement = Achievement::where('slug', $slug)->where('is_active', true)->first();
        if (!$achievement) {
            return null;
        }

        // Already earned?
        $exists = UserAchievement::where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($user, $achievement) {
            $ua = UserAchievement::create([
                'user_id'        => $user->id,
                'achievement_id' => $achievement->id,
                'xp_received'    => $achievement->xp_reward,
                'is_notified'    => false,
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

    // ================================================================
    //  PRIVATE HELPERS
    // ================================================================

    /** Credit XP and update totals (RF-01, RNF-01) */
    private function creditXp(
        User $user, string $type, int $amount, string $description,
        string $date, ?string $refId = null, ?string $refTable = null
    ): XpTransaction {
        $gam = $this->gamification($user);

        // Apply streak bonus
        $bonus = $this->calculateStreakMultiplier($gam->current_streak);
        $finalAmount = (int) round($amount * (1 + $bonus));

        $gam->increment('xp_total', $finalAmount);
        $gam->increment('current_week_xp', $finalAmount);
        $gam->increment('current_month_xp', $finalAmount);
        $gam->refresh();

        $tx = XpTransaction::create([
            'user_id'           => $user->id,
            'type'              => $type,
            'xp_gained'         => $finalAmount,
            'description'       => $description . ($bonus > 0 ? " (streak bonus +".round($bonus*100)."%)" : ''),
            'ref_id'            => $refId,
            'ref_table'         => $refTable,
            'date'              => $date,
            'xp_total_snapshot'  => $gam->xp_total,
        ]);

        // Check level up
        $this->checkLevelUp($user);

        return $tx;
    }

    /** Debit XP ensuring non-negative balance (RNF-02, RNF-01) */
    private function debitXp(User $user, string $type, int $amount, string $description, string $date): ?XpTransaction
    {
        $gam = $this->gamification($user);

        if ($gam->xp_total <= 0) {
            return null; // Already at zero — discard penalty
        }

        $actualDeduction = min($amount, $gam->xp_total);
        $gam->decrement('xp_total', $actualDeduction);
        $gam->decrement('current_week_xp', min($actualDeduction, $gam->current_week_xp));
        $gam->decrement('current_month_xp', min($actualDeduction, $gam->current_month_xp));
        $gam->refresh();

        return XpTransaction::create([
            'user_id'           => $user->id,
            'type'              => $type,
            'xp_gained'         => -$actualDeduction,
            'description'       => $description,
            'date'              => $date,
            'xp_total_snapshot'  => $gam->xp_total,
        ]);
    }

    /** Check calorie penalty for a specific day (RF-05) */
    private function checkCaloriePenalty(User $user, string $dateString): void
    {
        // Idempotency
        $alreadyPenalized = XpTransaction::where('user_id', $user->id)
            ->where('type', 'penalty_calories')
            ->where('date', $dateString)
            ->exists();

        if ($alreadyPenalized) {
            return;
        }

        $goal = $user->goal;
        if (!$goal || !$goal->goal_calories_day) {
            return; // No calorie goal set — skip
        }

        $totalCalories = MealLog::where('user_id', $user->id)
            ->where('date', $dateString)
            ->sum('calories_consumed');

        if ($totalCalories >= $goal->goal_calories_day) {
            return; // Goal met
        }

        $this->debitXp($user, 'penalty_calories', self::PENALTY_CALORIES,
            "Meta calórica não atingida ({$dateString}): {$totalCalories}/{$goal->goal_calories_day} kcal",
            $dateString
        );
    }

    /** Apply calorie penalty directly (for absent days with no safety) */
    private function applyCaloriePenalty(User $user, string $dateString): void
    {
        $alreadyPenalized = XpTransaction::where('user_id', $user->id)
            ->where('type', 'penalty_calories')
            ->where('date', $dateString)
            ->exists();

        if ($alreadyPenalized) {
            return;
        }

        $this->debitXp($user, 'penalty_calories', self::PENALTY_CALORIES,
            "Meta calórica não atingida - dia ausente ({$dateString})",
            $dateString
        );
    }

    /** Streak bonus: +10% for every 7 days, capped at 50% */
    private function applyStreakBonus(User $user, Carbon $date, int $streakDay): void
    {
        $multiplier = $this->calculateStreakMultiplier($streakDay);
        $bonusXp    = (int) round(self::XP_LOGIN * $multiplier * 10); // symbolic bonus

        if ($bonusXp > 0) {
            $this->creditXp($user, 'streak_bonus', $bonusXp,
                "Bônus de streak: {$streakDay} dias consecutivos",
                $date->toDateString()
            );
        }
    }

    /** Calculate streak multiplier (RF-02) */
    private function calculateStreakMultiplier(int $currentStreak): float
    {
        $weeks = intdiv($currentStreak, 7);
        $bonus = $weeks * self::STREAK_BONUS_PCT;
        return min($bonus, self::STREAK_BONUS_CAP);
    }

    /** Check and award streak-related badges (RF-08) */
    private function checkStreakBadges(User $user, int $streak): void
    {
        $milestones = [7 => 'streak_7', 30 => 'streak_30', 90 => 'streak_90'];
        foreach ($milestones as $days => $slug) {
            if ($streak >= $days) {
                $this->awardBadge($user, $slug);
            }
        }
    }

    /** Check and award level-related badges (RF-08) */
    private function checkLevelBadges(User $user, int $level): void
    {
        $milestones = [
            5  => 'level_5',
            10 => 'level_10',
        ];

        // Award badges that already exist in the achievements table
        foreach ($milestones as $lvl => $slug) {
            if ($level >= $lvl) {
                $this->awardBadge($user, $slug);
            }
        }
    }

    /** Check and award workout-related badges (RF-08) */
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

    /** Get or create daily activity limit record */
    private function getOrCreateDailyLimit(User $user, string $date): DailyActivityLimit
    {
        return DailyActivityLimit::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['daily_xp_limit' => 300]
        );
    }

    /** Update last_activity on the gamification record */
    private function touchActivity(User $user, string $date): void
    {
        $gam = $this->gamification($user);
        if (!$gam->last_activity || $gam->last_activity->toDateString() < $date) {
            $gam->update(['last_activity' => $date]);
        }
    }

    /** Get user's gamification record (lazy loaded) */
    private function gamification(User $user): UserGamification
    {
        return $user->gamification ?? UserGamification::create(['user_id' => $user->id]);
    }

    /** Get today's date in the user's timezone (RNF-03) */
    private function userToday(User $user): string
    {
        $tz = $user->timezone ?? 'UTC';
        return Carbon::now($tz)->toDateString();
    }
}
