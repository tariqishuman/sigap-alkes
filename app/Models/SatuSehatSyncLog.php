<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'resource',
    'local_id',
    'action',
    'status',
    'http_status',
    'response_summary',
    'synced_at',
])]
class SatuSehatSyncLog extends Model
{
    protected $table = 'satusehat_sync_logs';

    protected function casts(): array
    {
        return [
            'local_id' => 'integer',
            'http_status' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
