<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CLIENT = 'client';
    public const ROLE_OWNER = 'owner';
    public const ROLE_AGENT = 'agent';
    public const ROLE_ADMIN = 'admin';

    public const STATUS_PENDING = 'pending_review';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'email', 'phone', 'nif', 'role', 'status',
        'document_path', 'document_name', 'password', 'phone_verified_at',
        'iban', 'express_number',
    ];

    protected $hidden = [
        'password', 'remember_token', 'document_path', 'iban', 'express_number',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN && $this->status === self::STATUS_ACTIVE;
    }

    public function isPublisher(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_AGENT], true);
    }

    public function canPublish(): bool
    {
        return $this->isPublisher() && $this->status === self::STATUS_ACTIVE;
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function availability()
    {
        return $this->hasOne(Availability::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function toAuthArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nif' => $this->nif,
            'role' => $this->role,
            'status' => $this->status,
            'phone_verified_at' => optional($this->phone_verified_at)?->toIso8601String(),
        ];
    }
}
