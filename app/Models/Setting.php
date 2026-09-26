<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
            $settings = Cache::rememberForever('app_settings', function () {
                return static::pluck('value', 'key')->all();
            });

            return array_key_exists($key, $settings) && ! is_null($settings[$key]) ? $settings[$key] : $default;
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
        
        Cache::forget('app_settings');
    }

    public static function getCurrency(): string
    {
        return 'KWD';
    }

    public static function getCurrencyDecimals(): int
    {
        return 3;
    }

    public static function formatMoney(float|int|string|null $amount): string
    {
        $decimals = static::getCurrencyDecimals();

        return number_format((float) $amount, $decimals, '.', '');
    }
}
