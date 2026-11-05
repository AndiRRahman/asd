<?php

/*
 * Nilai bawaan aturan bisnis Boka. Super Admin bisa menimpa nilai bertanda
 * "setting" lewat panel; nilainya disimpan di tabel settings (App\Models\Setting).
 */
return [

    'store' => [
        'name' => env('APP_NAME', 'Tejaseku'),
        'long_name' => env('APP_LONG_NAME', 'Tejaseku'),
        'tagline' => 'Platform Digitalisasi Pemasaran UMKM Desa',
        'email' => env('BOKA_EMAIL', 'halo@boka.id'),
        'whatsapp' => env('BOKA_WHATSAPP', '088246700573'),
        'address' => 'Jl. Jenderal Sudirman, Watampone, Kabupaten Bone, Sulawesi Selatan',
    ],

    'locales' => ['id'],

    // Bagian 2 meminta 2FA wajib untuk staf. Atas keputusan klien, 2FA dimatikan sementara karena
    // pengguna belum familiar. BOKA_TWO_FACTOR=true menyalakan lagi fitur 2FA, kewajibannya di panel,
    // dan kode 2FA saat mengubah rekening tujuan.
    'two_factor' => (bool) env('BOKA_TWO_FACTOR', false),

    // Bagian 5 dan 4.5
    'payment_deadline_hours' => 24,
    'flash_sale_deadline_hours' => 1,
    'reupload_deadline_hours' => 6,
    'reminder_hours_before_deadline' => 2,
    'auto_complete_days' => 7,
    'max_pending_orders' => 3,
    'expired_block_count' => 3,
    'expired_block_window_days' => 7,
    'checkout_block_hours' => 24,
    'orders_per_hour' => 5,

    // Bagian 11.1 T3
    'second_approval_threshold' => 5_000_000,

    // Bagian 6
    'service_fee' => 0,
    'packaging_weight_gram' => 100,

    // Bagian 4.3
    'stock_inbound_max' => 1000,
    'stock_inbound_avg_multiplier' => 10,
    'stock_max' => 999_999,
    'low_stock_default' => 5,

    // Bagian 4.2
    'similarity' => [
        'block' => 0.85,
        'suggest' => 0.65,
        'stopwords' => ['khas', 'asli', 'original', 'premium', 'bone'],
    ],

    // Bagian 7
    'recommendation' => [
        'half_life_days' => 7,
        'window_days' => 30,
        'popular_categories' => 8,
        'explore_categories' => 4,
        'related_limit' => 12,
        'weights' => ['category' => 3, 'tag' => 2, 'producer' => 1, 'sold' => 0.5],
    ],

    'shipping' => [
        'rajaongkir_key' => env('RAJAONGKIR_API_KEY'),
        'rajaongkir_url' => env('RAJAONGKIR_URL', 'https://rajaongkir.komerce.id/api/v1'),
        'couriers' => ['jne', 'jnt', 'sicepat', 'pos'],
        'cache_hours' => 12,
    ],

    'pickup_max_attempts' => 5,
    'pickup_lock_minutes' => 15,
];
