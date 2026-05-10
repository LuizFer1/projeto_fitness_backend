<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietAdjustment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'source_meal_log_id', 'target_date',
        'delta_kcal', 'delta_protein_g', 'delta_carbs_g', 'delta_fat_g',
        'mode', 'applied_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'applied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
