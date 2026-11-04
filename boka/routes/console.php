<?php

use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Models\Order;
use App\Models\SearchLog;
use App\Models\User;
use App\Notifications\OrderUpdate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

/*
 * Penjadwal (cron tiap menit, bagian 8): kedaluwarsa, pengingat bayar, selesai otomatis, perawatan data.
 */

Artisan::command('boka:expire-orders', function (OrderService $orders) {
    $expired = 0;

    Order::query()
        ->where('status', OrderStatus::MenungguPembayaran)
        ->where('payment_deadline_at', '<=', now())
        ->each(function (Order $order) use ($orders, &$expired) {
            $expired += (int) $orders->expireIfDue($order);
        });

    $this->info("{$expired} pesanan kedaluwarsa.");
})->purpose('Kedaluwarsakan pesanan yang lewat batas bayar dan kembalikan stoknya');

Artisan::command('boka:payment-reminders', function () {
    Order::query()
        ->where('status', OrderStatus::MenungguPembayaran)
        ->whereBetween('payment_deadline_at', [now(), now()->addHours(config('boka.reminder_hours_before_deadline'))])
        ->with('user')
        ->each(function (Order $order) {
            // Cache::add hanya berhasil sekali, jadi satu pesanan mendapat satu pengingat.
            if (Cache::add("reminder:{$order->id}:{$order->payment_deadline_at->timestamp}", true, now()->addDay())) {
                $order->user->notify(new OrderUpdate($order, 'pengingat'));
            }
        });
})->purpose('Kirim pengingat 2 jam sebelum batas bayar');

Artisan::command('boka:auto-complete', function (OrderService $orders) {
    Order::query()
        ->where('status', OrderStatus::Dikirim)
        ->whereDoesntHave('shipments', fn ($q) => $q->where('shipped_at', '>', now()->subDays(config('boka.auto_complete_days'))))
        ->each(fn (Order $order) => $orders->complete($order));
})->purpose('Selesaikan pesanan 7 hari setelah paket terakhir dikirim');

Artisan::command('boka:prune-search-logs', function () {
    $deleted = SearchLog::query()->where('created_at', '<', now()->subDays(180))->delete();
    $this->info("{$deleted} log pencarian lama dihapus.");
})->purpose('Hapus log pencarian mentah berumur lebih dari 180 hari');

Artisan::command('boka:super-admin {email} {name}', function (string $email, string $name) {
    $password = $this->secret('Password (minimal 12 karakter)');

    Validator::make(['email' => $email, 'password' => $password], [
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'string', 'min:12'],
    ])->validate();

    $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole('super_admin');

    $this->info("Super Admin {$email} dibuat. Aktifkan 2FA saat login pertama. Siapkan minimal dua Super Admin.");
})->purpose('Buat akun Super Admin (hanya lewat server, bagian 2)');

Schedule::command('boka:expire-orders')->everyMinute()->withoutOverlapping();
Schedule::command('boka:payment-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('boka:auto-complete')->hourly();
Schedule::command('boka:prune-search-logs')->dailyAt('02:00')->timezone('Asia/Makassar');
