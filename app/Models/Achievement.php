<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'title',
        'description',
        'icon',
        'category',
        'xp_reward',
    ];

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
