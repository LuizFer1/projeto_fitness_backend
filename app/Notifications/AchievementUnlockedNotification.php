<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Notifications\Notification;

class AchievementUnlockedNotification extends Notification
{
    public function __construct(private Achievement $achievement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'achievement_unlocked',
            'achievement_slug' => $this->achievement->slug,
            'achievement_name' => $this->achievement->name,
            'tier' => $this->achievement->tier ?? 'bronze',
            'xp_reward' => $this->achievement->xp_reward ?? 0,
            'title' => 'Conquista desbloqueada!',
            'body' => "Você desbloqueou: {$this->achievement->name}",
        ];
    }
}
