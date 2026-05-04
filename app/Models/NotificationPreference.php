<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'streak_at_risk', 'rank_drop', 'achievement_close',
        'achievement_unlocked', 'daily_summary', 'quiet_hours_start', 'quiet_hours_end',
    ];

    protected $casts = [
        'streak_at_risk'       => 'boolean',
        'rank_drop'            => 'boolean',
        'achievement_close'    => 'boolean',
        'achievement_unlocked' => 'boolean',
        'daily_summary'        => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
