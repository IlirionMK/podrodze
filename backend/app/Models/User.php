<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Trip;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, MustVerifyEmailTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'banned_at',
        'facebook_id',
        'google_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned_at' => 'datetime',
        ];
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'owner_id');
    }

    public function joinedTrips()
    {
        return $this->belongsToMany(Trip::class, 'trip_user')
            ->withPivot(['role', 'status', 'created_at', 'updated_at'])
            ->withTimestamps();
    }
    public function acceptedJoinedTrips()
    {
        return $this->belongsToMany(Trip::class, 'trip_user')
            ->wherePivot('status', 'accepted')
            ->withPivot(['role', 'status', 'created_at', 'updated_at'])
            ->withTimestamps();
    }


    public function preferences()
    {
        return $this->hasMany(\App\Models\UserPreference::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }
}
