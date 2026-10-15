<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderPayment extends Model
{
    use HasFactory;

    public const STATUSES = ['confirmed', 'reversed'];

    protected $fillable = [
        'order_id',
        'amount',
        'currency',
        'payment_date',
        'method',
        'reference',
        'notes',
        'recorded_by',
        'status',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrderPayment $payment): void {
            if (Order::amountInCents($payment->amount) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be greater than zero.',
                ]);
            }

            if (! in_array($payment->status, self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected payment status is invalid.',
                ]);
            }
        });
    }

    public static function recordManual(Order $order, User $actor, array $attributes): self
    {
        Gate::forUser($actor)->authorize('create', [self::class, $order]);

        return DB::transaction(function () use ($order, $actor, $attributes): self {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $amountInCents = Order::amountInCents($attributes['amount'] ?? 0);
            $alreadyPaidInCents = $lockedOrder->payments()
                ->where('status', 'confirmed')
                ->get()
                ->sum(fn (OrderPayment $payment): int => Order::amountInCents($payment->amount));

            if ($amountInCents <= 0 || $alreadyPaidInCents + $amountInCents > Order::amountInCents($lockedOrder->total)) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be positive and cannot exceed the remaining order balance.',
                ]);
            }

            $payment = $lockedOrder->payments()->create([
                'amount' => $attributes['amount'],
                'currency' => $lockedOrder->currency,
                'payment_date' => $attributes['payment_date'] ?? now()->toDateString(),
                'method' => $attributes['method'],
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'recorded_by' => $actor->getKey(),
                'status' => 'confirmed',
            ]);

            $lockedOrder->synchronizePaymentStatus();

            return $payment;
        });
    }

    public function reverse(User $actor, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $this);

        DB::transaction(function () use ($actor, $reason): void {
            $payment = self::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($payment->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'payment' => 'Only confirmed payments can be reversed.',
                ]);
            }

            $payment->forceFill([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ])->save();

            $payment->order->synchronizePaymentStatus();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
