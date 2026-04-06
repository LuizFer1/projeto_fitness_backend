<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_uuid',
        'title',
        'type',
        'target_value',
        'initial_value',
        'current_value',
        'unit',
        'deadline',
        'visibility',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'initial_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
