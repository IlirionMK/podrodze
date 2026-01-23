<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Place extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'google_place_id',
        'category_slug',
        'rating',
        'meta',
        'opening_hours',
        'location',

    ];

    protected $casts = [
        'meta' => 'array',
        'rating' => 'float',
        'opening_hours' => 'array',
    ];

    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'trip_place')
            ->withPivot(['order_index', 'status', 'note'])
            ->withTimestamps();
    }
}
