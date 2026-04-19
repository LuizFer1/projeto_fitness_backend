<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
    use HasUuids;

    protected $table = 'quests';

    public $timestamps = false;

    protected $fillable = [
        'slug', 'name', 'description', 'icon', 'type',
        'periodicity', 'condition_type', 'condition_value',
        'xp_reward', 'is_active',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'condition_value' => 'integer',
        'xp_reward'       => 'integer',
    ];

    public function userQuests()
    {
        return $this->hasMany(UserQuest::class, 'quest_id', 'id');
    }
}
