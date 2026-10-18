<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_business_category')
            ->withPivot('is_primary');
    }
}
