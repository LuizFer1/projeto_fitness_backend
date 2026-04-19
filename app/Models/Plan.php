<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['code', 'name', 'description', 'trial_days', 'is_active'];

    protected $casts = [
        'trial_days' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function prices()
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function activePrices()
    {
        return $this->hasMany(PlanPrice::class)->where('is_active', true);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
