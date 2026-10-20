<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'content',
        'resource_type',
        'category',
        'resource_group',
        'tags',
        'file_name',
        'file_path',
        'file_url',
        'video_embed_url',
        'estimated_minutes',
        'status',
        'visibility',
        'is_featured',
        'requires_acknowledgment',
        'version',
        'created_by',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'tags' => 'array',
        'requires_acknowledgment' => 'boolean',
        'estimated_minutes' => 'integer',
        'version' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ResourceVersion::class)->orderByDesc('version');
    }

    public function acknowledgments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ResourceAcknowledgment::class);
    }
}
