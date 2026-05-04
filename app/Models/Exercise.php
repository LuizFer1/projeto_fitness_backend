<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'body_zones' => 'array',
        'is_active'  => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Exercise::class, 'parent_exercise_id', 'id');
    }

    public function variations()
    {
        return $this->hasMany(Exercise::class, 'parent_exercise_id', 'id');
    }
}
