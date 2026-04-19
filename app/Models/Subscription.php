<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id', 'plan_id', 'plan_price_id', 'status',
        'started_at', 'trial_ends_at', 'current_period_end',
        'canceled_at', 'cancel_at_period_end',
    ];

    protected $casts = [
        'started_at'            => 'datetime',
        'trial_ends_at'         => 'datetime',
        'current_period_end'    => 'datetime',
        'canceled_at'           => 'datetime',
        'cancel_at_period_end'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPrice()
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }

    public function scopeActiveForUser($query, string $userId)
    {
        return $query->where('user_id', $userId)
            ->whereIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE]);
    }
}
