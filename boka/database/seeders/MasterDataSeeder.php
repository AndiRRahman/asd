<?php

namespace Database\Seeders;

use App\Domain\Shipping\BoneDistricts;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Producer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asal Pengiriman
        $originsData = [
            'watampone' => ['Gudang Watampone', 'Jl. Jenderal Sudirman No. 12, Watampone', 'Tanete Riattang', true, 'Senin–Sabtu, 08.00–17.00 WITA'],
            'bontocani' => ['Titik Bontocani', 'Jl. Poros Kahu–Bontocani, Desa Pattuku', 'Bontocani', false, null],
            'bajoe' => ['Titik Pelabuhan Bajoe', 'Jl. Pelabuhan Bajoe, Kel. Bajoe', 'Tanete Riattang Timur', true, 'Setiap hari, 07.00–15.00 WITA'],
        ];

        $origins = [];
        foreach ($originsData as $key => [$name, $address, $district, $pickup, $hours]) {
            $origin = Origin::query()->create([
                'name' => $name, 'address' => $address, 'district' => $district,
                'contact' => '0812-4000-'.str_pad((string) (count($origins) + 1), 4, '0', STR_PAD_LEFT),
                'couriers' => ['jne', 'jnt', 'pos'], 'pickup_enabled' => $pickup, 'pickup_hours' => $hours,
            ]);

            $originIndex = array_search($district, BoneDistricts::ALL, true);
            foreach (BoneDistricts::ALL as $i => $destination) {
                $distance = abs($i - $originIndex);
                $origin->localRates()->create([
                    'destination_district' => $destination,
                    'fee' => $distance === 0 ? 8_000 : min(35_000, 12_000 + $distance * 1_000),
                ]);
            }
            $origins[$key] = $origin;
        }

        // 2. Kategori Produk
        $categoriesTree = [
            ['KOP', 'kopi-minuman', 'Kopi & Minuman', 'coffee', [['KPB', 'kopi', 'Kopi'], ['GAR', 'gula-aren', 'Gula Aren']]],
            ['PGN', 'olahan-pangan', 'Olahan Pangan', 'cookie', [['KUE', 'kue-tradisional', 'Kue Tradisional'], ['KRP', 'keripik-camilan', 'Keripik & Camilan']]],
            ['KRJ', 'kerajinan', 'Kerajinan', 'gem', [['ANY', 'anyaman', 'Anyaman'], ['UKR', 'ukiran-kayu', 'Ukiran Kayu']]],
            ['TAN', 'pertanian-rempah', 'Pertanian & Rempah', 'leaf', [['MDU', 'madu', 'Madu'], ['RMP', 'rempah', 'Rempah']]],
        ];

        foreach ($categoriesTree as $order => [$code, $slug, $name, $icon, $children]) {
            $parent = Category::query()->create([
                'name' => ['id' => $name], 'slug' => $slug, 'code' => $code, 'icon' => $icon, 'sort_order' => $order,
            ]);

            foreach ($children as $childOrder => [$childCode, $childSlug, $childName]) {
                Category::query()->create([
                    'parent_id' => $parent->id, 'name' => ['id' => $childName],
                    'slug' => $childSlug, 'code' => $childCode, 'icon' => $icon, 'sort_order' => $childOrder,
                ]);
            }
        }

        // 3. Produsen / Mitra UMKM & Karang Taruna
        $producersData = [
            'kt-desa' => ['Karang Taruna Desa', 'karang_taruna', 'Pattuku', 'Bontocani', 'bontocani', 'Kelompok pemuda desa yang memproduksi olahan pangan dan minuman lokal.'],
            'umkm-desa' => ['UMKM Binaan Desa', 'umkm', 'Macege', 'Tanete Riattang Barat', 'watampone', 'UMKM lokal desa yang memproduksi aneka produk khas.'],
        ];

        foreach ($producersData as $key => [$name, $type, $village, $district, $originKey, $story]) {
            Producer::query()->create([
                'name' => $name, 'slug' => Str::slug($name), 'type' => $type, 'village' => $village, 'district' => $district,
                'story' => ['id' => $story], 'contact' => '0813-5500-1111',
                'default_origin_id' => $origins[$originKey]->id,
            ]);
        }

        // 4. Rekening Bank
        BankAccount::query()->create([
            'bank_name' => 'BRI',
            'account_number' => '0123-01-000456-30-1',
            'account_holder' => 'Karang Taruna Pengelola',
            'active_from' => now()->subDays(1),
        ]);
    }
}

