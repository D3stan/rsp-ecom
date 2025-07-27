<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'type',
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'phone',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'billing_address_id');
    }

    public function shippingOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }

    // Scopes
    public function scopeShipping($query)
    {
        return $query->where('type', 'shipping');
    }

    public function scopeBilling($query)
    {
        return $query->where('type', 'billing');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getFullAddressAttribute(): string
    {
        $address = $this->address_line_1;
        
        if ($this->address_line_2) {
            $address .= ', ' . $this->address_line_2;
        }
        
        $address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->postal_code;
        $address .= ', ' . $this->country;
        
        return $address;
    }
}
