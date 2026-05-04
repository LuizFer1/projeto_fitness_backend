<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'platform', 'push_token', 'app_version',
        'device_model', 'last_seen_at', 'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    protected $hidden = ['push_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
