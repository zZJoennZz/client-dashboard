<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAcknowledgment extends Model
{
    protected $fillable = ['resource_id', 'business_id', 'user_id', 'version', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime', 'version' => 'integer'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }
}