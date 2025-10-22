<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['slug', 'translations'];

    protected $casts = [
        'translations' => 'array',
    ];

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->translations[$locale] ?? $this->translations['en'] ?? $this->slug;
    }
}
