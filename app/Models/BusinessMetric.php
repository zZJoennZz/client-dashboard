<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'metric_type',
        'value',
        'unit',
        'period_start',
        'period_end',
        'source',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'period_start' => 'date',
        'period_end' => 'date',
        'recorded_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }
}
