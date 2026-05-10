<?php

namespace App\Observers;

use App\Models\XpTransaction;
use App\Services\Ranking\RedisRankingService;

class XpTransactionObserver
{
    public function __construct(private RedisRankingService $ranking) {}

    public function created(XpTransaction $tx): void
    {
        if ($tx->xp_gained > 0) {
            $this->ranking->incrementXp((string) $tx->user_id, $tx->xp_gained);
        }
    }
}
