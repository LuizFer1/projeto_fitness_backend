<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Friendship extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'requester_id',
        'addressee_id',
        'status',
        'accepted_at',
        'blocked_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    // ── Scopes ──

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', 'blocked');
    }

    // ── Relationships ──

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee()
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }
}
