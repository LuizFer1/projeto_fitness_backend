<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionDaily extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nutrition_daily';

    protected $fillable = [
        'user_id', 'day',
        'calories_goal', 'protein_goal_g', 'carbs_goal_g', 'fat_goal_g',
        'calories_consumed', 'protein_consumed_g', 'carbs_consumed_g', 'fat_consumed_g',
        'delta_kcal', 'adjustment_ratio', 'dilution_active',
    ];

    protected $casts = [
        'day' => 'date',
        'calories_goal' => 'integer',
        'dilution_active' => 'boolean',
        'adjustment_ratio' => 'decimal:4',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
