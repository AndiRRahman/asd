# Dokumentasi Instalasi dan Implementasi Boka (Bone Karya)

Toko daring Karang Taruna dan UMKM Kabupaten Bone

| Keterangan | Isi |
| --- | --- |
| Versi dokumen | 1.0 |
| Tanggal | 4 November 2026 |
| Versi aplikasi | Fase 1 (MVP toko inti) |
| Dasar acuan | Spesifikasi E-Commerce Karang Taruna & UMKM Kabupaten Bone (2 November 2026) |
| Pembaca sasaran | Developer, DevOps, dan tim teknis yang memasang, mengembangkan, atau merawat Boka |

---

## 1. Pendahuluan

### 1.1 Tentang Boka

Boka (Bone Karya) adalah toko daring resmi untuk produk Karang Taruna dan UMKM Kabupaten Bone, Sulawesi Selatan. Pada fase 1, Boka berjalan sebagai **toko tunggal**: Admin menjadi satu-satunya penjual dan mengelola seluruh transaksi, sementara setiap produk tetap mencantumkan produsen asalnya (Karang Taruna desa atau UMKM) beserta desa dan kecamatannya.

Arsitektur Boka disiapkan sejak awal untuk dua arah pengembangan: aplikasi mobile (lewat API v1 yang sudah tersedia) dan marketplace multi-penjual (profil produsen bisa dinaikkan menjadi akun penjual tanpa migrasi data besar).

### 1.2 Ruang lingkup dokumen

Dokumen ini memandu seluruh siklus teknis Boka:

1. Menyiapkan komputer pengembang dan memasang aplikasi di lokal (cara cepat dan cara manual).
2. Memahami konfigurasi, struktur kode, peran pengguna, dan alur bisnis.
3. Mengisi data awal toko sampai siap menerima pesanan.
4. Mengembangkan fitur, menjalankan tes, dan memakai API mobile serta server MCP.
5. Memasang aplikasi di VPS produksi, merilis pembaruan, mencadangkan data, dan memantau sistem.
6. Daftar periksa sebelum peluncuran dan pekerjaan lanjutan.

### 1.3 Ringkasan teknologi

| Lapisan | Teknologi |
| --- | --- |
| Backend | Laravel 13 (PHP 8.3+, disarankan 8.4) |
| Frontend | Inertia.js 3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui, Lucide |
| Rendering | Server-Side Rendering (SSR) Inertia di Node.js, untuk SEO dan pratinjau tautan WhatsApp |
| Build tool | Vite+ (`vp`), Laravel Wayfinder (rute bertipe untuk TypeScript) |
| Autentikasi | Laravel Fortify (login, verifikasi email, 2FA, passkey), Laravel Sanctum (token API mobile) |
| Otorisasi | spatie/laravel-permission (peran dan izin disimpan sebagai data) |
| Audit | spatie/laravel-activitylog |
| Multibahasa | spatie/laravel-translatable (kolom JSON per bahasa), file `lang/id` dan `lang/en` |
| Pencarian | Laravel Scout (driver `database` di lokal, siap diarahkan ke Meilisearch) |
| Keamanan konten | mews/purifier (HTMLPurifier), hashids/hashids (ID acak di URL) |
| Asisten Admin | Laravel MCP (server MCP `/mcp/toko`) |
| Database | MySQL 8 / MariaDB (XAMPP) di lokal, MySQL 8.4 LTS di produksi; SQLite untuk tes |
| Pengujian | Pest 4, PHPUnit 12, Larastan (PHPStan), Laravel Pint |

---

## 2. Gambaran Sistem

### 2.1 Arsitektur

Boka adalah **monolit modular**: satu aplikasi Laravel dengan tiga pintu masuk yang memanggil lapisan aturan bisnis yang sama.

| Pintu masuk | Rute | Keluaran |
| --- | --- | --- |
| Web (toko dan panel) | `routes/web.php`, `routes/panel.php`, `routes/settings.php` | Halaman Inertia (React + SSR) |
| API v1 mobile | `routes/api.php` (prefiks `/api/v1`) | JSON, token Sanctum |
| Server MCP Admin | `routes/ai.php` (`/mcp/toko`) | Tool MCP untuk asisten AI |

Aturan arsitektur yang wajib dijaga:

- Controller hanya memvalidasi masukan, memanggil satu layanan di `app/Domain`, lalu mengembalikan respons.
- Logika bisnis tidak boleh berada di controller atau komponen React.
- Bentuk data yang dikirim ke React dan API disusun di `app/Support/Present.php`, sehingga web dan API konsisten.
- Total harga selalu dihitung ulang di server oleh `PriceCalculator`. Angka dari browser atau aplikasi tidak pernah dipakai sebagai harga.

Proses yang berjalan bersamaan saat aplikasi aktif:

| Proses | Perintah | Fungsi |
| --- | --- | --- |
| Web | `php artisan serve` (lokal) atau PHP-FPM + Nginx (produksi) | Melayani permintaan HTTP |
| SSR | `php artisan inertia:start-ssr` | Merender React di server (port 13714) |
| Antrean | `php artisan queue:work` | Email dan notifikasi di latar belakang |
| Penjadwal | `php artisan schedule:work` (lokal) atau cron `schedule:run` (produksi) | Kedaluwarsa pesanan, pengingat bayar, selesai otomatis, pembersihan log |

### 2.2 Struktur folder

```
boka/
  app/
    Domain/            Aturan bisnis per domain
      Cart/            CartService (validasi jumlah beli terhadap stok)
      Catalog/         ProductPublisher, SimilarityChecker, StockService, ProductStatus
      Checkout/        PriceCalculator, CreateOrder
      Identity/        AnonymizeUser (hapus akun sesuai UU PDP)
      Order/           OrderService, OrderStateMachine, OrderStatus
      Payment/         PaymentService, RefundService
      Promotion/       PriceResolver, VoucherService
      Recommendation/  RecommendationService
      Search/          SearchService
      Shipping/        RajaOngkirClient, ShippingQuoter, BoneDistricts
    Http/Controllers/
      Shop/            Halaman toko dan akun Pembeli
      Panel/           Panel Admin
      Api/V1/          API mobile
      Settings/        Profil dan keamanan akun
    Http/Middleware/   SecurityHeaders, SetLocale, EnsurePanelAccess, EnsureAccountActive, dll.
    Mcp/               Server MCP dan tool-nya
    Models/            Model Eloquent
    Notifications/     Email dan notifikasi dalam situs
    Panel/             Modul CRUD generik panel (kategori, produsen, voucher, banner, dll.)
    Support/           HashId, Present, Qr, Reports, ProductArt
  config/boka.php      Nilai bawaan aturan bisnis
  database/
    migrations/        Skema tabel
    seeders/           RoleSeeder (peran dan izin), BokaSeeder (data contoh)
  lang/                Teks antarmuka id dan en
  resources/
    css/app.css        Token warna dan tipografi
    js/pages/shop      Halaman toko
    js/pages/panel     Halaman panel Admin
    js/components      Komponen bersama (shop, panel, ui)
    views/             Template Blade (invoice, label, email)
  routes/              web, panel, api, ai, console, settings
  scripts/             mulai.ps1, uji.ps1, siapkan-db.php
  tests/Boka/          Tes kriteria penerimaan
  mulai.bat            Persiapan dan menjalankan aplikasi (Windows)
  uji.bat              Menjalankan semua tes (Windows)
```

### 2.3 Peran dan hak akses

