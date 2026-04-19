<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminPromoteCommand extends Command
{
    protected $signature = 'admin:promote {email} {--demote : Remove admin instead of granting}';

    protected $description = 'Promove (ou rebaixa com --demote) um usuário a admin pelo e-mail.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');
            return self::FAILURE;
        }

        $user->is_admin = ! $this->option('demote');
        $user->save();

        $this->info(sprintf(
            'Usuário %s %s.',
            $user->email,
            $user->is_admin ? 'promovido a admin' : 'rebaixado'
        ));

        return self::SUCCESS;
    }
}
