<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'name', 'last_name', 'email', 'cpf', 'password_hash', 'avatar_url',
        'username', 'bio', 'is_active',
        'xp_points', 'level',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ── Relationships ──

    public function onboarding()
    {
        return $this->hasOne(UserOnboarding::class, 'user_uuid', 'uuid');
    }

    public function xpTransactions()
    {
        return $this->hasMany(XpTransaction::class, 'user_uuid', 'uuid');
    }

    public function achievements()
    {
        return $this->hasMany(UserAchievement::class, 'user_uuid', 'uuid');
    }
}
