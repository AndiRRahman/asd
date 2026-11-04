<?php

namespace App\Domain\Payment;

use App\Domain\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Pengembalian dana terkendali (11.1 T8, K30): hanya ke rekening atas nama Pembeli atau nama pengirim
 * di bukti transfer, butuh dua orang berbeda, dan bukti transfer refund wajib diunggah.
 */
class RefundService
{
    public function request(Order $order, User $admin, array $data): OrderRefund
    {
        $this->authorize($admin);

        $allowedNames = collect([$order->user?->name, ...$order->proofs()->pluck('sender_name')->all()])
            ->filter()
            ->map(fn ($n) => Str::lower(Str::squish($n)));

        if (! $allowedNames->contains(Str::lower(Str::squish($data['account_holder'])))) {
            throw new BusinessRuleException('Refund hanya boleh ke rekening atas nama Pembeli atau nama pengirim pada bukti transfer.', 'refund_name_mismatch');
        }

        if ($data['amount'] < 1 || $data['amount'] > $order->grand_total - $order->refunds()->where('status', '!=', 'ditolak')->sum('amount')) {
            throw new BusinessRuleException('Nominal refund melebihi sisa yang bisa dikembalikan.');
        }

        return $order->refunds()->create([...$data, 'requested_by' => $admin->id, 'status' => 'diajukan']);
    }

    public function approve(OrderRefund $refund, User $approver, ?UploadedFile $proof): void
    {
        $this->authorize($approver);

        if ($refund->requested_by === $approver->id) {
            throw new BusinessRuleException('Refund harus disetujui orang yang berbeda dari pengaju.', 'same_approver', 403);
        }

        if (! $proof) {
            throw new BusinessRuleException('Unggah bukti transfer refund sebelum menandai selesai.', 'refund_proof_required');
        }

        $refund->update([
            'status' => 'selesai',
            'approved_by' => $approver->id,
            'proof_path' => $proof->store('refunds', 'local'),
            'refunded_at' => now(),
        ]);
    }

    private function authorize(User $admin): void
    {
        if (! $admin->can('refund.proses')) {
            throw new AuthorizationException('Hanya Admin Transaksi dan Super Admin yang bisa memproses refund.');
        }
    }
}
