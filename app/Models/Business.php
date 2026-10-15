<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $table = 'clients';

    protected $fillable = [
        'company_name',
        'slug',
        'description',
        'logo_url',
        'logo_path',
        'primary_color',
        'secondary_color',
        'timezone',
        'currency',
        'contact_name',
        'contact_email',
        'contact_phone',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'status',
        'is_active',
        'is_published',
        'is_featured',
        'directory_visibility',
        'published_at',
        'notes',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'settings' => 'array',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->company_name;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['company_name'] = $value;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot('role', 'access_level', 'status', 'is_primary', 'invited_at', 'accepted_at')
            ->withTimestamps();
    }

    public function businessUsers(): HasMany
    {
        return $this->hasMany(BusinessUser::class, 'business_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'business_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'business_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(BusinessMetric::class, 'business_id');
    }
}
