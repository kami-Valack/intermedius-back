<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'property_id', 'owner_id', 'agent_id', 'contract_number',
        'tenant_name', 'tenant_phone', 'tenant_nif', 'start_date', 'duration_months',
        'monthly_rent', 'platform_pct', 'platform_fee', 'tenant_total',
        'agent_commission_pct', 'agent_commission_amount', 'status', 'activated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'monthly_rent' => 'integer',
        'platform_fee' => 'integer',
        'tenant_total' => 'integer',
        'agent_commission_amount' => 'integer',
        'activated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable');
    }
}
