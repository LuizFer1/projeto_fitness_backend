<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasUuids;

    protected $table = 'foods';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'calories_100g'      => 'decimal:2',
        'protein_g'          => 'decimal:2',
        'carbs_g'            => 'decimal:2',
        'fat_g'              => 'decimal:2',
        'fiber_g'            => 'decimal:2',
        'sodium_mg'          => 'decimal:2',
        'standard_portion_g' => 'decimal:2',
        'is_active'          => 'boolean',
    ];
}
