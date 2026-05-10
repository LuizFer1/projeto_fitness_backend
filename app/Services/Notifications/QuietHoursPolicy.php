<?php

namespace App\Services\Notifications;

use Carbon\Carbon;

class QuietHoursPolicy
{
    /**
     * Returns true if $now falls within the quiet window defined by $start and $end (HH:MM strings).
     * Handles overnight windows (e.g. 23:00–07:00).
     */
    public function isQuiet(Carbon $now, string $start, string $end): bool
    {
        $startH = (int) explode(':', $start)[0];
        $endH = (int) explode(':', $end)[0];
        $hour = $now->hour;

        if ($startH > $endH) {
            // Overnight window: quiet from startH through midnight and until endH
            return $hour >= $startH || $hour < $endH;
        }

        return $hour >= $startH && $hour < $endH;
    }

    /**
     * Returns true if $now is inside the user's quiet hours, given their timezone.
     */
    public function isQuietForUser(string $timezone, string $start, string $end): bool
    {
        return $this->isQuiet(Carbon::now($timezone), $start, $end);
    }
}
