<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripItinerary extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'schedule',
        'day_count',
        'generated_at',
    ];

    protected $casts = [
        'schedule' => 'array',
        'generated_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
