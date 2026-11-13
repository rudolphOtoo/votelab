<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Table(key: 'key', keyType: 'string', incrementing: false)]
#[Fillable(['key', 'value'])]
final class PlatformSetting extends Model
{
    private const string CACHE_PREFIX = 'platform_setting:';

    public static function get(string $key, ?string $default = null): ?string
    {
        /** @var string|null $value */
        $value = Cache::rememberForever(self::CACHE_PREFIX . $key, static fn(): ?string => self::query()
            ->whereKey($key)
            ->value('value'));

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
