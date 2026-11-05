# e-TEJASEKU

Platform "e-TEJASEKU" — platform digitalisasi pemasaran UMKM desa berupa website/marketplace sederhana disertai katalog produk interaktif berbasis digitalisasi yang menampilkan informasi produk, cara seduh, dan testimoni saat kemasan produk dipindai (scan) menggunakan kamera ponsel.

## Ringkasan Proyek & Spesifikasi

- **a. Nama dan gambaran umum:** Platform "e-TEJASEKU" — platform digitalisasi pemasaran UMKM desa berupa website/marketplace sederhana disertai katalog produk interaktif berbasis digitalisasi yang menampilkan informasi produk, cara seduh, dan testimoni saat kemasan produk dipindai (scan) menggunakan kamera ponsel.
- **b. Dasar penelitian:** mengacu pada kajian penerapan aplikasi platform digital pembelajaran berbasis android serta program komputer dalam pemasaran produk UMKM berbasis kearifan lokal yang telah diuji fungsinya oleh tim pengembang, serta telah melalui uji coba terbatas sehingga siap diimplementasikan pada karang taruna sebagai admin/pengelola.
- **c. Kepemilikan KI:** hak kekayaan intelektual Hak Cipta platform dan program komputer diajukan atas nama Syahrul Mubarak Universitas Muslim Indonesia pada tahun 2021 dan 2022 dengan nomor pencatatan ciptaan EC00202166007 dan EC00202223770.
- **d. Spesifikasi:** platform berbasis web responsif (dapat diakses melalui smartphone), fitur meliputi katalog produk, keranjang pemesanan sederhana, dan dashboard admin untuk karang taruna.
- **e. Fungsi dan cara kerja:** konsumen memindai marker melalui kamera ponsel untuk menampilkan informasi produk secara interaktif (3D/animasi), sekaligus tautan pemesanan pada platform e-TEJASEKU.
- **f. Kesesuaian dengan mitra:** menjawab kebutuhan karang taruna yang melek teknologi namun belum terarah pemanfaatannya, serta menjawab kebutuhan Mitra dalam membutuhkan kanal pemasaran digital dengan nilai tambah interaktif.
- **g. Metode transfer:** pelatihan bertahap (dasar, lanjutan, dan praktik pendampingan UMKM) serta pendampingan pengelolaan hingga karang taruna mampu menjadi fasilitator/coach digital lokal (train the trainer).
- **h. Keterikatan dengan level keberdayaan:** karang taruna bertransformasi dari sekedar pengguna media sosial pribadi menjadi mampu memfasilitasi digitalisasi pemasaran UMKM desa secara mandiri dan berkelanjutan.

Stack: Laravel 13, Inertia 3, React 19 + TypeScript dengan SSR, Tailwind 4.

## Cara tercepat (Windows)

Klik dua kali **`mulai.bat`**. Script ini memeriksa PHP, Composer, dan Node; memasang paket jika belum ada;
membuat `.env`; menyalakan MySQL XAMPP jika mati; membuat database, tabel, dan data contoh; membangun
tampilan jika ada perubahan; lalu menjalankan web, SSR, antrean, dan penjadwal sekaligus dan membuka browser.
Aman dijalankan berulang kali; data yang sudah ada tidak dihapus. Tekan Ctrl+C untuk berhenti.

- `mulai.bat -Reset` menghapus semua data lalu mengisi ulang data contoh (ada konfirmasi).
- `mulai.bat -Port 8080` memakai port lain.

Klik dua kali **`uji.bat`** untuk menjalankan semua tes otomatis. Tes memakai SQLite di memori,
jadi tidak menyentuh data MySQL.

## Menjalankan di komputer lokal (manual)

Prasyarat: PHP 8.4 (ekstensi pdo_mysql, gd, mbstring, fileinfo), Composer 2, Node 22+, MySQL 8.

```bash
composer install
npm install
cp .env.example .env        # lalu isi bagian database di bawah
php artisan key:generate
php artisan migrate --seed  # peran, izin, dan data contoh Kabupaten Bone
php artisan storage:link
npm run build:ssr
```