Hak akses disimpan sebagai data (spatie/laravel-permission). Peran baru seperti Staf Gudang atau Penjual bisa ditambah nanti tanpa mengubah kode otorisasi.

| Peran | Kode peran | Cara mendapat akun | Kewenangan |
| --- | --- | --- | --- |
| Tamu | (tanpa akun) | Tidak perlu | Melihat katalog, mencari, membaca konten, keranjang lokal |
| Pembeli | `pembeli` | Daftar sendiri, aktif setelah verifikasi email | Keranjang, wishlist, checkout, upload bukti transfer, ulasan |
| Admin | `admin` | Undangan email dari Super Admin | Produk, stok, pesanan, promosi, konten, pelanggan, laporan |
| Admin Transaksi | `admin_transaksi` | Undangan email dari Super Admin | Semua kewenangan Admin + verifikasi pembayaran, refund, laporan keuangan, ekspor data pribadi |
| Super Admin | `super_admin` | Perintah server `boka:super-admin` | Semua kewenangan + akun Admin, pengaturan, rekening, log audit, sistem |

Daftar izin (permission) yang dipakai kode:

| Kelompok | Izin |
| --- | --- |
| Admin | `panel.akses`, `produk.kelola`, `stok.kelola`, `pesanan.kelola`, `promosi.kelola`, `konten.kelola`, `pelanggan.kelola`, `laporan.lihat` |
| Transaksi | `pembayaran.verifikasi`, `refund.proses`, `laporan.keuangan`, `ekspor.data_pribadi` |
| Super Admin | `admin.kelola`, `pengaturan.kelola`, `audit.lihat_semua` |

Super Admin melewati semua pemeriksaan izin lewat `Gate::before` di `AppServiceProvider`.

### 2.4 Fitur fase 1

| Area | Fitur |
| --- | --- |
| Toko publik | Beranda dengan banner, kategori populer, "Jelajahi juga", flash sale, terlaris, terbaru, cerita produsen, kabar kegiatan; katalog dengan filter; detail produk dengan varian; halaman produsen; halaman rekening resmi |
| Pencarian | Saran instan per ketikan, log pencarian, sinonim lokal, kata kunci tanpa hasil |
| Rekomendasi | Empat blok berbasis aturan (tanpa AI): kategori sering dicari, jelajahi juga, terkait pencarian Anda, permata tersembunyi |
| Akun Pembeli | Registrasi, verifikasi email, buku alamat (27 kecamatan Bone), wishlist, unduh data pribadi, hapus akun |
| Belanja | Keranjang Tamu di cookie lalu digabung saat login, checkout per paket (per titik asal), voucher belanja dan voucher ongkir, kode unik transfer |
| Pembayaran | Transfer bank, upload bukti, verifikasi oleh Admin Transaksi dengan daftar periksa mutasi rekening, persetujuan kedua untuk nominal besar, deteksi bukti ganda |
| Pengiriman | Kurir ekspedisi (RajaOngkir), kurir lokal per kecamatan, ambil sendiri dengan kode ambil 6 digit dan QR |
| Panel Admin | Dasbor antrean kerja, pembayaran, pesanan, produk (status tayang, deteksi produk serupa, gabung produk), stok dan stok opname, pelanggan, ulasan, laporan CSV, modul CRUD, pengguna panel, pengaturan, log audit, sistem |
| Multibahasa dan tema | Bahasa Indonesia (bawaan) dan Inggris di prefiks `/en`; mode terang (bawaan) dan gelap tanpa kedipan |
| Dokumen | Invoice PDF dengan QR verifikasi, label alamat |
| Integrasi | API v1 mobile, server MCP untuk Admin |

---

## 3. Prasyarat

### 3.1 Perangkat lunak

| Perangkat lunak | Versi minimum | Disarankan | Keterangan |
| --- | --- | --- | --- |
| PHP | 8.3 | 8.4 | Di Windows bisa memakai PHP bawaan XAMPP (`C:\xampp\php`) |
| Composer | 2.x | versi terbaru | Pengelola paket PHP |
| Node.js | 20.19 | 22 LTS | Untuk build frontend dan server SSR |
| npm | ikut Node.js | | |
| MySQL / MariaDB | MySQL 8.0 / MariaDB 10.6 | MySQL 8.4 LTS | XAMPP sudah menyertakan MariaDB; tanpa MySQL, aplikasi bisa memakai SQLite |
| Git | 2.x | | Mengambil dan merilis kode |
| Browser | Chrome, Edge, Firefox, Safari terbaru | | |

### 3.2 Ekstensi PHP yang wajib aktif

`pdo_mysql` (atau `pdo_sqlite`), `gd`, `mbstring`, `fileinfo`, `openssl`, `curl`, `zip`, `xml`, `bcmath`, `intl`.

Di XAMPP, buka `C:\xampp\php\php.ini`, cari baris berikut, lalu hapus tanda titik koma (`;`) di depannya:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

### 3.3 Memeriksa versi

```bash
php -v
php -m          # daftar ekstensi yang aktif
composer -V
node -v
npm -v
```

---

## 4. Instalasi Lokal Cara Cepat (Windows + XAMPP)

Cara ini paling mudah untuk mencoba Boka di komputer Windows. Seluruh langkah persiapan dikerjakan oleh satu script.

### 4.1 Langkah

1. Pasang XAMPP (berisi PHP dan MySQL/MariaDB), Composer, dan Node.js 22.
2. Salin folder project ke `C:\xampp\htdocs\Project_Bone\boka` (atau lokasi lain).
3. Klik dua kali **`mulai.bat`** di folder `boka`.
4. Tunggu sampai browser terbuka otomatis di `http://localhost:8000`.
5. Tekan `Ctrl+C` di jendela script untuk menghentikan semua proses.

### 4.2 Yang dikerjakan `mulai.bat`

Script `scripts/mulai.ps1` aman dijalankan berulang kali; langkah yang sudah beres dilewati dan data yang ada tidak dihapus.

| Urutan | Langkah | Rincian |
| --- | --- | --- |
| 1 | Memeriksa alat | PHP (menambahkan `C:\xampp\php` ke PATH bila perlu), Composer, Node, npm; menolak PHP < 8.3 dan Node < 20.19 |
| 2 | Paket PHP | `composer install` jika `vendor` belum ada atau `composer.lock` berubah |
| 3 | Paket JavaScript | `npm install` jika `node_modules` belum ada atau `package-lock.json` berubah |
| 4 | File `.env` | Menyalin `.env.example` dan membuat `APP_KEY` jika belum ada |
| 5 | Server database | Jika `DB_CONNECTION=mysql` di port 3306 dan MySQL XAMPP mati, script menyalakannya |
| 6 | Database | `scripts/siapkan-db.php` memeriksa ekstensi PHP dan membuat database `boka` (utf8mb4) bila belum ada |
| 7 | Migrasi dan data contoh | Database baru: `migrate --seed`; tabel ada tapi kosong: `migrate:fresh --seed`; sudah berisi: hanya `migrate` |
| 8 | Storage link | `php artisan storage:link` jika `public/storage` belum ada |
| 9 | Build tampilan | `npm run build:ssr` hanya jika kode di `resources/js` atau `resources/css` berubah |
| 10 | Pembersihan cache | `optimize:clear` dan `package:discover` |
| 11 | Menjalankan | Web, SSR, antrean, dan penjadwal sekaligus (lewat `concurrently`), lalu membuka browser |

