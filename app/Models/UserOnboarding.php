<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOnboarding extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'onboarding';
    
    // public function uniqueIds(): array { return ['uuid']; } // Removed since we use auto-increment or id already managed by HasUuids

    protected $fillable = [
        'user_id', 'gender', 'age', 'height_cm', 'weight_kg',
        'exercise_frequency', 'work_style', 'body_fat_pct', 'bmr'
    ];

    protected $casts = [
        'age'              => 'integer',
        'height_cm'        => 'decimal:2',
        'weight_kg'        => 'decimal:2',
        'body_fat_pct'     => 'decimal:1',
        'bmr'              => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
