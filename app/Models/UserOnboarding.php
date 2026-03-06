<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOnboarding extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_onboarding';
    
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'user_uuid', 'completed', 'gender', 'age', 'height_cm', 'weight_kg',
        'body_fat_percent', 'workouts_per_week', 'work_style', 'bmr'
    ];

    protected $casts = [
        'completed'        => 'boolean',
        'age'              => 'integer',
        'weight_kg'        => 'decimal:2',
        'body_fat_percent' => 'decimal:2',
        'bmr'              => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
