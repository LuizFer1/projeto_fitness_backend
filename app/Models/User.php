<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    // removed uniqueIds to default to primary key 'id'

    protected $fillable = [
        'name', 'last_name', 'email', 'cpf', 'password_hash', 'avatar_url',
        'nickname', 'bio', 'timezone',
    ];

    protected $hidden = ['password_hash'];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ── Relationships ──

    public function onboarding()
    {
        return $this->hasOne(UserOnboarding::class, 'user_id', 'id');
    }

    public function gamification()
    {
        return $this->hasOne(UserGamification::class, 'user_id', 'id');
    }

    public function goal()
    {
        return $this->hasOne(UserGoal::class, 'user_id', 'id')->where('is_active', true);
    }
}
