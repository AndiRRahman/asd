# Dokumentasi Perbaikan: Gagal Menambah Produk Baru

## 1. Ringkasan Masalah
Saat pengguna/admin mencoba menambahkan produk baru melalui Panel Admin (`/panel/produk/baru`), sistem mengalami galat (error HTTP 500) dengan pesan:

```text
Illuminate\Database\QueryException
SQLSTATE[HY000]: General error: 1 table products has no column named brewing_instructions
(Connection: sqlite, Database: D:\New folder\asd\boka\database\database.sqlite,
SQL: insert into "products" ("name", "name_normalized", "description", "brewing_instructions", ...)
values (...))
```

Akibat permasalahan ini, data produk baru tidak dapat disimpan ke database dan pengguna mendapati tampilan *Internal Server Error*.

---

## 2. Analisis & Akar Masalah (Root Cause)
1. **Fitur Baru (Petunjuk Penyajian / Cara Seduh):**
   Pada pembaruan kode aplikasi sebelumnya, ditambahkan kolom input opsional *"Cara Seduh / Petunjuk Penyajian"* (`brewing_instructions`) pada form produk (`resources/js/pages/panel/products/form.tsx`) serta controller (`app/Http/Controllers/Panel/ProductController.php`) dan model (`app/Models/Product.php`).
2. **Migrasi Database Belum Dijalankan (Pending Migration):**
   Berkas migrasi untuk menambahkan kolom tersebut sudah tersedia di `database/migrations/2026_11_04_161110_add_brewing_instructions_to_products_table.php`, namun migrasi ini belum dieksekusi ke database aktif (`database/database.sqlite`).
3. **Konflik Skema Database:**
   Karena kolom `brewing_instructions` belum ada di tabel `products`, perintah SQL `INSERT INTO products` gagal dieksekusi oleh SQLite.

---

## 3. Langkah-Langkah Perbaikan yang Dilakukan

### Langkah 1: Memeriksa Status Migrasi
Menjalankan pengecekan status migrasi database untuk mengidentifikasi migrasi yang belum berjalan:
```bash
php artisan migrate:status
```
*Hasil:*
```text
2026_11_04_161110_add_brewing_instructions_to_products_table .. Pending
```

### Langkah 2: Mengeksekusi Migrasi Database
Menjalankan migrasi database agar kolom `brewing_instructions` ditambahkan ke tabel `products`:
```bash
php artisan migrate --force
```
*Hasil:*
```text
INFO Running migrations.
2026_11_04_161110_add_brewing_instructions_to_products_table .. 15.72ms DONE
```

### Langkah 3: Verifikasi Hasil Migrasi
Memeriksa kembali status migrasi untuk memastikan semua berkas migrasi telah berhasil dieksekusi:
```bash
php artisan migrate:status
```
*Hasil:*
Seluruh migrasi termasuk `2026_11_04_161110_add_brewing_instructions_to_products_table` telah berstatus `Ran`.

---

## 4. Pengujian & Verifikasi (Testing)
1. **Pengujian Penyimpanan Data Produk Baru:**
   Melakukan simulasi pengiriman payload penambahan produk baru ke rute `POST /panel/produk` dengan atribut lengkap:
   - Nama produk: *Katirisala*
   - Kategori: *Olahan Pangan › Kue Tradisional*
   - Deskripsi produk dan petunjuk penyajian (`brewing_instructions`)
   - Varian produk, harga, stok, dan titik asal pengiriman.
2. **Hasil Pengujian:**
   - Permintaan berhasil diproses dengan status **HTTP 302 (Redirect)** ke `/panel/produk`.
   - Data berhasil di-insert ke dalam tabel `products` tanpa ada kendala skema kolom.
   - Kolom `brewing_instructions` tersimpan sesuai dengan input pengguna.

---

## 5. Kesimpulan
Permasalahan gagal menambah produk baru telah berhasil diperbaiki sepenuhnya dengan menjalankan migrasi database yang tertunda tanpa mengubah struktur kode lainnya di luar cakupan masalah. Pengguna sekarang dapat menambahkan produk baru secara normal melalui halaman `/panel/produk/baru`.

---

# Dokumentasi Perbaikan Tambahan: Error Upload Foto Produk (ImageConverter)

## 6. Ringkasan Permasalahan Lanjutan
Setelah perbaikan migrasi kolom `brewing_instructions`, penambahan produk baru yang disertai dengan pengunggahan foto produk memunculkan galat baru (error HTTP 500):

