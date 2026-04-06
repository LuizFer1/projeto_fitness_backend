<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessTimezoneGamification extends Command
{
    protected $signature = 'gamification:process-timezone';
    protected $description = 'Processes daily/weekly gamification for users whose local midnight has passed (runs hourly)';

    public function handle(GamificationService $service): int
    {
        $now = Carbon::now('UTC');
        $processed = 0;
        $errors = 0;

        // Get all distinct timezones from users
        $timezones = User::whereNotNull('timezone')
            ->distinct()
            ->pluck('timezone')
            ->push('UTC')
            ->unique();

        foreach ($timezones as $tz) {
            try {
                $localNow = $now->copy()->setTimezone($tz);
            } catch (\Exception $e) {
                Log::warning("Gamification: Invalid timezone '{$tz}', skipping.");
                continue;
            }

            // Only process if it's between 00:00 and 00:59 in this timezone
            if ($localNow->hour !== 0) {
                continue;
            }

            $yesterday = $localNow->copy()->subDay()->toDateString();
            $isSunday  = $localNow->copy()->subDay()->dayOfWeekIso === 7; // yesterday was Sunday

            // Get users with this timezone that haven't been processed for yesterday
            $users = User::where('timezone', $tz)
                ->whereHas('gamification', function ($q) use ($yesterday) {
                    $q->where(function ($sub) use ($yesterday) {
                        $sub->whereNull('last_processed_date')
                            ->orWhere('last_processed_date', '<', $yesterday);
                    });
                })
                ->with(['gamification', 'goal'])
                ->cursor();

            foreach ($users as $user) {
                try {
                    // Fill any gap days between last_processed and yesterday
                    $lastProcessed = $user->gamification->last_processed_date
                        ? Carbon::parse($user->gamification->last_processed_date)->addDay()
                        : Carbon::parse($yesterday);

                    $targetDate = Carbon::parse($yesterday);

                    while ($lastProcessed->lte($targetDate)) {
                        $dateStr = $lastProcessed->toDateString();
                        $service->processEndOfDay($user, $dateStr);

                        // Check if this date was a Sunday for weekly processing
                        if ($lastProcessed->dayOfWeekIso === 7) {
                            $service->processEndOfWeek($user, $dateStr);
                        }

                        $lastProcessed->addDay();
                    }

                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Gamification: Error processing user {$user->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Processed {$processed} users, {$errors} errors.");
        Log::info("Gamification cron: {$processed} processed, {$errors} errors.");

        return self::SUCCESS;
    }
}
