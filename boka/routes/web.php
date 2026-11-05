<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ContentController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SearchController;
use Illuminate\Support\Facades\Route;

/*
 * Halaman konten punya URL per bahasa (ADR-9): tanpa prefiks untuk Indonesia, /en untuk Inggris.
 * Penanda 'localized' memberi tahu SetLocale bahwa bahasa ditentukan oleh URL.
 */
$content = function (string $prefix) {
    Route::get('/', HomeController::class)->name($prefix.'home');
    Route::get('produk', [CatalogController::class, 'index'])->name($prefix.'catalog');
    Route::get('produk/{slug}', [ProductController::class, 'show'])->name($prefix.'product');
    Route::get('kategori/{category}', [CatalogController::class, 'index'])->name($prefix.'category');
    Route::get('produsen/{slug}', [CatalogController::class, 'producer'])->name($prefix.'producer');
    Route::get('kabar', [ContentController::class, 'posts'])->name($prefix.'posts');
    Route::get('kabar/{slug}', [ContentController::class, 'post'])->name($prefix.'post');
    Route::get('halaman/{slug}', [ContentController::class, 'page'])->name($prefix.'page');
    Route::get('rekening-resmi', [ContentController::class, 'officialAccounts'])->name($prefix.'official-accounts');
};

Route::group(['localized' => true], fn () => $content(''));

Route::middleware('throttle:search')->group(function () {
    Route::get('cari/saran', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::post('cari/log', [SearchController::class, 'log'])->name('search.log');
});

Route::get('invoice/verifikasi/{code}', [ContentController::class, 'verifyInvoice'])->name('invoice.verify');

Route::middleware('guest')->group(function () {
    Route::get('undangan/{invitation}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('undangan/{invitation}', [InvitationController::class, 'accept'])->name('invitation.accept');
});

Route::get('keranjang', [CartController::class, 'index'])->name('cart');
Route::post('keranjang', [CartController::class, 'store'])->name('cart.store');
Route::post('keranjang/sesuaikan', [CartController::class, 'adjust'])->name('cart.adjust');
Route::patch('keranjang/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('keranjang/{variant}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::post('preferensi/bahasa', [AccountController::class, 'locale'])->name('preferences.locale');
Route::post('preferensi/tema', [AccountController::class, 'theme'])->name('preferences.theme');

Route::get('wilayah', [AccountController::class, 'destinations'])->middleware('throttle:60,1')->name('destinations');

// Checkout dan pesanan dapat diakses langsung tanpa harus login (Pesan Tanpa Login).
Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('checkout/rincian', [CheckoutController::class, 'quote'])->name('checkout.quote');
Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('pesanan/{code}', [OrderController::class, 'show'])->name('orders.show');
Route::get('pesanan/{code}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
Route::post('pesanan/{code}/bukti', [OrderController::class, 'proof'])->middleware('throttle:proof-upload')->name('orders.proof');

Route::middleware('auth')->group(function () {
    Route::get('pesanan', [OrderController::class, 'index'])->name('orders');
    Route::post('pesanan/{code}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('pesanan/{code}/diterima', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('pesanan/{code}/ulasan', [OrderController::class, 'review'])->name('orders.review');

    Route::get('akun/alamat', [AccountController::class, 'addresses'])->name('addresses');
    Route::post('akun/alamat', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('akun/alamat/{id}', [AccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('akun/alamat/{id}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::get('akun/data-saya', [AccountController::class, 'exportData'])->name('account.export');

    Route::get('wishlist', [AccountController::class, 'wishlist'])->name('wishlist');
    Route::post('wishlist/{product}', [AccountController::class, 'toggleWishlist'])->name('wishlist.toggle');

    // Tujuan bawaan Fortify setelah registrasi dan verifikasi email: staf ke panel, Pembeli ke beranda.
    Route::get('dashboard', fn () => redirect(request()->user()->can('panel.akses') ? '/panel' : '/'))->name('dashboard');
});

require __DIR__.'/settings.php';
