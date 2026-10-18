<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessVideo extends Model
{
    protected $fillable = ['business_id', 'source_type', 'source_url', 'file_path'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
