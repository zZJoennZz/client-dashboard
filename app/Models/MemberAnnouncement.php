<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAnnouncement extends Model
{
    protected $fillable = [
        'created_by',
        'subject',
        'body',
        'audience',
        'status',
        'recipient_count',
        'delivered_count',
        'failed_count',
        'failed_recipients',
        'sent_at',
    ];

    protected $casts = [
        'audience' => 'array',
        'failed_recipients' => 'array',
        'sent_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
