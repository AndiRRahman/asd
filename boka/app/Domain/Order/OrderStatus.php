<?php

namespace App\Domain\Order;

/**
 * Tujuh status pesanan dan transisi yang diizinkan (bagian 5).
 */
enum OrderStatus: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Diproses = 'diproses';
    case Dikirim = 'dikirim';
    case Selesai = 'selesai';
    case Kedaluwarsa = 'kedaluwarsa';
    case Dibatalkan = 'dibatalkan';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::MenungguPembayaran => [self::MenungguVerifikasi, self::Kedaluwarsa, self::Dibatalkan],
            self::MenungguVerifikasi => [self::Diproses, self::MenungguPembayaran, self::Dibatalkan],
            self::Diproses => [self::Dikirim, self::Dibatalkan],
            self::Dikirim => [self::Selesai],
            self::Selesai, self::Kedaluwarsa, self::Dibatalkan => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }
}
