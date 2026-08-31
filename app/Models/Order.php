<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'subtotal',
        'discount',
        'tax',
        'total',
        'tendered',
        'change',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:3',
        'discount' => 'decimal:3',
        'tax' => 'decimal:3',
        'total' => 'decimal:3',
        'tendered' => 'decimal:3',
        'change' => 'decimal:3',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