Semua keluaran script juga dicatat di `storage/logs/mulai.log`, sehingga galat bisa dibaca setelah jendela ditutup.

### 4.3 Opsi

| Perintah | Fungsi |
| --- | --- |
| `mulai.bat` | Siapkan (jika perlu) lalu jalankan |
| `mulai.bat -Reset` | Hapus semua data lalu isi ulang data contoh (ada konfirmasi `y/n`) |
| `mulai.bat -Port 8080` | Pakai port lain; jika port terpakai, script mencari port berikutnya yang kosong |
| `uji.bat` | Jalankan semua tes otomatis (memakai SQLite di memori, data MySQL tidak tersentuh) |

---

## 5. Instalasi Lokal Cara Manual (semua sistem operasi)

Gunakan cara ini di macOS, Linux, atau jika ingin mengendalikan setiap langkah.

### 5.1 Ambil kode

```bash
git clone <url-repositori> boka
cd boka
```

### 5.2 Pasang dependensi

```bash
composer install
npm install
```

### 5.3 Siapkan file `.env`

```bash
cp .env.example .env
php artisan key:generate
```

> **Penting:** `APP_KEY` dipakai untuk mengenkripsi sesi dan file bukti transfer asli, sekaligus menjadi kunci samaran HashID di URL. Jangan mengganti `APP_KEY` setelah aplikasi berisi data sungguhan: tautan lama akan rusak dan file terenkripsi tidak bisa dibuka.

### 5.4 Siapkan database

**Pilihan A: MySQL / MariaDB (disarankan).** Buat database:

```sql
CREATE DATABASE boka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu ubah bagian database di `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306          # XAMPP biasanya 3306; MySQL 8 terpisah sering 3307
DB_DATABASE=boka
DB_USERNAME=root
DB_PASSWORD=          # isi jika MySQL memakai password
```

**Pilihan B: SQLite (paling sederhana).** Biarkan `DB_CONNECTION=sqlite`. File `database/database.sqlite` dipakai otomatis; buat file kosong jika belum ada.

### 5.5 Buat tabel dan isi data contoh

```bash
php artisan migrate --seed
```

Perintah ini membuat seluruh tabel, peran dan izin (`RoleSeeder`), serta data contoh Kabupaten Bone (`BokaSeeder`): 5 akun, 4 titik asal kirim beserta tarif kurir lokal ke 27 kecamatan, 6 kategori induk dengan sub-kategori, produsen, produk, 2 rekening bank, 2 voucher, flash sale, banner, kabar kegiatan, halaman statis, ulasan, dan riwayat pesanan.

### 5.6 Hubungkan folder penyimpanan publik

```bash
php artisan storage:link
```

### 5.7 Build tampilan

```bash
npm run build:ssr     # build untuk browser + bundle SSR
```

Saat sedang mengembangkan tampilan, gunakan `npm run dev` (hot reload) sebagai ganti build.

### 5.8 Jalankan empat proses

Buka empat terminal di folder `boka`:

```bash
php artisan serve                 # http://localhost:8000
php artisan inertia:start-ssr     # server SSR (meta Open Graph dan SEO)
php artisan queue:work            # email dan notifikasi
php artisan schedule:work         # kedaluwarsa pesanan, pengingat bayar, selesai otomatis
```

Alternatif satu perintah: `composer dev`.

### 5.9 Verifikasi instalasi

| Pemeriksaan | Hasil yang diharapkan |
| --- | --- |
| `http://localhost:8000/up` | Halaman status "Application up" |
| `http://localhost:8000` | Beranda Boka dengan produk contoh |
| Lihat sumber halaman produk (Ctrl+U) | Ada tag `og:title` dan `og:image` di HTML awal (SSR aktif) |
| Login `admin@boka.test` | Diarahkan ke `/panel` |
| `php artisan test` | Semua tes lolos |

---

## 6. Konfigurasi

### 6.1 Variabel `.env` penting

| Variabel | Nilai lokal | Nilai produksi | Fungsi |
| --- | --- | --- | --- |
| `APP_ENV` | `local` | `production` | Mengaktifkan aturan produksi (password kuat, HSTS) |
| `APP_DEBUG` | `true` | `false` | Wajib `false` di produksi |
| `APP_URL` | `http://localhost:8000` | `https://domain-boka` | Dipakai di email, sitemap, dan tautan bertanda tangan |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `id` | `id` | Bahasa bawaan |
| `DB_*` | lihat bagian 5.4 | kredensial MySQL produksi | Koneksi database |
| `SESSION_DRIVER` | `database` | `database` atau `redis` | Penyimpanan sesi |
| `SESSION_ENCRYPT` | `true` | `true` | Data sesi dienkripsi |
| `SESSION_SECURE_COOKIE` | (kosong) | `true` | Cookie hanya lewat HTTPS |
| `QUEUE_CONNECTION` | `database` | `database` atau `redis` | Antrean email dan notifikasi |
| `CACHE_STORE` | `database` | `database` atau `redis` | Cache |
| `MAIL_MAILER` | `log` | `smtp` | Di lokal, isi email tercatat di `storage/logs/laravel.log` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | | kredensial SMTP | Pengiriman email |
| `SCOUT_DRIVER` | `database` | `database` atau `meilisearch` | Mesin pencarian produk |
| `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | | jika memakai Meilisearch | |
| `RAJAONGKIR_API_KEY` | (kosong) | API key Komerce | Ongkir ekspedisi; tanpa key, checkout hanya menawarkan kurir lokal dan ambil sendiri |
| `RAJAONGKIR_URL` | bawaan | bawaan | `https://rajaongkir.komerce.id/api/v1` |
| `BOKA_TWO_FACTOR` | `false` | `true` (disarankan) | Mewajibkan 2FA bagi staf panel |
| `BOKA_EMAIL`, `BOKA_WHATSAPP` | contoh | kontak resmi | Tampil di footer dan halaman kontak |

### 6.2 Aturan bisnis (`config/boka.php`)

Nilai di bawah adalah bawaan. Nilai yang ditandai "Panel" bisa diubah Super Admin di **Panel, Pengaturan** tanpa menyentuh kode; nilainya disimpan di tabel `settings`.

| Kunci | Bawaan | Panel | Arti |
| --- | --- | --- | --- |
| `payment_deadline_hours` | 24 | Ya | Batas waktu bayar setelah pesanan dibuat |
| `flash_sale_deadline_hours` | 1 | Ya | Batas bayar pesanan yang berisi item flash sale |
| `reupload_deadline_hours` | 6 | Ya | Batas unggah ulang setelah bukti ditolak pertama kali |
| `reminder_hours_before_deadline` | 2 | | Pengingat email sebelum batas bayar |
| `auto_complete_days` | 7 | | Pesanan otomatis Selesai setelah paket terakhir dikirim |
| `max_pending_orders` | 3 | Ya | Maksimal pesanan menunggu pembayaran per akun |
| `expired_block_count` / `expired_block_window_days` | 3 / 7 | Ya | 3 pesanan kedaluwarsa dalam 7 hari memicu blokir checkout |
| `checkout_block_hours` | 24 | Ya | Lama blokir checkout |
| `orders_per_hour` | 5 | Ya | Maksimal pesanan per jam per akun |
| `second_approval_threshold` | 5.000.000 | Ya | Ambang nominal yang butuh persetujuan kedua |
| `service_fee` | 0 | Ya | Biaya layanan per pesanan |
| `packaging_weight_gram` | 100 | Ya | Berat kemasan per paket |
| `stock_inbound_max` | 1.000 | Ya | Stok masuk di atas angka ini butuh konfirmasi password |
| `low_stock_default` | 5 | | Ambang stok menipis bawaan |
| `similarity.block` / `similarity.suggest` | 0,85 / 0,65 | | Ambang skor deteksi produk serupa |
| `recommendation.*` | lihat file | | Bobot dan jendela waktu rekomendasi |
| `shipping.couriers` | jne, jnt, sicepat, pos | | Kurir ekspedisi yang ditawarkan |
| `pickup_max_attempts` / `pickup_lock_minutes` | 5 / 15 | | Penguncian input kode ambil |

