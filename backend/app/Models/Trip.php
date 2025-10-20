<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $fillable = ['name','start_date','end_date','owner_id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function members()
    {
        return $this->belongsToMany(\App\Models\User::class, 'trip_user')
            ->withTimestamps()
            ->withPivot('role');
    }
    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

}
