<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'owner_id',
        'start_latitude',
        'start_longitude',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Automatically update PostGIS start_location when latitude/longitude change.
     */
    protected static function booted()
    {
        static::saving(function (Trip $trip) {
            if (!is_null($trip->start_latitude) && !is_null($trip->start_longitude)) {
                $lon = (float) $trip->start_longitude;
                $lat = (float) $trip->start_latitude;
                $trip->start_location = DB::raw("ST_SetSRID(ST_MakePoint($lon, $lat), 4326)");
            }
        });
    }


    public function members()
    {
        return $this->belongsToMany(User::class, 'trip_user')
            ->withPivot(['role', 'status', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function places()
    {
        return $this->belongsToMany(Place::class, 'trip_place')
            ->withPivot(['order_index', 'status', 'note'])
            ->withTimestamps();
    }
}
