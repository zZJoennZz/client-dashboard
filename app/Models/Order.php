<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'draft',
        'submitted',
        'under_review',
        'approved',
        'in_progress',
        'completed',
        'cancelled',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid',
        'partially_paid',
        'paid',
        'refunded',
        'waived',
    ];

    protected $fillable = [
        'order_number',
        'business_id',
        'user_id',
        'status',
        'payment_status',
        'subtotal',
        'total',
        'currency',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            if (! in_array($order->status, self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected order status is invalid.',
                ]);
            }

            if (! in_array($order->payment_status, self::PAYMENT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'The selected payment status is invalid.',
                ]);
            }

            if (self::amountInCents($order->subtotal) < 0 || self::amountInCents($order->total) < 0) {
                throw ValidationException::withMessages([
                    'total' => 'Order amounts cannot be negative.',
                ]);
            }

            if (self::amountInCents($order->total) < self::amountInCents($order->subtotal)) {
                throw ValidationException::withMessages([
                    'total' => 'Order total cannot be less than its subtotal.',
                ]);
            }
        });
    }

    public static function amountInCents(string|int|float|null $amount): int
    {
        $value = (string) $amount;

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw ValidationException::withMessages([
                'amount' => 'Monetary amounts must be numeric and have no more than two decimal places.',
            ]);
        }

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 2, '0');
        $cents = ($whole * 100) + $fraction;

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function formatCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        return $sign . intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function recalculateTotals(): void
    {
        $subtotalInCents = $this->items()
            ->get()
            ->sum(fn (OrderItem $item): int => self::amountInCents($item->line_total));

        $confirmedPaymentsInCents = $this->payments()
            ->where('status', 'confirmed')
            ->get()
            ->sum(fn (OrderPayment $payment): int => self::amountInCents($payment->amount));

        if ($confirmedPaymentsInCents > $subtotalInCents) {
            throw ValidationException::withMessages([
                'total' => 'Order total cannot be reduced below confirmed payments.',
            ]);
        }

        $this->forceFill([
            'subtotal' => self::formatCents($subtotalInCents),
            'total' => self::formatCents($subtotalInCents),
        ])->save();
    }

    public function synchronizePaymentStatus(): void
    {
        DB::transaction(function (): void {
            $order = self::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($order->payment_status === 'waived') {
                return;
            }

            $paidInCents = $order->payments()
                ->where('status', 'confirmed')
                ->get()
                ->sum(fn (OrderPayment $payment): int => self::amountInCents($payment->amount));
            $totalInCents = self::amountInCents($order->total);

            $status = match (true) {
                $paidInCents === 0 => 'unpaid',
                $paidInCents < $totalInCents => 'partially_paid',
                default => 'paid',
            };

            $order->forceFill(['payment_status' => $status])->save();
            $this->payment_status = $status;
        });
    }
}
