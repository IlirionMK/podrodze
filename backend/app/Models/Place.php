<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    protected $fillable = [
        'name',
        'category_slug',
        'rating',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'trip_place')
            ->withPivot(['order_index', 'status', 'note'])
            ->withTimestamps();
    }

}
