<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'slug',
        'translations',
        'include_in_preferences',
    ];

    protected $casts = [
        'translations'           => 'array',
        'include_in_preferences' => 'boolean',
    ];

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();

        return $this->translations[$locale]
            ?? $this->translations['en']
            ?? $this->slug;
    }
}
