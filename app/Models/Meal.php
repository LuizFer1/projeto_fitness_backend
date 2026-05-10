<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'meals';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'total_calories' => 'decimal:2',
        'total_protein_g' => 'decimal:2',
        'total_carbs_g' => 'decimal:2',
        'total_fat_g' => 'decimal:2',
        'total_fiber_g' => 'decimal:2',
    ];
}
