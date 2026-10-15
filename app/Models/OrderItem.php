<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'service_id',
        'service_name',
        'service_description',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            if ($item->quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Order item quantity must be at least one.',
                ]);
            }

            if (Order::amountInCents($item->unit_price) < 0) {
                throw ValidationException::withMessages([
                    'unit_price' => 'Order item price cannot be negative.',
                ]);
            }

            $lineTotal = Order::amountInCents($item->unit_price) * $item->quantity;
            $item->line_total = Order::formatCents($lineTotal);
        });

        static::saved(function (OrderItem $item): void {
            $item->order?->recalculateTotals();
        });
    }

    public static function createFromService(Order $order, Service $service, int $quantity): self
    {
        return DB::transaction(function () use ($order, $service, $quantity): self {
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Order item quantity must be at least one.',
                ]);
            }

            if ($service->status !== 'available') {
                throw ValidationException::withMessages([
                    'service' => 'This service is not currently available.',
                ]);
            }

            $item = $order->items()->create([
                'service_id' => $service->getKey(),
                'service_name' => $service->name,
                'service_description' => $service->description,
                'unit_price' => $service->price,
                'quantity' => $quantity,
            ]);

            return $item;
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }
}
