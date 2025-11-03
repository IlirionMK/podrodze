<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'start_date', 'end_date', 'owner_id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function members()
    {
        return $this->belongsToMany(\App\Models\User::class, 'trip_user')
            ->withPivot(['role', 'status', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }
    public function places()
    {
        return $this->belongsToMany(Place::class, 'trip_place')
            ->withPivot(['order_index', 'status', 'note'])
            ->withTimestamps();
    }

}
