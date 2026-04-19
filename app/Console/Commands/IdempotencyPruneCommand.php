<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

class IdempotencyPruneCommand extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Remove registros de Idempotency-Key expirados.';

    public function handle(): int
    {
        $deleted = IdempotencyKey::where('expires_at', '<', now())->delete();

        $this->info("Removidos {$deleted} registros expirados.");

        return self::SUCCESS;
    }
}
