<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberActivity extends Model
{
    protected $fillable = [
        'user_id',
        'business_id',
        'event_id',
        'activity_type',
        'activity_date',
        'subject_type',
        'subject_id',
        'subject_label',
        'metadata',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