---

## 7. Akun Contoh dan Login Pertama

Data contoh hanya untuk lokal dan staging. Semua password: `password`.

| Peran | Email |
| --- | --- |
| Super Admin | `super@boka.test`, `super2@boka.test` |
| Admin Transaksi | `transaksi@boka.test` |
| Admin | `admin@boka.test` |
| Pembeli | `pembeli@boka.test` |

Setelah login, staf (Admin, Admin Transaksi, Super Admin) diarahkan ke `/panel`; Pembeli diarahkan ke beranda atau ke halaman yang tadinya dituju (misalnya checkout).

> **Catatan 2FA:** atas keputusan klien, 2FA saat ini dimatikan (`BOKA_TWO_FACTOR=false`) karena pengguna belum familiar. Nilai `true` menyalakan kembali menu 2FA, mewajibkannya di panel, dan meminta kode saat mengubah rekening tujuan. Aktifkan sebelum produksi.

---

## 8. Implementasi Fungsional: Menyiapkan Toko sampai Pesanan Selesai

Bagian ini menjelaskan urutan kerja dari toko kosong sampai pesanan pertama selesai. Di produksi, mulai dari database tanpa data contoh (lihat bagian 13).

### 8.1 Urutan pengisian data awal

| Urutan | Menu panel | URL | Siapa | Isi |
| --- | --- | --- | --- | --- |
| 1 | Pengguna | `/panel/pengguna` | Super Admin | Undang Admin dan Admin Transaksi lewat email (tautan berlaku 48 jam; Admin mengatur password sendiri) |
| 2 | Pengaturan | `/panel/pengaturan` | Super Admin | Batas bayar, aturan anti-penimbunan, ambang persetujuan kedua, biaya layanan, berat kemasan, sinonim pencarian |
| 3 | Rekening | `/panel/pengaturan` | Super Admin | Rekening tujuan resmi organisasi; rekening baru tampil di checkout setelah masa tunggu 24 jam dan semua Super Admin mendapat email |
| 4 | Titik asal kirim | `/panel/kelola/origin` | Admin | Nama, alamat, kecamatan, kontak, kurir, layanan ambil sendiri dan jamnya, tarif kurir lokal per kecamatan |
| 5 | Kategori | `/panel/kelola/category` | Admin | Kategori dua tingkat dengan kode SKU, ikon, dan urutan |
| 6 | Produsen | `/panel/kelola/producer` | Admin | Profil Karang Taruna/UMKM: jenis, desa, kecamatan, cerita, foto, kontak, titik asal bawaan |
| 7 | Produk | `/panel/produk` | Admin | Produk, varian, foto, tag, berat; lalu ubah status ke Aktif |
| 8 | Stok | `/panel/stok` | Admin | Stok masuk per varian dengan alasan |
| 9 | Promosi | `/panel/kelola/voucher`, `/panel/kelola/flash-sale` | Admin | Voucher belanja/ongkir, flash sale dengan kuota |
| 10 | Banner | `/panel/kelola/banner` | Admin | Gambar desktop dan HP, tautan, jadwal tayang |
| 11 | Konten | `/panel/kelola/post`, `/panel/kelola/page` | Admin | Kabar kegiatan, halaman Tentang, Kebijakan Privasi, Syarat, Pengembalian |

### 8.2 Mengelola produk

Produk melewati empat status. Hanya produk **Aktif** yang tampil di toko, muncul di pencarian, dan bisa dibeli.

| Status | Arti | Tampil di toko |
| --- | --- | --- |
| Draf | Sedang disiapkan | Tidak |
| Aktif | Lolos syarat tayang | Ya |
| Nonaktif | Disembunyikan sementara | Tidak; tautan lama menampilkan "produk tidak tersedia" |
| Arsip | Tidak dijual lagi (pengganti hapus untuk produk yang pernah dipesan) | Tidak |

Syarat tayang (diperiksa `ProductPublisher`): minimal satu foto, kategori, produsen, dan titik asal terisi; minimal satu varian dengan harga dan berat lebih dari 0; harga coret lebih besar dari harga jual; pemeriksaan produk serupa sudah diputuskan.

**Deteksi produk serupa** (`SimilarityChecker`): saat menyimpan produk baru, sistem menghitung skor kemiripan nama (tanpa AI). Skor di atas 0,85 menahan penyimpanan sampai Admin memilih: tambah stok ke produk lama, jadikan varian baru, atau simpan sebagai produk terpisah. SKU dan barcode yang sudah dipakai selalu ditolak. Produk duplikat bisa digabung; tautan lama dialihkan dengan HTTP 301.

### 8.3 Mengelola stok

- Setiap perubahan stok tercatat di `stock_movements` (masuk, keluar, opname, checkout, restore, retur) dan tidak bisa diubah atau dihapus.
- Pengurangan melebihi stok ditolak. Stok masuk di atas `stock_inbound_max` butuh konfirmasi password.
- **Stok opname** per titik asal: mulai sesi, isi hitungan fisik, lalu posting. Penjualan selama sesi tetap dihitung, sehingga opname tidak menimpa transaksi.
- Stok dipotong **saat checkout**, bukan saat pembayaran disetujui, dan dikembalikan otomatis jika pesanan kedaluwarsa atau dibatalkan.

### 8.4 Alur belanja Pembeli

1. Tamu menjelajah katalog dan menambah produk ke keranjang (tersimpan di cookie).
2. Saat checkout, Tamu diminta mendaftar atau login; keranjang digabung ke akun.
3. Pembeli memverifikasi email (checkout hanya untuk email terverifikasi).
4. Pembeli memilih alamat. Item dikelompokkan per titik asal menjadi beberapa paket.
5. Pembeli memilih metode kirim per paket: ekspedisi, kurir lokal, atau ambil sendiri.
6. Pembeli memasukkan voucher (maksimal satu voucher belanja dan satu voucher ongkir) dan memilih rekening tujuan.
7. Sistem menghitung total lalu membuat pesanan. Stok dipotong dan batas bayar dicatat.
8. Pembeli mentransfer nominal tepat termasuk kode unik, lalu mengunggah bukti (JPG, PNG, WebP, atau PDF, maksimal 5 MB).

Rumus total pesanan (dihitung `PriceCalculator`):

```
Total = (Subtotal - Voucher belanja) + (Ongkir - Voucher ongkir) + Biaya layanan + Kode unik
```

Harga aktif per item: harga flash sale jika berlaku, lalu harga diskon produk, lalu harga normal. Diskon persen dibulatkan ke bawah. Semua nominal disimpan sebagai bilangan bulat rupiah.

### 8.5 Status pesanan

Pesanan berjalan di atas state machine (`OrderStatus`). Perpindahan di luar tabel ditolak, termasuk dari Super Admin.

