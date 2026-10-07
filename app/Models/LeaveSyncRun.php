<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveSyncRun extends Model
{
    protected $fillable = [
        'admin_user_id',
        'status',
        'fetched_count',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'linked_count',
        'errors',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
