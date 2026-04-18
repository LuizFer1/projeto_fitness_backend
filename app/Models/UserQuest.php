<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserQuest extends Model
{
    use HasUuids;

    protected $table = 'user_quests';

    protected $fillable = [
        'user_id', 'quest_id', 'status', 'current_progress',
        'target_progress', 'started_at', 'completed_at',
        'xp_received', 'ref_period', 'is_notified',
    ];

    protected $casts = [
        'started_at'   => 'date',
        'completed_at' => 'datetime',
        'is_notified'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function quest()
    {
        return $this->belongsTo(Quest::class, 'quest_id', 'id');
    }
}