| Dari | Ke | Pemicu |
| --- | --- | --- |
| (baru) | Menunggu Pembayaran | Pembeli membuat pesanan |
| Menunggu Pembayaran | Menunggu Verifikasi | Pembeli upload bukti sebelum batas |
| Menunggu Pembayaran | Kedaluwarsa | Penjadwal: batas bayar lewat (stok dan voucher kembali, item kembali ke keranjang) |
| Menunggu Pembayaran | Dibatalkan | Pembeli membatalkan |
| Menunggu Verifikasi | Diproses | Admin Transaksi menyetujui setelah dana terlihat di mutasi rekening |
| Menunggu Verifikasi | Menunggu Pembayaran | Penolakan pertama (batas unggah ulang 6 jam) |
| Menunggu Verifikasi | Dibatalkan | Penolakan kedua |
| Diproses | Dikirim | Semua paket punya resi atau sudah siap diambil |
| Diproses | Dibatalkan | Admin membatalkan dengan alasan |
| Dikirim | Selesai | Pembeli menekan "Pesanan diterima", atau otomatis 7 hari setelah paket terakhir dikirim |

### 8.6 Verifikasi pembayaran (Admin Transaksi)

1. Buka **Panel, Pembayaran** (`/panel/pembayaran`). Antrean diurutkan dari yang paling lama menunggu.
2. Bandingkan bukti transfer dengan tagihan di layar yang sama.
3. Periksa mutasi rekening di internet banking. Gambar bukti hanya alat bantu; pembayaran sah hanya jika dana terlihat di mutasi.
4. Isi daftar periksa mutasi beserta tanggal dan jam mutasi. Tombol Setujui baru aktif setelah daftar periksa terisi.
5. Konfirmasi password, lalu setujui atau tolak dengan alasan template.
6. Pesanan di atas ambang (bawaan Rp 5.000.000) butuh persetujuan kedua dari orang yang berbeda.
7. Jika bukti yang sama pernah dipakai di pesanan lain, panel menampilkan peringatan bukti ganda (hash SHA-256).

### 8.7 Pengiriman

| Metode | Langkah Admin |
| --- | --- |
| Ekspedisi | Isi nomor resi per paket di detail pesanan. Cetak label alamat dari tombol Label |
| Kurir lokal | Isi resi/keterangan, lalu tandai terkirim dengan foto serah terima dan nama penerima |
| Ambil sendiri | Tandai "siap diambil". Pembeli menunjukkan kode 6 digit atau QR; petugas memasukkan kode. Lima kali salah mengunci input 15 menit |

Status pesanan berubah menjadi Dikirim hanya setelah **semua** paket punya resi atau siap diambil.

### 8.8 Pembatalan dan refund

- Admin bisa membatalkan pesanan berstatus Diproses dengan alasan; stok dikembalikan.
- Refund diajukan dan disetujui oleh dua orang berbeda (izin `refund.proses`), hanya ke rekening atas nama Pembeli atau pengirim pada bukti transfer, dan wajib disertai bukti transfer refund.

### 8.9 Laporan, audit, dan sistem

| Menu | Fungsi |
| --- | --- |
| Laporan (`/panel/laporan`) | Penjualan per periode, produk dan kategori terlaris, kata kunci tanpa hasil, pemakaian voucher; ekspor CSV (maksimal 5.000 baris) |
| Log audit (`/panel/audit`) | Siapa mengubah apa, kapan, nilai lama dan baru; Admin melihat miliknya sendiri, Super Admin melihat semua |
| Sistem (`/panel/sistem`) | Status antrean, bersihkan cache |
| Pelanggan (`/panel/pelanggan`) | Daftar Pembeli, nonaktifkan akun, blokir checkout |
| Ulasan (`/panel/ulasan`) | Sembunyikan ulasan yang melanggar, balas sebagai toko |

---

## 9. Panduan Pengembangan

### 9.1 Mode pengembangan

```bash
npm run dev          # Vite dev server dengan hot reload
php artisan serve
php artisan queue:work
```

### 9.2 Konvensi kode

- Aturan bisnis ditulis sekali di `app/Domain/<Domain>` dan dipakai bersama oleh web, API v1, dan MCP.
- Pelanggaran aturan bisnis dilempar sebagai `App\Domain\BusinessRuleException`. Untuk API hasilnya JSON berformat seragam; untuk web hasilnya pesan toast.
- ID di URL dan data ke browser selalu dienkode dengan `App\Support\HashId`. Kode tak sah dianggap data tidak ada (404).
- Model yang perlu jejak audit memakai trait `App\Models\Concerns\Audited`.
- Warna hanya lewat token di `resources/css/app.css` (`--primary`, `--gold`, `--leaf`, dan seterusnya). Warna yang ditulis langsung di komponen dilarang agar mode gelap tetap benar.
- Teks antarmuka toko wajib ada di `lang/id.json` dan `lang/en.json`. Panel Admin sengaja hanya berbahasa Indonesia.

### 9.3 Menambah modul CRUD di panel

Modul sederhana (kategori, produsen, titik asal, voucher, flash sale, banner, kabar, halaman) memakai kelas generik di `app/Panel`:

1. Buat kelas baru di `app/Panel/Resources`, turunan `App\Panel\Resource`.
2. Isi `title()`, `columns()` (kolom tabel), `fields()` (isian form), dan `rules()` (validasi).
3. Daftarkan kelas di `App\Panel\Resources::all()`.
4. Modul langsung tersedia di `/panel/kelola/<slug>`; slug diturunkan dari nama kelas (contoh `FlashSaleResource` menjadi `flash-sale`).

### 9.4 Rute bertipe (Wayfinder)

Laravel Wayfinder membuat fungsi TypeScript untuk setiap rute dan controller di `resources/js/routes` dan `resources/js/actions`. File ini dibuat ulang otomatis saat build. Jangan mengedit file di dua folder tersebut secara manual.

### 9.5 Pemeriksaan kualitas kode

| Perintah | Fungsi |
| --- | --- |
| `composer lint` | Merapikan kode PHP (Pint) |
| `composer types:check` | Analisis statis PHP (Larastan/PHPStan) |
| `npm run check` | Lint dan format frontend (Vite+) |
| `npm run types:check` | Pemeriksaan tipe TypeScript |
| `composer ci:check` | Semua pemeriksaan di atas ditambah tes, sama seperti di GitHub Actions |

---

## 10. Pengujian

### 10.1 Menjalankan tes

```bash
php artisan test        # atau klik dua kali uji.bat di Windows
```

Tes memakai SQLite di memori, sehingga data MySQL tidak tersentuh. Terdapat 92+ tes, termasuk tes kriteria penerimaan di `tests/Boka`.

| File tes | Cakupan |
| --- | --- |
| `PricingTest.php` | Rumus total, voucher persen/nominal, batas maksimal, kode unik |
| `OrderFlowTest.php` | State machine, kedaluwarsa, penolakan, idempotensi, paket per titik asal, kode ambil, refund |
| `StockTest.php` | Pemotongan stok saat checkout, pengurangan berlebih, stok opname, stok masuk tidak wajar |
| `CatalogTest.php` | Syarat tayang, duplikat SKU, deteksi produk serupa, rekomendasi |
| `SecurityTest.php`, `SecurityHardeningTest.php` | Akses pesanan orang lain, izin per peran, header keamanan, CSRF, HashID |
| `LocaleAndApiTest.php` | Pergantian bahasa, hreflang, mode gelap SSR, API v1 |

