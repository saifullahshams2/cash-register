<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();

            return $setting && ! is_null($setting->value) ? $setting->value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getCurrency(): string
    {
        return strtoupper((string) static::get('currency_code', 'KWD'));
    }

    public static function getCurrencyDecimals(): int
    {
        return max(0, min(4, (int) static::get('currency_decimals', 3)));
    }

    public static function formatMoney(float|int|string|null $amount): string
    {
        $decimals = static::getCurrencyDecimals();

        return number_format((float) $amount, $decimals, '.', '');
    }
}
