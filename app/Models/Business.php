<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    protected $table = 'clients';

    protected $fillable = [
        'company_name',
        'slug',
        'description',
        'tagline',
        'short_description',
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
        'alternate_phone',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'tiktok_url',
        'youtube_url',
        'other_social_url',
        'latitude',
        'longitude',
        'business_hours',
        'specialties',
        'year_established',
        'employee_count',
        'ownership_information',
        'accessibility_information',
        'appointment_methods',
        'service_areas',
        'meta_title',
        'meta_description',
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
        'business_hours' => 'array',
        'specialties' => 'array',
        'appointment_methods' => 'array',
        'service_areas' => 'array',
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

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'business_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'business_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'business_id');
    }

    public function marketingActivities(): HasMany
    {
        return $this->hasMany(MarketingActivity::class, 'business_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'business_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(BusinessMetric::class, 'business_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BusinessCategory::class, 'business_business_category')
            ->withPivot('is_primary')
            ->orderByDesc('business_business_category.is_primary')
            ->orderBy('name');
    }

    public function images(): HasMany
    {
        return $this->hasMany(BusinessImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BusinessHour::class)->orderBy('day_of_week');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(BusinessHighlight::class)->orderBy('sort_order')->orderBy('id');
    }

    public function video(): HasOne
    {
        return $this->hasOne(BusinessVideo::class);
    }
}
