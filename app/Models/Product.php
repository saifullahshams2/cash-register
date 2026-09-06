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

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->code)) {
                $product->code = 'PRD-'.strtoupper(substr(uniqid(), -6));
            }
            if (empty($product->category)) {
                $product->category = 'General';
            }
            if (is_null($product->price)) {
                $product->price = 0.000;
            }
            if (is_null($product->stock)) {
                $product->stock = 9999;
            }
        });
    }

    public function getFormattedPriceAttribute(): string
    {
        return Setting::formatMoney($this->price).' '.Setting::getCurrency();
    }
}
