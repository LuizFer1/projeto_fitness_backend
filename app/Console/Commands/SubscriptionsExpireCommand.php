<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SubscriptionsExpireCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Marca assinaturas com period/trial encerrado como expired.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $trialingExpired = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        foreach ($trialingExpired as $sub) {
            if ($sub->current_period_end && $sub->current_period_end->greaterThan($now)) {
                $sub->update(['status' => Subscription::STATUS_ACTIVE]);
            } else {
                $sub->update(['status' => Subscription::STATUS_EXPIRED]);
            }
        }

        $activeExpired = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->get();

        foreach ($activeExpired as $sub) {
            $sub->update(['status' => Subscription::STATUS_EXPIRED]);
        }

        $this->info('Trialing processados: '.$trialingExpired->count());
        $this->info('Active expirados: '.$activeExpired->count());

        return self::SUCCESS;
    }
}