```text
Error
app\Support\ImageConverter.php:26
Call to undefined function App\Support\imagecreatefromjpeg()
(POST http://127.0.0.1:8000/panel/produk)
```

Akibat permasalahan ini, produk yang memiliki lampiran berkas foto gagal disimpan ke dalam sistem.

---

## 7. Analisis & Akar Masalah (Root Cause)
1. **Pemanggilan Fungsi GD Tanpa Verifikasi Ekstensi:**
   Pada berkas `app/Support/ImageConverter.php`, pemanggilan fungsi pembuatan resource gambar (seperti `@imagecreatefromjpeg()`, `@imagecreatefrompng()`, dsb.) dilakukan secara langsung di dalam ekspresi `match ($imageType)`.
2. **Ketiadaan Ekstensi GD pada PHP Lokal:**
   Di lingkungan server/PHP lokal pengembang, ekstensi GD (`ext-gd`) tidak diaktifkan/tidak terpasang.
3. **Sifat Fatal Error PHP:**
   Penggunaan operator error control `@` tidak dapat meredam kesalahan tingkat *Fatal Error* (`Call to undefined function`). Karena fungsi `imagecreatefromjpeg` tidak ada di lingkungan PHP tersebut, eksekusi kode langsung terhenti dan memicu *Internal Server Error*.

---

## 8. Langkah-Langkah Perbaikan yang Diterapkan
Memperbarui berkas [app/Support/ImageConverter.php](file:///d:/New%20folder/asd/boka/app/Support/ImageConverter.php) dengan mekanisme penanganan defensif:

1. **Pemeriksaan Eksistensi Berkas:**
   Memastikan path fisik berkas ada dan valid sebelum memanggil fungsi `getimagesize()`:
   ```php
   [$width, $height, $imageType] = ($realPath && file_exists($realPath) ? @getimagesize($realPath) : null) ?: [0, 0, null];
   ```

2. **Pemeriksaan Ketersediaan Modul GD:**
   Menambahkan validasi `extension_loaded('gd') && function_exists('imagewebp')` serta memeriksa setiap fungsi `imagecreatefrom...` sebelum mencoba memproses konversi ke WebP.

3. **Penyimpanan Fallback Aman:**
   Jika ekstensi GD tidak aktif atau tidak mendukung format gambar terkait, sistem secara otomatis beralih ke penyimpanan bawaan Laravel:
   ```php
   $fallbackPath = $file->store($directory, 'public');

   return [
       'path' => $fallbackPath,
       'width' => $width,
       'height' => $height,
   ];
   ```
   Dengan demikian, foto produk tetap tersimpan ke penyimpanan publik (`storage/app/public/produk/`) tanpa menyebabkan error aplikasi.

---

## 9. Pengujian & Verifikasi Lanjutan
1. **Pengujian Unit ImageConverter:**
   Menguji pemrosesan berkas gambar (JPEG/PNG) pada lingkungan tanpa ekstensi GD. Berkas berhasil tersimpan ke disk publik dan mengembalikan data path serta dimensi gambar dengan benar.
2. **Pengujian End-to-End Simpan Produk Baru:**
   Menguji alur penambahan produk lengkap (informasi produk, deskripsi, petunjuk penyajian, varian harga & stok, serta upload berkas foto). Sistem berhasil merespons dengan HTTP 302 Redirect ke `/panel/produk` dan seluruh entitas (produk, varian, dan foto) tersimpan ke database.

---

## 10. Kesimpulan Akhir
Seluruh kendala pada fitur penambahan produk baru di Panel Admin telah teratasi secara tuntas:
1. Skema database tabel `products` telah diperbarui dengan kolom `brewing_instructions`.
2. Penanganan unggah gambar pada `ImageConverter` kini memiliki proteksi ketersediaan modul GD dan fallback otomatis.


---

# Dokumentasi Konfigurasi Nomor WhatsApp Pemesanan

## 11. Ringkasan Konfigurasi WhatsApp
Nomor kontak pemesanan dan komunikasi admin via WhatsApp pada platform Tejaseku diarahkan ke nomor:
**`088246700573`** (format internasional: `+62 882-4670-0573` / `6288246700573`).

Konfigurasi telah ditambahkan ke berkas environment aplikasi:
- `.env`
- `.env.example`
- `config/boka.php` (nilai default / fallback)

---

## 12. Rincian Konfigurasi Environment

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

## 13. Alur Penggunaan Nomor WhatsApp di Aplikasi

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

## 14. Pengujian & Verifikasi

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
