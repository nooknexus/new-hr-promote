<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An office (ต้นสังกัด) that personnel and users belong to by its two-digit code. */
class Office extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
