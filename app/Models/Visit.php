<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'property_id', 'agent_id', 'scheduled_at', 'status',
        'code', 'hold_expires_at', 'paid_at', 'checked_in_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'hold_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function isHoldActive(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->hold_expires_at
            && $this->hold_expires_at->isFuture();
    }
}
