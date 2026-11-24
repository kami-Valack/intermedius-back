<?php

namespace App\Models;

use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending_review';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RENTED = 'rented';
    public const STATUS_SOLD = 'sold';

    protected $fillable = [
        'user_id', 'title', 'category', 'type', 'price', 'province', 'municipality',
        'neighborhood', 'bedrooms', 'baths', 'area', 'private_address', 'private_lat',
        'private_lng', 'visual_pin_x', 'visual_pin_y', 'description', 'amenities',
        'status', 'rejection_reason', 'address_fingerprint',
    ];

    protected $casts = [
        'price' => 'integer',
        'amenities' => 'array',
        'private_lat' => 'decimal:7',
        'private_lng' => 'decimal:7',
        'visual_pin_x' => 'integer',
        'visual_pin_y' => 'integer',
    ];

    protected $hidden = [
        'private_address', 'private_lat', 'private_lng', 'address_fingerprint',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media()
    {
        return $this->hasMany(PropertyMedia::class)->orderBy('sort_order');
    }

    public function photos()
    {
        return $this->hasMany(PropertyMedia::class)->where('kind', 'photo')->orderBy('sort_order');
    }

    public function cover()
    {
        return $this->hasOne(PropertyMedia::class)->where('is_cover', true);
    }

    public function video()
    {
        return $this->hasOne(PropertyMedia::class)->where('kind', 'video');
    }

    public function agentLinks()
    {
        return $this->hasMany(AgentLink::class);
    }

    public function activeAgentLinks()
    {
        return $this->agentLinks()->whereIn('status', [AgentLink::STATUS_PENDING, AgentLink::STATUS_APPROVED]);
    }

    public function approvedAgents()
    {
        return $this->agentLinks()->where('status', AgentLink::STATUS_APPROVED);
    }

    public function tipologia(): string
    {
        return Normalizer::tipologia((int) $this->bedrooms);
    }

    public function period(): string
    {
        return $this->category === 'rent' ? '/ mês' : '';
    }

    public function badge(): string
    {
        return match ($this->category) {
            'rent' => 'Arrendamento',
            'sale' => 'Compra e venda',
            default => 'Comercial',
        };
    }
}
