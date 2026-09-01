<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'price',
        'cost',
        'stock',
        'image',
        'color',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:3',
        'cost' => 'decimal:3',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 3, '.', '').' KWD';
    }
}
