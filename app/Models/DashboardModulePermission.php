<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardModulePermission extends Model
{
    protected $fillable = [
        'module',
        'can_read',
        'can_write',
    ];

    protected $casts = [
        'can_read' => 'boolean',
        'can_write' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
