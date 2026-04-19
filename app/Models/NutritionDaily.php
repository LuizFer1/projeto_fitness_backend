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
        'user_id', 'day', 'calories_goal',
    ];

    protected $casts = [
        'calories_goal' => 'integer',
        'day'           => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
