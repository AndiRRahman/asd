<?php

namespace App\Domain\Shipping;

use App\Domain\BusinessRuleException;
use App\Models\LocalCourierRate;
use App\Models\Origin;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Mengelompokkan item per titik asal menjadi paket, lalu menyusun pilihan kirim tiap paket (bagian 3.5 dan 6).
 */
class ShippingQuoter
{
    public function __construct(private RajaOngkirClient $rajaOngkir) {}

    /**
     * @param  Collection<int, array>  $lines
     * @return Collection<int, array{origin: Origin, lines: Collection, weight_gram: int}>
     */
    public function packages(Collection $lines): Collection
    {
        $packaging = (int) Setting::get('packaging_weight_gram');

        return $lines
            ->groupBy(fn ($line) => $line['variant']->product->origin_id)
            ->map(fn (Collection $group) => [
                'origin' => $group->first()['variant']->product->origin,
                'lines' => $group->values(),
                'weight_gram' => (int) $group->sum(fn ($l) => $l['variant']->weight_gram * $l['qty']) + $packaging,
            ])
            ->values();
    }

    /**
     * @param  array{destination_id: string, district: string}  $destination
     * @return array{options: list<array{key: string, method: string, courier: ?string, service: ?string, label: string, cost: int, etd: ?string}>, expedition_unavailable: bool}
     */
    public function options(Origin $origin, int $weightGram, array $destination): array
    {
        $options = [];
        $expedition = $this->rajaOngkir->cost($origin->destination_id, $destination['destination_id'], $weightGram);

        foreach ($expedition ?? [] as $row) {
            $options[] = [
                'key' => "ekspedisi:{$row['courier']}:{$row['service']}",
                'method' => 'ekspedisi',
                'courier' => $row['courier'],
                'service' => $row['service'],
                'label' => "{$row['courier']} {$row['service']}",
                'cost' => $row['cost'],
                'etd' => $row['etd'] ?: null,
            ];
        }

        $localRate = LocalCourierRate::query()
            ->where('origin_id', $origin->id)
            ->where('destination_district', $destination['district'])
            ->value('fee');

        if ($localRate !== null) {
            $options[] = [
                'key' => 'lokal',
                'method' => 'lokal',
                'courier' => 'Kurir Lokal Boka',
                'service' => null,
                'label' => 'Kurir lokal Kabupaten Bone',
                'cost' => (int) $localRate,
                'etd' => '1-2 hari',
            ];
        }

        if ($origin->pickup_enabled) {
            $options[] = [
                'key' => 'ambil',
                'method' => 'ambil',
                'courier' => null,
                'service' => null,
                'label' => "Ambil sendiri di {$origin->name}",
                'cost' => 0,
                'etd' => $origin->pickup_hours,
            ];
        }

        return ['options' => $options, 'expedition_unavailable' => $expedition === null];
    }

    /** @param  list<array>  $options */
    public function pick(array $options, ?string $key): array
    {
        $choice = collect($options)->firstWhere('key', $key);

        if (! $choice) {
            throw new BusinessRuleException('Pilih metode kirim untuk setiap paket.', 'shipping_required');
        }

        return $choice;
    }
}
