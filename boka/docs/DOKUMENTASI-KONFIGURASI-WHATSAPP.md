# Dokumentasi Konfigurasi Nomor WhatsApp Pemesanan

Dokumen ini mencatat langkah konfigurasi dan implementasi integrasi nomor kontak WhatsApp resmi untuk pemesanan barang dan komunikasi admin pada platform Tejaseku (Boka).

---

## 1. Ringkasan Pekerjaan

Sesuai dengan instruksi, nomor kontak pemesanan dan admin via WhatsApp telah diarahkan ke nomor:
**`088246700573`** (format internasional: `+62 882-4670-0573` / `6288246700573`).

Konfigurasi telah ditambahkan ke berkas environment aplikasi:
- `.env`
- `.env.example`
- `config/boka.php` (nilai default / fallback)

---

## 2. Rincian Konfigurasi Environment

### Berkas `.env` dan `.env.example`
Menambahkan variabel `BOKA_WHATSAPP` ke dalam berkas konfigurasi environment:

```dotenv
# Konfigurasi Kontak WhatsApp Toko
BOKA_WHATSAPP=088246700573
```

### Berkas `config/boka.php`
Memastikan konfigurasi toko membaca variabel environment tersebut dengan fallback ke nomor yang sama:

```php
'store' => [
    'name' => env('APP_NAME', 'Tejaseku'),
    'long_name' => env('APP_LONG_NAME', 'Tejaseku'),
    'tagline' => 'Platform Digitalisasi Pemasaran UMKM Desa',
    'email' => env('BOKA_EMAIL', 'halo@boka.id'),
    'whatsapp' => env('BOKA_WHATSAPP', '088246700573'),
    'address' => 'Jl. Jenderal Sudirman, Watampone, Kabupaten Bone, Sulawesi Selatan',
],
```

---

## 3. Alur Penggunaan Nomor WhatsApp di Aplikasi

Nomor WhatsApp dari konfigurasi dibagikan secara otomatis ke frontend melalui middleware Inertia (`HandleInertiaRequests`), kemudian digunakan pada titik-titik interaksi berikut:

1. **Pemesanan Keranjang Belanja (`resources/js/pages/shop/cart.tsx`):**
   - Tombol **"Pesan Sekarang via WhatsApp"** membaca `store.whatsapp`.
   - Nomor dinormalisasi dari `088246700573` menjadi format internasional `6288246700573`.
   - Tautan menghasilkan URL `https://wa.me/6288246700573?text=...` yang memuat rincian daftar belanja, varian, jumlah, dan estimasi total biaya.

2. **Halaman Detail Produk (`resources/js/pages/shop/product.tsx`):**
   - Tombol **"Chat Admin"** membuka WhatsApp langsung ke nomor `6288246700573` dengan pesan tanya stok atau pemesanan spesifik produk.

3. **Tata Letak Toko & Footer (`resources/js/layouts/shop-layout.tsx`):**
   - Tombol bantuan mengambang (*floating WhatsApp button*) di pojok kanan bawah.
   - Informasi kontak pada footer toko menampilkan `WhatsApp 088246700573`.

---

## 4. Pengujian & Verifikasi

1. **Pemeriksaan Nilai Konfigurasi:**
   Mengeksekusi pembacaan konfigurasi melalui Laravel:
   ```bash
   php -r "echo config('boka.store.whatsapp');"
   ```
   *Output:* `088246700573`

2. **Kompilasi Aset Frontend & SSR:**
   Menjalankan perintah pembaharuan bundel aplikasi:
   ```bash
   npm run build:ssr
   ```
   *Hasil:* Sukses dibangun (*built in 2.08s*) tanpa kendala error.

---

## 5. Kesimpulan
Integrasi nomor WhatsApp resmi `088246700573` telah aktif di seluruh halaman toko baik untuk pemesanan langsung dari keranjang maupun konsultasi produk.

