<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class StreakAtRiskNotification extends Notification
{
    public function __construct(private int $streakDays) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'streak_at_risk',
            'streak_days' => $this->streakDays,
            'title'       => 'Sua sequência está em risco!',
            'body'        => "Você tem uma sequência de {$this->streakDays} dias. Faça uma atividade hoje para não perder!",
        ];
    }
}
