<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class XpTransaction extends Model
{
    use HasUuids;

    protected $table = 'xp_transactions';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'type', 'xp_gained', 'description',
        'ref_id', 'ref_table', 'date', 'xp_total_snapshot',
    ];

    protected $casts = [
        'date' => 'date',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
