<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BiweeklyReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'period_start'  => 'date',
        'period_end'    => 'date',
        'summary_data'  => 'array',
        'generated_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
