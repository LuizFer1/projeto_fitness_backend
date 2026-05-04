<?php

use App\Jobs\EvaluateStreakAtRiskJob;
use App\Jobs\GenerateBiweeklyReportJob;
use App\Jobs\RecalculateLeaderboardJob;
use App\Jobs\VerifyAchievementsRetroactivelyJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Leaderboard rebuild (hourly, recalculates DB snapshots + Redis reset at midnight UTC)
Schedule::job(new RecalculateLeaderboardJob)->hourly();

// Streak-at-risk push notification (hourly — each job instance checks local time per user)
Schedule::job(new EvaluateStreakAtRiskJob)->hourly();

// Gold/Platinum achievement retroactive verification (daily at 03:00 UTC)
Schedule::job(new VerifyAchievementsRetroactivelyJob)->dailyAt('03:00');

// Biweekly progress reports (every 14 days at 06:00 UTC, RF-25)
Schedule::call(fn () => GenerateBiweeklyReportJob::dispatchForAllUsers())->cron('0 6 */14 * *');
