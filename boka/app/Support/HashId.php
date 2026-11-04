<?php

namespace App\Support;

use Hashids\Hashids;

/**
 * Menyamarkan ID angka berurutan (12, 13, 14...) menjadi kode acak di URL dan data yang dikirim ke browser,
 * sehingga ID tidak bisa ditebak atau dihitung. Kunci samaran diturunkan dari APP_KEY.
 * Ini lapisan tambahan; setiap akses tetap diperiksa kepemilikan dan izinnya di server.
 */
final class HashId
{
    private static ?Hashids $hashids = null;

    public static function encode(int $id): string
    {
        return self::hashids()->encode($id);
    }

    public static function decode(mixed $hash): ?int
    {
        if (! is_string($hash) || $hash === '') {
            return null;
        }

        $decoded = self::hashids()->decode($hash);

        return count($decoded) === 1 ? (int) $decoded[0] : null;
    }

    /** Kode yang tidak sah dianggap data tidak ada (404), bukan galat. */
    public static function decodeOrFail(mixed $hash): int
    {
        return self::decode($hash) ?? abort(404);
    }

    /**
     * Mendekode kunci array, misalnya pilihan kirim {hash titik asal: "lokal"}. Kunci tak sah dibuang.
     *
     * @return array<int, mixed>
     */
    public static function decodeKeys(array $values): array
    {
        $decoded = [];

        foreach ($values as $hash => $value) {
            if (($id = self::decode((string) $hash)) !== null) {
                $decoded[$id] = $value;
            }
        }

        return $decoded;
    }

    /** @return list<int> */
    public static function decodeMany(array $hashes): array
    {
        return array_values(array_filter(array_map(fn ($hash) => self::decode($hash), $hashes), fn ($id) => $id !== null));
    }

    private static function hashids(): Hashids
    {
        return self::$hashids ??= new Hashids(hash('sha256', 'boka-id|'.config('app.key')), 10);
    }
}
