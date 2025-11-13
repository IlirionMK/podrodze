<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $fillable = [
        'name',
        'google_place_id',
        'category_slug',
        'rating',
        'meta',
        'location',
    ];

    protected $casts = [
        'meta' => 'array',
        'rating' => 'float',
    ];

    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'trip_place')
            ->withPivot(['order_index', 'status', 'note'])
            ->withTimestamps();
    }
}
