<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    use HasFactory;
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
