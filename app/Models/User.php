<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'name_en',
        'email',
        'password',
        'thaid_pid',
        'pid',
        'birthdate',
        'address',
        'provider_id',
        'cid_hash',
        'hcode',
        'hname',
    ];

    /**
     * Ensure cid_hash is generated when creating a user
     */
    protected static function booted()
    {
        static::creating(function ($user) {
            // Generate cid_hash if not already set and thaid_pid is available
            if (empty($user->cid_hash) && !empty($user->thaid_pid)) {
                $user->cid_hash = hash('sha256', $user->thaid_pid);
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'cid_hash'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
        ];
    }
}
