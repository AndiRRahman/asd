<?php

namespace App\Domain\Payment;

use App\Domain\BusinessRuleException;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStateMachine;
use App\Domain\Order\OrderStatus;
use App\Domain\Promotion\VoucherService;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentProofSubmitted;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembayaran semi-otomatis (bagian 5). Pembayaran hanya sah jika dana terlihat di mutasi rekening;
 * gambar bukti hanya alat bantu pencocokan (11.1).
 */
class PaymentService
{
    public const REJECT_TEMPLATES = [
        'Nominal tidak sesuai dengan tagihan',
        'Dana belum masuk di mutasi rekening tujuan',
        'Bukti transfer tidak terbaca',
        'Rekening tujuan salah',
        'Bukti transfer pernah dipakai di pesanan lain',
    ];

    public function __construct(
        private OrderStateMachine $machine,
        private OrderService $orders,
        private VoucherService $vouchers,
    ) {}

    public function submitProof(Order $order, User $buyer, UploadedFile $file, string $senderBank, string $senderName, string $transferredAt): PaymentProof
    {
        $this->orders->expireIfDue($order);
        $order->refresh();

        if ($order->status !== OrderStatus::MenungguPembayaran && $order->status !== OrderStatus::MenungguVerifikasi) {
            throw new BusinessRuleException(
                $order->status === OrderStatus::Kedaluwarsa ? 'Batas waktu pembayaran sudah lewat.' : 'Pesanan ini tidak sedang menunggu pembayaran atau verifikasi.',
                'payment_deadline_passed',
                409,
            );
        }

        $contents = $file->get();
        $sha = hash('sha256', $contents);
        $name = Str::random(40);

        // Salinan tampilan di-encode ulang (membuang EXIF dan muatan tersembunyi); asli disimpan terenkripsi (T15).
        $viewPath = "proofs/{$name}.".($file->getMimeType() === 'application/pdf' ? 'pdf' : 'jpg');
        Storage::disk('local')->put($viewPath, $file->getMimeType() === 'application/pdf' ? $contents : $this->reencode($contents));
        Storage::disk('local')->put("proofs/original/{$name}.enc", Crypt::encryptString(base64_encode($contents)));

        return DB::transaction(function () use ($order, $buyer, $viewPath, $sha, $senderBank, $senderName, $transferredAt) {
            $proof = $order->proofs()->create([
                'file_path' => $viewPath,
                'file_sha256' => $sha,
                'sender_bank' => $senderBank,
                'sender_name' => $senderName,
                'transferred_at' => $transferredAt,
            ]);

            if ($order->status !== OrderStatus::MenungguVerifikasi) {
                $this->machine->transition($order, OrderStatus::MenungguVerifikasi, $buyer, 'Bukti transfer diunggah', notify: true);
            }

            Notification::send(
                User::permission('pembayaran.verifikasi')->get(),
                new PaymentProofSubmitted($order),
            );

            return $proof;
        });
    }

    /** Peringatan "bukti ini pernah dipakai di pesanan X" (K9). */
    public function duplicateOrders(PaymentProof $proof): array
    {
        return PaymentProof::query()
            ->where('file_sha256', $proof->file_sha256)
            ->where('order_id', '!=', $proof->order_id)
            ->with('order:id,code')
            ->get()
            ->pluck('order.code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Setujui pembayaran. Tombol Setujui baru aktif setelah daftar periksa mutasi terisi (T1, K27).
     * Pesanan di atas ambang butuh persetujuan kedua dari orang lain (T3).
     *
     * @return string 'disetujui' atau 'menunggu_persetujuan_kedua'
     */
    public function approve(Order $order, User $admin, bool $mutationChecked, ?string $mutationDatetime): string
    {
        $this->authorize($admin);

        if ($order->status !== OrderStatus::MenungguVerifikasi) {
            throw new BusinessRuleException('Pesanan ini tidak sedang menunggu verifikasi.', 'invalid_transition', 409);
        }

        if (! $mutationChecked || blank($mutationDatetime)) {
            throw new BusinessRuleException('Centang daftar periksa mutasi rekening dan isi tanggal serta jam mutasi.', 'mutation_check_required');
        }

        $proof = $order->latestProof;
        $needsSecond = $order->grand_total > (int) Setting::get('second_approval_threshold');

        return DB::transaction(function () use ($order, $admin, $proof, $needsSecond, $mutationDatetime) {
            if ($proof) {
                if ($needsSecond && $proof->status !== 'disetujui_1') {
                    $proof->update(['status' => 'disetujui_1', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'mutation_datetime' => Carbon::parse($mutationDatetime, 'Asia/Makassar')]);
                    activity()->performedOn($order)->causedBy($admin)->log('Persetujuan pertama pembayaran');

                    return 'menunggu_persetujuan_kedua';
                }

                if ($needsSecond) {
                    if ($proof->reviewed_by === $admin->id) {
                        throw new BusinessRuleException('Persetujuan kedua harus dilakukan oleh Admin Transaksi lain atau Super Admin.', 'same_approver', 403);
                    }

                    $proof->update(['status' => 'disetujui', 'second_reviewed_by' => $admin->id]);
                } else {
                    $proof->update(['status' => 'disetujui', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'mutation_datetime' => Carbon::parse($mutationDatetime, 'Asia/Makassar')]);
                }
            }

            $this->machine->transition($order, OrderStatus::Diproses, $admin, 'Pembayaran disetujui');
            $this->vouchers->markUsed($order);
            activity()->performedOn($order)->causedBy($admin)->withProperties(['grand_total' => $order->grand_total])->log('Pembayaran disetujui');

            return 'disetujui';
        });
    }

    /** Penolakan pertama memberi batas bayar baru; penolakan kedua membatalkan pesanan (K5). */
    public function reject(Order $order, User $admin, string $reason): void
    {
        $this->authorize($admin);

        if ($order->status !== OrderStatus::MenungguVerifikasi) {
            throw new BusinessRuleException('Pesanan ini tidak sedang menunggu verifikasi.', 'invalid_transition', 409);
        }

        DB::transaction(function () use ($order, $admin, $reason) {
            $order->latestProof?->update(['status' => 'ditolak', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'reject_reason' => $reason]);
            $order->increment('rejection_count');

            if ($order->rejection_count >= 2) {
                $this->machine->transition($order, OrderStatus::Dibatalkan, $admin, "Bukti ditolak kedua kali: {$reason}");
                $order->update(['cancel_reason' => $reason]);
                $this->orders->releaseReservations($order);

                return;
            }

            $order->update(['payment_deadline_at' => now()->addHours((int) Setting::get('reupload_deadline_hours'))]);
            $this->machine->transition($order, OrderStatus::MenungguPembayaran, $admin, "Bukti ditolak: {$reason}");
        });
    }

    private function authorize(User $admin): void
    {
        if (! $admin->can('pembayaran.verifikasi')) {
            throw new AuthorizationException('Hanya Admin Transaksi dan Super Admin yang bisa memverifikasi pembayaran.');
        }
    }

    private function reencode(string $contents): string
    {
        $image = @imagecreatefromstring($contents);

        if (! $image) {
            throw new BusinessRuleException('File bukti tidak bisa dibaca sebagai gambar.', 'invalid_file');
        }

        $width = imagesx($image);

        if ($width > 1600) {
            $image = imagescale($image, 1600);
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }
}
