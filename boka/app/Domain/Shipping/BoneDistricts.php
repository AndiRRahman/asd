<?php

namespace App\Domain\Shipping;

use Illuminate\Support\Str;

/**
 * 27 kecamatan Kabupaten Bone. Dipakai sebagai daftar tujuan cadangan
 * saat API ongkir belum dikonfigurasi, dan untuk tarif kurir lokal.
 */
final class BoneDistricts
{
    public const ALL = [
        'Ajangale', 'Amali', 'Awangpone', 'Barebbo', 'Bengo', 'Bontocani', 'Cenrana',
        'Cina', 'Dua Boccoe', 'Kahu', 'Kajuara', 'Lamuru', 'Lappariaja', 'Libureng',
        'Mare', 'Palakka', 'Patimpeng', 'Ponre', 'Salomekko', 'Sibulue', 'Tanete Riattang',
        'Tanete Riattang Barat', 'Tanete Riattang Timur', 'Tellu Limpoe', 'Tellu Siattinge',
        'Tonra', 'Ulaweng',
    ];

    /** @return list<array{id: string, label: string, district: string}> */
    public static function search(string $query): array
    {
        $query = Str::lower(trim($query));

        return collect(self::ALL)
            ->filter(fn ($d) => $query === '' || Str::contains(Str::lower($d), $query) || Str::contains('watampone bone', $query))
            ->map(fn ($d) => [
                'id' => 'bone:'.Str::slug($d),
                'label' => "Kec. {$d}, Kab. Bone, Sulawesi Selatan",
                'district' => $d,
            ])
            ->values()
            ->all();
    }
}
