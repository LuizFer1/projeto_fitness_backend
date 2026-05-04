<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RankDropNotification extends Notification
{
    public function __construct(
        private int $previousPosition,
        private int $currentPosition,
        private string $period = 'weekly',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'rank_drop',
            'period'            => $this->period,
            'previous_position' => $this->previousPosition,
            'current_position'  => $this->currentPosition,
            'title'             => 'Você caiu no ranking!',
            'body'              => "Você saiu da posição #{$this->previousPosition} para #{$this->currentPosition} no ranking {$this->period}. Treine para recuperar sua posição!",
        ];
    }
}
