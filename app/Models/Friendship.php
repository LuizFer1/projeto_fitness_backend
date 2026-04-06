<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    protected $fillable = ['user_uuid', 'friend_uuid', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_uuid', 'uuid');
    }
}
