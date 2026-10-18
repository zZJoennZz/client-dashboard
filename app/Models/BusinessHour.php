<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHour extends Model
{
    protected $fillable = ['business_id', 'day_of_week', 'opens_at', 'closes_at', 'is_closed', 'by_appointment'];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
        'by_appointment' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
