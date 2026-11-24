<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    public const PURPOSE_VISIT = 'visit';
    public const PURPOSE_RENTAL = 'rental';
    public const PURPOSE_COMMISSION = 'commission';

    protected $fillable = [
        'payable_type', 'payable_id', 'purpose', 'gateway', 'method', 'reference',
        'external_id', 'idempotency_key', 'amount', 'currency', 'entity', 'status',
        'instructions', 'audit', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'instructions' => 'array',
        'audit' => 'array',
        'paid_at' => 'datetime',
    ];

    public function payable()
    {
        return $this->morphTo();
    }
}
