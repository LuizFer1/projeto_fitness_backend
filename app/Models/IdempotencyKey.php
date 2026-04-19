<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasUuids;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';

    public const UPDATED_AT = null;

    protected $fillable = [
        'key', 'user_id', 'method', 'path', 'request_hash',
        'status', 'response_status', 'response_body', 'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'expires_at'    => 'datetime',
    ];
}