### 10.2 Status kriteria penerimaan

| Status | Kriteria |
| --- | --- |
| Sudah diuji otomatis | K1, K2 (versi berurutan), K3 sampai K9, K11, K13 (data), K14 sampai K31 |
| Butuh staging | K2 dengan 50 proses paralel sungguhan (MySQL), K10 (kecepatan saran pencarian), K12 (Lighthouse) |
| Belum dibuat | K32 (ekspor besar lewat antrean), K33 (konversi gambar ke WebP) |

### 10.3 Anggaran JavaScript

Ukuran JavaScript awal halaman toko (gzip): beranda 192 KB, produk 192 KB, katalog 191 KB, keranjang 189 KB, checkout 197 KB. Target spesifikasi: di bawah 200 KB. Karena itu animasi memakai CSS dan IntersectionObserver, bukan pustaka animasi besar.

---

## 11. API v1 untuk Aplikasi Mobile

### 11.1 Konvensi

- Prefiks `/api/v1`, format JSON, nama field `snake_case`, waktu ISO 8601.
- Autentikasi: header `Authorization: Bearer <token>`. Token didapat dari `POST /api/v1/auth/login`.
- Bahasa: header `Accept-Language: id` atau `en`.
- Galat seragam: `{ "message": "...", "code": "...", "errors": { ... } }` dengan kode HTTP 401, 403, 404, 409, 422, atau 429.
- `POST /orders` wajib membawa header `Idempotency-Key`; dua permintaan dengan kunci yang sama menghasilkan satu pesanan.
- Batas laju: 60 permintaan per menit umum, 5 per menit untuk login/registrasi, 10 per jam untuk upload bukti.

### 11.2 Contoh pemakaian

```bash
# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -d "email=pembeli@boka.test" -d "password=password" -d "device_name=hp-uji"

# Daftar produk
curl http://localhost:8000/api/v1/products -H "Accept-Language: id"

# Keranjang (dengan token)
curl http://localhost:8000/api/v1/cart \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"
```

### 11.3 Daftar endpoint

| Kelompok | Endpoint | Login |
| --- | --- | --- |
| Auth | `POST auth/register`, `POST auth/login`, `POST auth/password/forgot` | Tidak |
| Auth | `POST auth/logout`, `POST auth/email/resend` | Ya |
| Katalog | `GET categories`, `GET products`, `GET products/{slug}`, `GET producers/{slug}` | Tidak |
| Pencarian | `GET search/suggest?q=`, `GET search?q=` | Tidak |
| Rekomendasi | `GET recommendations/home`, `GET recommendations/product/{slug}` | Tidak |
| Konten | `GET banners`, `GET posts`, `GET posts/{slug}` | Tidak |
| Profil | `GET me`, `PATCH me`, `DELETE me` | Ya |
| Alamat | `GET/POST addresses`, `PATCH/DELETE addresses/{id}`, `GET shipping/destinations` | Ya |
| Keranjang | `GET cart`, `POST cart/items`, `PATCH/DELETE cart/items/{variant}` | Ya |
| Wishlist | `GET wishlist`, `POST/DELETE wishlist/{product}` | Ya |
| Notifikasi | `GET notifications`, `POST notifications/read` | Ya |
| Pesanan | `GET orders`, `GET orders/{code}` | Ya |
| Checkout | `POST checkout/quote`, `POST checkout/shipping-options`, `POST orders` | Ya, email terverifikasi |
| Pesanan | `POST orders/{code}/payment-proof`, `POST orders/{code}/cancel`, `POST orders/{code}/complete` | Ya, email terverifikasi |
| Ulasan | `POST order-items/{id}/review` | Ya, email terverifikasi |

---

## 12. Server MCP untuk Admin

Server MCP memungkinkan Admin bertanya kepada asisten AI (misalnya "pesanan mana yang menunggu verifikasi lebih dari 12 jam?"). Asisten membaca data toko lewat tool yang aman. Fitur ini tidak mengubah keputusan bahwa rekomendasi toko tidak memakai AI.

| Hal | Rincian |
| --- | --- |
| Endpoint | `POST /mcp/toko` |
| Autentikasi | Token Sanctum milik akun Admin |
| Batas laju | 30 panggilan per menit per token |
| Izin | Sama dengan panel; setiap panggilan tercatat di log audit |
| Tool baca | `toko_list_pending_verifications`, `toko_get_order`, `toko_search_orders`, `toko_sales_summary`, `toko_low_stock`, `toko_search_insights` |
| Tool tulis | `toko_set_tracking_number` (isi resi; idempoten, tidak destruktif) |
| Sengaja tidak ada | Menyetujui pembayaran, membatalkan pesanan, mengubah harga, mengelola akun |

Uji manual: `php artisan mcp:inspector mcp/toko`. Pertanyaan evaluasi ada di `tests/Mcp/evaluation.xml` (berlaku untuk data seed dalam 30 hari setelah seeding).

---

## 13. Deploy ke Produksi (VPS Ubuntu)

Bagian ini memakai contoh domain `boka.example.id`, folder `/var/www/boka`, dan pengguna sistem `www-data`. Sesuaikan dengan keadaan sebenarnya. File konfigurasi Nginx, Supervisor, dan cron di bawah adalah templat yang belum ada di repositori.

### 13.1 Spesifikasi server

| Komponen | Minimum awal |
| --- | --- |
| VPS | 2 vCPU, 4 GB RAM, SSD 80 GB, pusat data di Indonesia |
| Sistem operasi | Ubuntu Server LTS (24.04) |
| Asumsi beban | Hingga 2.000 kunjungan/hari, 50 pesanan/hari, 5.000 varian produk |
| Target | Ketersediaan 99,5%/bulan; p95 halaman di bawah 300 ms |

### 13.2 Memasang paket server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip nginx supervisor ufw

# PHP 8.4 dan ekstensinya
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-gd php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL (8.4 LTS disarankan lewat repositori APT resmi MySQL)
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

### 13.3 Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Login SSH sebaiknya hanya dengan kunci. MySQL, Redis, dan Meilisearch hanya boleh mendengar di `127.0.0.1`.

### 13.4 Database produksi

```sql
CREATE DATABASE boka CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'boka'@'localhost' IDENTIFIED BY 'ganti-dengan-password-kuat';
GRANT ALL PRIVILEGES ON boka.* TO 'boka'@'localhost';
FLUSH PRIVILEGES;
```

### 13.5 Mengambil kode dan build

```bash
sudo mkdir -p /var/www/boka && sudo chown $USER:www-data /var/www/boka
git clone <url-repositori> /var/www/boka
cd /var/www/boka

composer install --no-dev --optimize-autoloader
npm ci
npm run build:ssr

cp .env.example .env
php artisan key:generate
```

### 13.6 `.env` produksi

```dotenv
APP_NAME=Boka
APP_ENV=production
APP_DEBUG=false
APP_URL=https://boka.example.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=id

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=boka
DB_USERNAME=boka
DB_PASSWORD=ganti-dengan-password-kuat

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.penyedia-email.id
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="halo@boka.example.id"
MAIL_FROM_NAME="Boka"

SCOUT_DRIVER=database
RAJAONGKIR_API_KEY=...
BOKA_TWO_FACTOR=true
BOKA_EMAIL=halo@boka.example.id
BOKA_WHATSAPP=08xx-xxxx-xxxx
```

