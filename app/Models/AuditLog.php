<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    // Audit log is append-only — deny updates and deletes at model level
    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        return false;
    }

    public function delete(): bool|null
    {
        return false;
    }
}