Database. Contoh `.env` untuk MySQL 8.0 di port 3307:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=boka
DB_USERNAME=root
DB_PASSWORD=isi-password-mysql
```

Buat database `boka` dulu (`CREATE DATABASE boka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`).
Tanpa konfigurasi ini, `.env` bawaan memakai SQLite (`database/database.sqlite`).

Jalankan empat proses (masing-masing di terminal sendiri):

```bash
php artisan serve                 # http://localhost:8000
php artisan inertia:start-ssr     # server SSR untuk meta Open Graph dan SEO
php artisan queue:work            # email dan notifikasi
php artisan schedule:work         # kedaluwarsa pesanan tiap menit, pengingat bayar, selesai otomatis
```

Saat mengembangkan tampilan, ganti `npm run build:ssr` dengan `npm run dev`.

## Akun contoh (hanya untuk lokal dan staging)

Semua password: `password`.

| Peran | Email |
| --- | --- |
| Super Admin | super@boka.test, super2@boka.test |
| Admin Transaksi | transaksi@boka.test |
| Admin | admin@boka.test |
| Pembeli | pembeli@boka.test |

Setelah login, staf (Admin, Admin Transaksi, Super Admin) diarahkan ke `/panel`, sedangkan Pembeli ke
beranda atau ke halaman yang tadinya dituju (misalnya checkout).

2FA sedang dimatikan atas keputusan klien (pengguna belum familiar). Saklarnya `BOKA_TWO_FACTOR` di `.env`:
`false` menyembunyikan menu 2FA, tidak mewajibkannya di panel, dan tidak meminta kode saat login maupun saat
mengubah rekening. Ubah ke `true` untuk mengaktifkannya kembali (disarankan sebelum produksi).

Super Admin di produksi dibuat lewat server: `php artisan boka:super-admin email@domain "Nama"`.

## Peta spesifikasi ke kode

| Bagian spesifikasi | Lokasi |
| --- | --- |
| 2 Peran dan izin | `database/seeders/RoleSeeder.php`, `Gate::before` di `AppServiceProvider` |
| 3 Toko dan Pembeli | `app/Http/Controllers/Shop`, `resources/js/pages/shop` |
| 4 Panel Admin | `app/Http/Controllers/Panel`, `app/Panel` (modul CRUD), `resources/js/pages/panel` |
| 4.1 Status dan syarat tayang | `app/Domain/Catalog/ProductPublisher.php` |
| 4.2 Produk serupa | `app/Domain/Catalog/SimilarityChecker.php` |
| 4.3 Mutasi stok | `app/Domain/Catalog/StockService.php` |
| 4.4 Validasi jumlah beli | `app/Domain/Cart/CartService.php`, `PriceCalculator`, `CreateOrder` |
| 5 State machine pesanan | `app/Domain/Order` |
| 6 Mesin harga | `app/Domain/Checkout/PriceCalculator.php`, `app/Domain/Promotion` |
| 6 Ongkir dan paket | `app/Domain/Shipping` |
| 7 Pencarian dan rekomendasi | `app/Domain/Search`, `app/Domain/Recommendation` |
| 9 Database | `database/migrations/2026_11_03_*` |
| 10 API v1 | `routes/api.php`, `app/Http/Controllers/Api/V1` |
| 11 Keamanan pembayaran | `app/Domain/Payment` |
| 12 Desain | `resources/css/app.css` (token warna), `resources/js/components/shop` |
| 12.7 Bahasa | `lang/id.json`, `lang/en.json`, `lang/id/*.php` |
| 14 Server MCP | `app/Mcp`, `routes/ai.php`, `tests/Mcp/evaluation.xml` |
| 15 Pengujian | `tests/Boka` |

Controller hanya memvalidasi, memanggil satu layanan di `app/Domain`, lalu mengembalikan respons.
Web, API v1, dan MCP memanggil layanan yang sama. Bentuk data untuk React dan API ada di `app/Support/Present.php`.

## Keamanan

- **CSRF:** setiap form dan permintaan tulis di web wajib membawa token; tanpa token ditolak (HTTP 419).
- **HashID:** ID di URL dan data yang dikirim ke browser/API berupa kode acak (misalnya `/panel/produk/Xk3a9pQz2L/edit`),
  bukan angka berurutan, sehingga tidak bisa ditebak atau dihitung. Setiap akses tetap diperiksa kepemilikan dan izinnya.
- **Content-Security-Policy:** browser hanya menjalankan script dari situs ini atau yang membawa nonce acak per permintaan.
- **Header HTTP:** `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`,
  `Cross-Origin-Opener-Policy`; HSTS di produksi; `X-Powered-By` dihapus.
- **Akun:** akun yang dinonaktifkan Admin tidak bisa login dan sesinya langsung diakhiri; data sesi dienkripsi;
  login dibatasi 5 percobaan per menit; password produksi minimal 12 karakter dan dicek ke daftar password bocor.
- **Anti-bot dan spam:** honeypot di registrasi, batas 60 aksi tulis per menit per pengguna/IP, batas laju API.
- **Lainnya:** query database selalu memakai parameter terikat; isi berita disaring HTMLPurifier; bukti transfer
  disimpan privat, di-encode ulang, dan dibuka lewat URL bertanda tangan 10 menit; log audit untuk aksi Admin.

## Pengujian

```bash
php artisan test
```

92+ tes. Kriteria penerimaan yang sudah punya tes otomatis di `tests/Boka`:
K1, K2 (versi berurutan), K3, K4, K5, K6, K7, K8, K9, K11, K13 (data), K14, K15, K16, K17, K18,
K19, K20, K21, K22, K23, K24, K25, K26, K27, K28, K29, K30, K31.

Belum diuji otomatis: K2 dengan 50 proses paralel sungguhan (butuh MySQL dan proses paralel),
K10 dan K12 (butuh staging dan Lighthouse), K32 (ekspor antrean belum dibuat), K33 (konversi WebP belum dibuat).

Anggaran JavaScript awal halaman toko (gzip, diukur dari build): beranda 192 KB, produk 192 KB,
katalog 191 KB, keranjang 189 KB, checkout 197 KB. Target spesifikasi di bawah 200 KB.

## Server MCP untuk Admin

Endpoint `POST /mcp/toko` dengan token Sanctum milik akun Admin. Tool: `toko_list_pending_verifications`,
`toko_get_order`, `toko_search_orders`, `toko_sales_summary`, `toko_low_stock`, `toko_search_insights`,
`toko_set_tracking_number`. Tool memakai izin yang sama dengan panel, dan setiap panggilan tercatat di
log audit. Menyetujui pembayaran, membatalkan pesanan, mengubah harga, dan mengelola akun sengaja tidak tersedia.

Uji manual: `php artisan mcp:inspector mcp/toko`. Pertanyaan evaluasi ada di `tests/Mcp/evaluation.xml`;
jawabannya berlaku untuk data seed dalam 30 hari setelah seeding.

## Yang belum dikerjakan di fase ini

Daftar ini perlu diselesaikan sebelum peluncuran atau diputuskan bersama klien:

- **Konversi gambar ke WebP (12.8).** Foto tersimpan apa adanya, belum ada `srcset` per lebar dan belum
  ada gambar Open Graph JPEG 1200 x 630. Produk contoh memakai ilustrasi SVG sementara; WhatsApp tidak
  menampilkan pratinjau SVG, jadi produk nyata butuh foto JPG/PNG.
- **Ekspor.** Baru CSV langsung (maksimal 5.000 baris). Belum ada Excel, PDF laporan per produsen,
  ekspor besar lewat antrean, dan menu "Ekspor saya".
- **Impor CSV produk** beserta pratinjau produk serupa.
- **Keamanan akun Pembeli (T9, T11):** email login dari perangkat baru, konfirmasi ganti email ke dua alamat,
  Cloudflare Turnstile.
- **Email ringkasan stok menipis harian.**
- **Infrastruktur produksi:** Redis, Horizon, Meilisearch, header CSP di Nginx, backup. Secara lokal aplikasi
  memakai antrean, cache, dan pencarian berbasis database; Scout tinggal diarahkan ke Meilisearch lewat
  `SCOUT_DRIVER=meilisearch`.
- **RajaOngkir.** Klien sudah ada; isi `RAJAONGKIR_API_KEY` dan `destination_id` tiap titik asal.
  Tanpa key, checkout menawarkan kurir lokal dan ambil sendiri, dan daftar tujuan memakai 27 kecamatan Bone.
- **Teks antarmuka.** Toko, login, registrasi, dan verifikasi email sudah dwibahasa. Halaman lupa/reset
  password, tantangan 2FA, dan pengaturan akun masih memakai teks bawaan starter kit berbahasa Inggris.
  Panel Admin sengaja hanya berbahasa Indonesia.
- **Animasi.** Animasi memakai CSS dan IntersectionObserver, bukan Motion, GSAP, dan Lottie, agar halaman
  toko tetap di bawah anggaran 200 KB.

Pertanyaan terbuka untuk klien tetap seperti di bagian 16 spesifikasi. Nilai usulan (batas bayar 24 jam,
kode unik, ambang persetujuan kedua Rp 5.000.000, aturan anti-penimbunan) sudah terpasang dan bisa diubah
Super Admin di Panel, Pengaturan.
