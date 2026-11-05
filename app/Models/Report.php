<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'reporting_period',
        'reporting_period_start',
        'reporting_period_end',
        'file_name',
        'file_path',
        'file_disk',
        'mime_type',
        'file_size',
        'status',
        'visibility',
        'created_by',
        'published_at',
        'submitted_at',
        'review_feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'published_at' => 'datetime',
        'reporting_period_start' => 'date:Y-m-d',
        'reporting_period_end' => 'date:Y-m-d',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
