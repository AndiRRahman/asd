<?php

use App\Http\Controllers\Panel\AdminController;
use App\Http\Controllers\Panel\CustomerController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\OrderController;
use App\Http\Controllers\Panel\PaymentController;
use App\Http\Controllers\Panel\ProductController;
use App\Http\Controllers\Panel\ReportController;
use App\Http\Controllers\Panel\ResourceController;
use App\Http\Controllers\Panel\StockController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Semua rute di sini sudah melewati: web, auth, verified, panel (staf + 2FA + batas tidak aktif 2 jam).

Route::get('/', DashboardController::class)->name('dashboard');

Route::middleware('can:pesanan.kelola')->group(function () {
    Route::get('pembayaran', [PaymentController::class, 'index'])->name('payments');
    Route::get('pembayaran/{code}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('bukti/{proof}', [PaymentController::class, 'file'])->name('proofs.file');

    Route::get('pesanan', [OrderController::class, 'index'])->name('orders');
    Route::get('pesanan/{code}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('pesanan/{code}/label', [OrderController::class, 'label'])->name('orders.label');
    Route::post('pesanan/{code}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('paket/{shipment}/resi', [OrderController::class, 'tracking'])->name('shipments.tracking');
    Route::post('paket/{shipment}/siap-diambil', [OrderController::class, 'readyForPickup'])->name('shipments.ready');
    Route::post('paket/{shipment}/kode-ambil', [OrderController::class, 'verifyPickup'])->middleware('throttle:20,1')->name('shipments.pickup');
    Route::post('paket/{shipment}/terkirim', [OrderController::class, 'delivered'])->name('shipments.delivered');
});

// Hanya Admin Transaksi dan Super Admin (K31). Aksi sensitif butuh konfirmasi password.
Route::middleware(['can:pembayaran.verifikasi', RequirePassword::class])->group(function () {
    Route::post('pembayaran/{code}/setujui', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('pembayaran/{code}/tolak', [PaymentController::class, 'reject'])->name('payments.reject');
});

Route::middleware(['can:refund.proses', RequirePassword::class])->group(function () {
    Route::post('pesanan/{code}/refund', [OrderController::class, 'requestRefund'])->name('refunds.store');
    Route::post('refund/{refund}/setujui', [OrderController::class, 'approveRefund'])->name('refunds.approve');
});

Route::middleware('can:produk.kelola')->group(function () {
    Route::get('produk', [ProductController::class, 'index'])->name('products');
    Route::get('produk/baru', [ProductController::class, 'create'])->name('products.create');
    Route::post('produk', [ProductController::class, 'store'])->name('products.store');
    Route::post('produk/cek-serupa', [ProductController::class, 'checkSimilarity'])->name('products.similarity');
    Route::get('produk/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::post('produk/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('produk/{product}/status', [ProductController::class, 'status'])->name('products.status');
    Route::post('produk/{product}/gabung', [ProductController::class, 'merge'])->name('products.merge');
});

Route::middleware('can:stok.kelola')->group(function () {
    Route::post('varian/{variant}/stok', [ProductController::class, 'adjustStock'])->name('variants.stock');
    Route::get('stok', [StockController::class, 'index'])->name('stock');
    Route::post('stok/opname', [StockController::class, 'startOpname'])->name('opname.start');
    Route::get('stok/opname/{opname}', [StockController::class, 'showOpname'])->name('opname.show');
    Route::post('stok/opname/{opname}/hitung', [StockController::class, 'countOpname'])->name('opname.count');
    Route::post('stok/opname/{opname}/posting', [StockController::class, 'postOpname'])->name('opname.post');
});

// Modul pelanggan & ulasan dinonaktifkan sesuai permintaan.

Route::middleware('can:laporan.lihat')->group(function () {
    Route::get('laporan', [ReportController::class, 'index'])->name('reports');
    Route::get('laporan/ekspor/{report}', [ReportController::class, 'export'])->name('reports.export');
});

Route::get('kelola/{resource}', [ResourceController::class, 'index'])->name('resources.index');
Route::get('kelola/{resource}/baru', [ResourceController::class, 'create'])->name('resources.create');
Route::post('kelola/{resource}', [ResourceController::class, 'store'])->name('resources.store');
Route::get('kelola/{resource}/{id}', [ResourceController::class, 'edit'])->name('resources.edit');
Route::post('kelola/{resource}/{id}', [ResourceController::class, 'update'])->name('resources.update');
Route::delete('kelola/{resource}/{id}', [ResourceController::class, 'destroy'])->name('resources.destroy');

Route::get('audit', [AdminController::class, 'audit'])->name('audit');

Route::middleware('can:admin.kelola')->group(function () {
    Route::get('pengguna', [AdminController::class, 'users'])->name('users');
    Route::post('pengguna/undang', [AdminController::class, 'invite'])->name('users.invite');
    Route::post('pengguna/{user}', [AdminController::class, 'updateUser'])->name('users.update');
});

Route::middleware('can:pengaturan.kelola')->group(function () {
    Route::get('pengaturan', [AdminController::class, 'settings'])->name('settings');
    Route::post('pengaturan', [AdminController::class, 'saveSettings'])->name('settings.save');
    Route::post('rekening', [AdminController::class, 'storeBank'])->name('banks.store');
    Route::post('rekening/{bank}/status', [AdminController::class, 'toggleBank'])->name('banks.toggle');
    Route::get('sistem', [AdminController::class, 'system'])->name('system');
    Route::post('sistem/cache', [AdminController::class, 'clearCache'])->name('system.cache');
});