Domain email wajib memakai SPF, DKIM, dan DMARC agar email toko sulit dipalsukan.

### 13.7 Migrasi tanpa data contoh

Di produksi, jangan jalankan `BokaSeeder` (berisi akun dan data contoh). Jalankan migrasi dan peran saja:

```bash
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan storage:link
php artisan optimize
```

### 13.8 Super Admin pertama

```bash
php artisan boka:super-admin email@domain "Nama Lengkap"
```

Perintah meminta password (minimal 12 karakter). Buat **minimal dua** Super Admin agar akses tidak hilang jika satu perangkat 2FA rusak. Setelah itu, Admin lain diundang dari panel.

### 13.9 Hak akses folder

```bash
sudo chown -R $USER:www-data /var/www/boka
sudo chmod -R ug+rwx /var/www/boka/storage /var/www/boka/bootstrap/cache
```

### 13.10 Nginx

Simpan sebagai `/etc/nginx/sites-available/boka`:

```nginx
server {
    listen 80;
    server_name boka.example.id;
    root /var/www/boka/public;
    index index.php;

    client_max_body_size 10M;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~* \.(?:css|js|woff2|svg|png|jpg|jpeg|webp|ico)$ {
        expires 30d;
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/boka /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Header keamanan (CSP dengan nonce, X-Frame-Options, Referrer-Policy, HSTS di produksi, dan lainnya) sudah dikirim oleh middleware `SecurityHeaders`, sehingga tidak perlu digandakan di Nginx.

### 13.11 SSL (HTTPS)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d boka.example.id
```

### 13.12 Supervisor: antrean dan SSR

Simpan sebagai `/etc/supervisor/conf.d/boka.conf`:

```ini
[program:boka-queue]
command=php /var/www/boka/artisan queue:work --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/boka/storage/logs/queue.log

[program:boka-ssr]
command=php /var/www/boka/artisan inertia:start-ssr
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/boka/storage/logs/ssr.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

Jika SSR mati, halaman tetap berjalan dengan rendering di browser, tetapi meta Open Graph untuk pratinjau WhatsApp hilang. Pantau proses ini.

### 13.13 Penjadwal (cron)

```bash
sudo crontab -u www-data -e
```

Tambahkan baris:

```
* * * * * cd /var/www/boka && php artisan schedule:run >> /dev/null 2>&1
```

| Tugas terjadwal | Jadwal |
| --- | --- |
| `boka:expire-orders` | Setiap menit |
| `boka:payment-reminders` | Setiap 5 menit |
| `boka:auto-complete` | Setiap jam |
| `boka:prune-search-logs` | Setiap hari 02.00 WITA |

### 13.14 Merilis pembaruan

```bash
cd /var/www/boka
php artisan down --retry=30
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build:ssr
php artisan migrate --force
php artisan optimize
php artisan queue:restart
sudo supervisorctl restart boka-ssr
php artisan up
```

Untuk rilis tanpa henti layanan, gunakan pola folder rilis baru dan symlink (misalnya lewat Laravel Forge atau Deployer). Alur yang disarankan: setiap perubahan lewat pull request, CI hijau, gabung ke `main` dirilis ke staging, lalu rilis produksi dipicu manual dengan tag versi.

### 13.15 Backup dan pemulihan

| Item | Kebijakan |
| --- | --- |
| Database | `mysqldump` harian terenkripsi, disimpan di luar VPS |
| File | Folder `storage/app` (foto produk, bukti transfer privat) |
| Rahasia | Salinan `.env` (terutama `APP_KEY`) disimpan aman terpisah dari backup data |
| Masa simpan | Harian 14 hari, mingguan 8 minggu |
| Target | RPO 24 jam, RTO 4 jam |
| Uji pemulihan | Sebulan sekali ke server uji |

Contoh backup harian:

```bash
mysqldump --single-transaction -u boka -p boka | gzip > /backup/boka-$(date +%F).sql.gz
tar czf /backup/boka-storage-$(date +%F).tar.gz -C /var/www/boka storage/app
```

### 13.16 Pemantauan

- Pemantau ketersediaan dari luar ke `https://boka.example.id/up`, dengan peringatan ke email dan WhatsApp tim.
- Pelacak galat (misalnya Sentry atau Laravel Nightwatch) dan pemantau performa (Laravel Pulse).
- Peringatan khusus: antrean macet lebih dari 10 menit, penjadwal tidak berjalan lebih dari 5 menit, backup gagal, disk di atas 80%.

### 13.17 Peningkatan opsional

| Komponen | Cara mengaktifkan |
| --- | --- |
| Redis (cache, sesi, antrean) | Pasang `redis-server` dan `php8.4-redis`, lalu ubah `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` ke `redis` |
| Meilisearch | Pasang Meilisearch dengan master key, isi `MEILISEARCH_HOST`/`MEILISEARCH_KEY`, ubah `SCOUT_DRIVER=meilisearch`, lalu `php artisan scout:import "App\Models\Product"` |
| Object storage S3 | Isi `AWS_*` dan arahkan disk gambar produk ke S3-compatible storage |

---

## 14. Keamanan

| Area | Kontrol yang sudah terpasang |
| --- | --- |
| CSRF | Setiap form dan permintaan tulis wajib membawa token; tanpa token ditolak (HTTP 419) |
| HashID | ID di URL berupa kode acak (contoh `/panel/produk/Xk3a9pQz2L/edit`); akses tetap diperiksa kepemilikan dan izinnya |
| Header HTTP | CSP dengan nonce per permintaan, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, HSTS di produksi; `X-Powered-By` dihapus |
| Akun | Akun nonaktif tidak bisa login dan sesinya diakhiri; sesi dienkripsi; login 5 percobaan per menit; password produksi minimal 12 karakter dan dicek ke daftar password bocor |
| Panel | Sesi panel berakhir setelah 2 jam tidak aktif; konfirmasi password untuk menyetujui pembayaran, refund, dan aksi sensitif |
| Anti-bot | Honeypot di registrasi, batas 60 aksi tulis per menit per pengguna/IP, batas laju API |
| Bukti transfer | Disimpan privat, di-encode ulang (EXIF dan muatan tersembunyi hilang), asli terenkripsi, dibuka lewat URL bertanda tangan 10 menit |
| Data | Query selalu memakai parameter terikat; isi berita disaring HTMLPurifier; log audit untuk aksi Admin |
| UU PDP | Persetujuan kebijakan privasi saat daftar, unduh data pribadi, hapus akun dengan anonimisasi (pesanan tetap tersimpan sebagai catatan keuangan) |

---

## 15. Daftar Periksa Sebelum Peluncuran

