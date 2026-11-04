<?php

namespace App\Domain\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien RajaOngkir API V2 (Komerce). API key hanya di server.
 * Mengembalikan null jika layanan tidak tersedia, agar checkout tetap menawarkan kurir lokal dan ambil sendiri.
 */
class RajaOngkirClient
{
    public function configured(): bool
    {
        return filled(config('boka.shipping.rajaongkir_key'));
    }

    /** @return list<array{id: string, label: string, district: string}> */
    public function searchDestinations(string $query): array
    {
        if (! $this->configured()) {
            return BoneDistricts::search($query);
        }

        try {
            $response = $this->http()->get('/destination/domestic-destination', ['search' => $query, 'limit' => 10])->throw();

            return collect($response->json('data', []))->map(fn ($row) => [
                'id' => (string) $row['id'],
                'label' => $row['label'],
                'district' => $row['district_name'] ?? '',
            ])->all();
        } catch (Throwable $e) {
            Log::warning('RajaOngkir destination gagal', ['error' => $e->getMessage()]);

            return BoneDistricts::search($query);
        }
    }

    /**
     * Hasil disimpan di cache 12 jam per titik asal, tujuan, berat (dibulatkan ke 1 kg), dan kurir.
     *
     * @return list<array{courier: string, service: string, description: string, cost: int, etd: string}>|null
     */
    public function cost(?string $origin, string $destination, int $weightGram): ?array
    {
        if (! $this->configured() || ! $origin || str_starts_with($destination, 'bone:')) {
            return null;
        }

        $couriers = implode(':', config('boka.shipping.couriers'));
        $weight = (int) ceil(max($weightGram, 1) / 1000) * 1000;
        $key = "ongkir:{$origin}:{$destination}:{$weight}:{$couriers}";

        try {
            return Cache::remember($key, now()->addHours(config('boka.shipping.cache_hours')), function () use ($origin, $destination, $weight, $couriers) {
                $response = $this->http()->asForm()->post('/calculate/domestic-cost', [
                    'origin' => $origin,
                    'destination' => $destination,
                    'weight' => $weight,
                    'courier' => $couriers,
                    'price' => 'lowest',
                ])->throw();

                return collect($response->json('data', []))->map(fn ($row) => [
                    'courier' => strtoupper($row['code']),
                    'service' => $row['service'],
                    'description' => $row['description'] ?? '',
                    'cost' => (int) $row['cost'],
                    'etd' => (string) ($row['etd'] ?? ''),
                ])->all();
            });
        } catch (Throwable $e) {
            Log::warning('RajaOngkir cost gagal', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function http()
    {
        return Http::baseUrl(config('boka.shipping.rajaongkir_url'))
            ->withHeaders(['key' => config('boka.shipping.rajaongkir_key')])
            ->acceptJson()
            ->timeout(8);
    }
}
