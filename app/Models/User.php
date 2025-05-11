<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const ROLE_USER = 'user';
    const ROLE_RELAWAN = 'relawan';
    const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nik',
        'no_telp',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isRelawan()
    {
        return $this->role === self::ROLE_RELAWAN;
    }

    public function isUser()
    {
        return $this->role === self::ROLE_USER;
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function createRefreshToken()
    {
        $this->refreshTokens()->delete();

        $expiresAt = now()->addMinutes(config('sanctum.refresh_expiration', 60 * 24 * 7));

        $token = \Str::random(80);

        $refreshToken = $this->refreshTokens()->create([
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        return $refreshToken;
    }
}