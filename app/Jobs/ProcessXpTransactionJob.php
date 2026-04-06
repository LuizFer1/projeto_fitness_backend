<?php

namespace App\Jobs;

use App\Application\UseCases\Xp\AddXpUseCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessXpTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly int $baseAmount,
        public readonly string $reason,
        public readonly ?string $referenceType = null,
        public readonly ?string $referenceId = null,
        public readonly ?Carbon $occurredAt = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AddXpUseCase $useCase): void
    {
        $useCase->execute(
            $this->user,
            $this->baseAmount,
            $this->reason,
            $this->referenceType,
            $this->referenceId,
            $this->occurredAt,
        );
    }
}
