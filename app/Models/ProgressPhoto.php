<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressPhoto extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'taken_at', 'weight_kg', 'category',
        's3_key', 'encryption_iv', 'encryption_key_wrapped',
        'caption', 'notes',
    ];

    protected $casts = [
        'taken_at'  => 'date',
        'weight_kg' => 'decimal:2',
    ];

    protected $hidden = [
        's3_key', 'encryption_iv', 'encryption_key_wrapped',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
