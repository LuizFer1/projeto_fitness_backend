<?php

namespace App\Http\Resources\Gamification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class XpTransactionResource extends JsonResource
{
    private const REASON_LABELS = [
        'login_daily' => 'Login diário',
        'workout_done' => 'Treino concluído',
        'goal_checkin' => 'Check-in de meta',
        'goal_completed' => 'Meta concluída',
        'meal_followed' => 'Refeição seguida',
        'achievement_unlocked' => 'Conquista desbloqueada',
        'friend_added' => 'Amigo adicionado',
        'post_liked' => 'Post curtido',
    ];

    public function toArray(Request $request): array
    {
        $meta = $this->meta ?? [];

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'base_amount' => $meta['base_amount'] ?? $this->amount,
            'streak_bonus' => $meta['streak_bonus'] ?? 0,
            'streak_day' => $meta['streak_day'] ?? 0,
            'reason' => $this->reason,
            'reason_label' => self::REASON_LABELS[$this->reason] ?? $this->reason,
            'reference_type' => $this->reference_type,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
