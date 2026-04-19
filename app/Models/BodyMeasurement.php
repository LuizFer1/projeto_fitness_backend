<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BodyMeasurement extends Model
{
    use HasUuids;

    protected $table = 'body_measurements';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'date', 'weight_kg', 'body_fat_pct',
        'muscle_mass_kg', 'waist_circumference_cm',
        'hip_circumference_cm', 'arm_circumference_cm', 'bmi',
    ];

    protected $casts = [
        'date'                     => 'date',
        'weight_kg'                => 'decimal:2',
        'body_fat_pct'             => 'decimal:1',
        'muscle_mass_kg'           => 'decimal:2',
        'waist_circumference_cm'   => 'decimal:2',
        'hip_circumference_cm'     => 'decimal:2',
        'arm_circumference_cm'     => 'decimal:2',
        'bmi'                      => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
