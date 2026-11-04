<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan yang bisa diubah Super Admin. Nilai bawaan ada di config/boka.php.
 */
#[Unguarded]
class Setting extends Model
{
    use Audited;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key): mixed
    {
        $stored = Cache::rememberForever('settings', fn () => self::query()->pluck('value', 'key')->all());

        return $stored[$key] ?? config('boka.'.$key);
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
