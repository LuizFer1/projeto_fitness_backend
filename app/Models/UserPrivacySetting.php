<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserPrivacySetting extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'share_weight' => 'boolean',
        'share_macros' => 'boolean',
        'share_one_rm' => 'boolean',
        'share_streak' => 'boolean',
        'share_achievements' => 'boolean',
        'share_workouts' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get or create default privacy settings for a user.
     */
    public static function forUser(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id],
            [
                'share_weight' => false,
                'share_macros' => false,
                'share_one_rm' => true,
                'share_streak' => true,
                'share_achievements' => true,
                'share_workouts' => true,
            ]
        );
    }
}
