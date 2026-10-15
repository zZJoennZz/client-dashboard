<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'payment_status',
        'start_date',
        'activated_at',
        'end_date',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'activated_at' => 'datetime',
        'end_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'payment_status' => $this->payment_status ?: 'paid',
            'activated_at' => $this->activated_at ?? now(),
            'start_date' => $this->start_date ?? now()->toDateString(),
        ]);
    }
}
