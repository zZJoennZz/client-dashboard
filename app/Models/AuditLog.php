<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'context',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (AuditLog $auditLog): void {
            foreach (['old_values', 'new_values', 'context'] as $attribute) {
                $value = $auditLog->getAttribute($attribute);
                if (is_array($value)) {
                    $auditLog->setAttribute($attribute, self::withoutSecrets($value));
                }
            }
        });
    }

    public static function record(
        ?User $actor,
        string $action,
        ?Model $target = null,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
    ): self {
        $request = request();

        return static::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'model_type' => $target ? $target->getMorphClass() : 'system',
            'model_id' => $target?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => Arr::only($context, [
                'method',
                'route',
                'request_id',
                'source',
            ]),
            'request_id' => $request->headers->get('X-Request-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model_type', 'model_id');
    }

    public function scopeForTarget(Builder $query, Model $target): Builder
    {
        return $query->where('model_type', $target->getMorphClass())
            ->where('model_id', $target->getKey());
    }

    private static function withoutSecrets(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $key));

            if (preg_match('/password|secret|token|authorization|cookie|credential|cvv|cardnumber|privatekey|apikey/', $normalizedKey)) {
                continue;
            }

            $safe[$key] = is_array($value) ? self::withoutSecrets($value) : $value;
        }

        return $safe;
    }
}
