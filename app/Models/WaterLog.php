<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'water_logs';

    protected $fillable = [
        'user_id', 'date', 'liters', 'time',
    ];

    protected $casts = [
        'liters' => 'decimal:2',
        'date'   => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
