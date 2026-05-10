<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Notifications\Notification;

class AchievementCloseNotification extends Notification
{
    public function __construct(
        private Achievement $achievement,
        private int $progressPercent,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'achievement_close',
            'achievement_slug' => $this->achievement->slug,
            'achievement_name' => $this->achievement->name,
            'progress_percent' => $this->progressPercent,
            'title' => 'Você está quase lá!',
            'body' => "Você está {$this->progressPercent}% do caminho para a conquista: {$this->achievement->name}",
        ];
    }
}
