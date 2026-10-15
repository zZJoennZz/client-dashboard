<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Agreement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'uploaded_by',
        'approved_by',
        'title',
        'description',
        'doc_type',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'disk',
        'mime_type',
        'status',
        'is_active',
        'signed_at',
        'effective_date',
        'expires_at',
        'reminder_sent_at',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'signed_at' => 'datetime',
        'effective_date' => 'datetime',
        'expires_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    /**
     * Agreement types for Selph Marketing client contracts.
     */
    public const TYPES = [
        'general' => 'General Agreement',
        'service_agreement' => 'Service Agreement',
        'mou' => 'Memorandum of Understanding',
        'nda' => 'Non-Disclosure Agreement',
        'sow' => 'Statement of Work',
        'proposal' => 'Proposal / Quote',
        'invoice' => 'Invoice',
        'addendum' => 'Addendum / Amendment',
        'termination' => 'Termination Notice',
        'renewal' => 'Renewal Agreement',
        'other' => 'Other',
    ];

    /**
     * Agreement status workflow.
     */
    public const STATUSES = [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'pending_signature' => 'Pending Signature',
        'signed' => 'Signed',
        'active' => 'Active',
        'expired' => 'Expired',
        'terminated' => 'Terminated',
        'cancelled' => 'Cancelled',
    ];

    /**
     * The client this agreement belongs to.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The Selph Marketing user who uploaded this agreement.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The Selph Marketing user who approved this agreement.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the full URL for the agreement file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get the display type name.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->doc_type] ?? ucfirst($this->doc_type);
    }

    /**
     * Get the display status name.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Scope: Active agreements only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by agreement type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('doc_type', $type);
    }

    /**
     * Scope: Filter by client.
     */
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope: Agreements expiring soon (within 30 days).
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>=', now());
    }

    /**
     * Scope: Currently effective agreements.
     */
    public function scopeCurrentlyEffective($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('effective_date')
              ->orWhere('effective_date', '<=', now());
        })->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>=', now());
        });
    }

    /**
     * Check if the agreement has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the agreement is pending signature.
     */
    public function isPendingSignature(): bool
    {
        return $this->status === 'pending_signature';
    }

    /**
     * Mark agreement as signed.
     */
    public function markAsSigned(): void
    {
        $this->update([
            'status' => 'signed',
            'signed_at' => now(),
        ]);
    }

    /**
     * Mark agreement as active.
     */
    public function markAsActive(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Mark agreement as terminated.
     */
    public function markAsTerminated(): void
    {
        $this->update(['status' => 'terminated']);
    }

    /**
     * Delete the file from storage when the model is force deleted.
     */
    protected static function booted(): void
    {
        static::forceDeleted(function (ClientAgreement $agreement) {
            if (Storage::disk($agreement->disk)->exists($agreement->file_path)) {
                Storage::disk($agreement->disk)->delete($agreement->file_path);
            }
        });
    }
}