**Konfigurasi**

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` memakai HTTPS
- [ ] `BOKA_TWO_FACTOR=true`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] SMTP aktif dengan SPF, DKIM, DMARC
- [ ] `RAJAONGKIR_API_KEY` terisi dan `destination_id` setiap titik asal sudah diisi
- [ ] Salinan `APP_KEY` disimpan aman

**Data dan akun**

- [ ] Tidak ada akun contoh `@boka.test` di produksi
- [ ] Minimal dua Super Admin dan dua Admin Transaksi
- [ ] Rekening tujuan atas nama organisasi, sudah melewati masa tunggu 24 jam
- [ ] Titik asal, tarif kurir lokal, kategori, produsen, dan produk nyata sudah diisi
- [ ] Halaman Kebijakan Privasi, Syarat dan Ketentuan, dan Kebijakan Pengembalian sudah ditulis

**Operasional**

- [ ] Supervisor menjalankan antrean dan SSR; cron penjadwal aktif
- [ ] Backup harian berjalan dan sudah diuji pulihkan
- [ ] Pemantau ketersediaan dan pelacak galat aktif
- [ ] Semua tes lolos (`php artisan test`)
- [ ] Uji coba dengan 10 pembeli nyata dan 2 Admin tanpa galat kritis
- [ ] Pindai OWASP ZAP baseline dan uji manual IDOR, unggah file berbahaya, manipulasi harga lewat API

---

## 16. Pemecahan Masalah

| Gejala | Penyebab umum | Solusi |
| --- | --- | --- |
| `mulai.bat` berhenti dengan "GAGAL" | Alat belum terpasang atau ekstensi PHP mati | Baca `storage/logs/mulai.log`; aktifkan ekstensi di `php.ini` |
| "Tidak bisa terhubung ke database" | MySQL mati atau kredensial salah | Nyalakan MySQL di XAMPP Control Panel; periksa `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` |
| Halaman putih atau galat Vite manifest | Tampilan belum dibuild | `npm run build:ssr` atau jalankan `npm run dev` |
| Pratinjau WhatsApp tidak muncul | SSR mati atau gambar produk SVG | Jalankan `inertia:start-ssr`; pakai foto JPG/PNG (WhatsApp tidak menampilkan SVG) |
| Email tidak terkirim | `MAIL_MAILER=log` atau antrean tidak berjalan | Lihat `storage/logs/laravel.log`; pastikan `queue:work` berjalan |
| Pesanan tidak pernah kedaluwarsa | Penjadwal tidak berjalan | Jalankan `schedule:work` (lokal) atau periksa cron (produksi). Status juga diperiksa saat halaman pesanan dibuka |
| Galat 419 saat kirim form | Sesi kedaluwarsa atau token CSRF hilang | Muat ulang halaman; periksa `SESSION_DOMAIN` dan `APP_URL` |
| Galat 429 | Batas laju tercapai | Tunggu satu menit |
| Foto produk tidak tampil | `storage:link` belum dijalankan | `php artisan storage:link` |
| Port 13714 sudah dipakai | Proses SSR lama masih berjalan | `php artisan inertia:stop-ssr` |
| Tautan lama rusak setelah pindah server | `APP_KEY` berbeda | Pulihkan `APP_KEY` lama dari salinan `.env` |
| Perubahan `.env` tidak berpengaruh | Konfigurasi masih ter-cache | `php artisan optimize:clear` lalu `php artisan optimize` |

---

## 17. Status Implementasi dan Pekerjaan Lanjutan

### 17.1 Pekerjaan sebelum peluncuran

| Pekerjaan | Keterangan |
| --- | --- |
| Konversi gambar ke WebP | Foto masih tersimpan apa adanya; belum ada `srcset` per lebar dan gambar Open Graph JPEG 1200 x 630 |
| Ekspor lanjutan | Baru CSV langsung (maksimal 5.000 baris); belum ada Excel, PDF per produsen, ekspor besar lewat antrean, menu "Ekspor saya" |
| Impor CSV produk | Beserta pratinjau produk serupa |
| Keamanan akun Pembeli | Email login dari perangkat baru, konfirmasi ganti email ke dua alamat, Cloudflare Turnstile |
| Email stok menipis harian | Ringkasan untuk Admin |
| Infrastruktur produksi | Redis, Horizon, Meilisearch, backup otomatis |
| RajaOngkir | Klien sudah ada; perlu API key dan `destination_id` tiap titik asal |
| Teks antarmuka | Halaman lupa/reset password, tantangan 2FA, dan pengaturan akun masih berbahasa Inggris bawaan starter kit |

### 17.2 Roadmap

| Fase | Isi | Syarat selesai |
| --- | --- | --- |
| Fase 0: Fondasi | Konfirmasi asumsi, identitas visual, skema database, repositori, CI, staging, VPS | Pertanyaan terbuka terjawab, CI hijau |
| Fase 1: Toko inti (berjalan) | Katalog, pencarian, akun, keranjang, checkout, ongkir, pembayaran semi-otomatis, panel Admin, API v1, multibahasa, mode gelap | K1 sampai K33 lulus; uji coba 10 pembeli dan 2 Admin |
| Fase 2: Promosi dan wawasan | Rekomendasi lengkap, laporan lanjutan, pencocokan mutasi rekening dari file, OTP WhatsApp | Rekomendasi sesuai aturan, laporan bisa diekspor |
| Fase 3: Mobile dan MCP | Aplikasi mobile di atas API v1, notifikasi push, server MCP | Uji end-to-end aplikasi, 10 pertanyaan evaluasi MCP lolos |
| Fase 4: Skala | Payment gateway otomatis, akun penjual (marketplace multi-penjual), "Sering dibeli bersama" | Ditentukan bersama klien |

---

## Lampiran A. Perintah Artisan Khusus Boka

| Perintah | Fungsi |
| --- | --- |
| `php artisan boka:super-admin {email} {nama}` | Membuat akun Super Admin (hanya lewat server) |
| `php artisan boka:expire-orders` | Mengedaluwarsakan pesanan lewat batas bayar dan mengembalikan stok |
| `php artisan boka:payment-reminders` | Mengirim pengingat 2 jam sebelum batas bayar |
| `php artisan boka:auto-complete` | Menyelesaikan pesanan 7 hari setelah paket terakhir dikirim |
| `php artisan boka:prune-search-logs` | Menghapus log pencarian berumur lebih dari 180 hari |
| `php artisan mcp:inspector mcp/toko` | Menguji server MCP secara manual |

## Lampiran B. Peta Spesifikasi ke Kode

| Bagian spesifikasi | Lokasi kode |
| --- | --- |
| 2 Peran dan izin | `database/seeders/RoleSeeder.php`, `Gate::before` di `AppServiceProvider` |
| 3 Toko dan Pembeli | `app/Http/Controllers/Shop`, `resources/js/pages/shop` |
| 4 Panel Admin | `app/Http/Controllers/Panel`, `app/Panel`, `resources/js/pages/panel` |
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
| 12 Desain | `resources/css/app.css`, `resources/js/components/shop` |
| 12.7 Bahasa | `lang/id.json`, `lang/en.json`, `lang/id/*.php` |
| 14 Server MCP | `app/Mcp`, `routes/ai.php`, `tests/Mcp/evaluation.xml` |
| 15 Pengujian | `tests/Boka` |

## Lampiran C. Glosarium

| Istilah | Arti |
| --- | --- |
| Titik asal | Lokasi pengiriman barang di Kabupaten Bone (contoh Gudang Watampone) |
| Paket | Kelompok item dari satu titik asal dalam satu pesanan, dengan ongkir dan resi sendiri |
| Kode unik | Angka acak 1 sampai 999 rupiah yang ditambahkan ke total agar transfer mudah dicocokkan |
| Mutasi rekening | Catatan dana masuk di internet banking; dasar sah persetujuan pembayaran |
| Stok opname | Penghitungan fisik stok untuk mencocokkan catatan sistem |
| SSR | Server-Side Rendering: halaman React dirender di server sebelum dikirim ke browser |
| HashID | Kode acak pengganti ID angka berurutan di URL |
| MCP | Model Context Protocol: cara asisten AI memanggil tool aplikasi secara aman |
| Idempotency-Key | Kunci unik per percobaan checkout agar pesanan tidak tercipta ganda |
