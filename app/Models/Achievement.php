<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasUuids;

    protected $table = 'achievements';

    public $timestamps = false;

    protected $fillable = [
        'slug', 'name', 'description', 'icon', 'category',
        'xp_reward', 'condition_type', 'condition_value',
        'is_hidden', 'is_active',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'is_active' => 'boolean',
    ];
}
