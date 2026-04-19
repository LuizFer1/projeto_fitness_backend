<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlanPrice extends Model
{
    use HasUuids;

    protected $fillable = ['plan_id', 'billing_period', 'price_cents', 'currency', 'is_active'];

    protected $casts = [
        'price_cents' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
