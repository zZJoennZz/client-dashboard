<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            if (Order::amountInCents($service->price) < 0) {
                throw ValidationException::withMessages([
                    'price' => 'Service price cannot be negative.',
                ]);
            }

            if (! in_array($service->status, ['available', 'unavailable'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected service status is invalid.',
                ]);
            }
        });
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'service_id');
    }
}
