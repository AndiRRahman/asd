# Panduan Alur Penggunaan Platform Tejaseku

Dokumen ini berisi panduan alur kerja platform **Tejaseku** (*Platform Digitalisasi Pemasaran UMKM Desa*), baik untuk **Masyarakat / Pembeli (Publik)** maupun untuk **Pengelola (Karang Taruna / Admin)**.

---

## 1. Panduan Sederhana untuk Publik / Pembeli

> **Mudah & Cepat:** Anda **tidak perlu mendaftar akun atau login**, dan **tidak perlu repot transfer bank**. Semua pemesanan langsung terhubung ke WhatsApp pengelola.

### Langkah 1: Buka Website atau Pindai Kemasan
- **Lewat Ponsel / Laptop:** Buka browser dan kunjungi alamat website Tejaseku.
- **Lewat Kemasan Produk:** Arahkan kamera ponsel Anda ke stiker/QR Code pada kemasan produk untuk langsung membuka informasi produk tersebut.

### Langkah 2: Lihat Informasi Produk & Cara Seduh
- Anda dapat melihat foto asli produk, harga, asal desa, dan cerita mitra UMKM pembuatnya.
- Pada produk minuman atau makanan tertentu, terdapat bagian khusus **"Cara Seduh / Petunjuk Penyajian"** agar Anda dapat menikmati produk dengan cita rasa terbaik.
- Jika ada hal yang ingin ditanyakan, cukup klik tombol **"Chat Admin"** untuk langsung terhubung ke WhatsApp pengurus.

### Langkah 3: Pilih Jumlah & Masukkan ke Keranjang
- Tentukan pilihan/varian (misal ukuran atau berat) dan jumlah barang yang ingin dibeli.
- Klik tombol **"Tambah ke Keranjang"**. Anda bisa memilih beberapa produk sekaligus.

### Langkah 4: Pesan via WhatsApp
- Buka ikon **Keranjang Belanja** di pojok kanan atas.
- Periksa daftar pesanan Anda, lalu klik tombol hijau **"Pesan Sekarang via WhatsApp"**.
- Aplikasi WhatsApp akan otomatis terbuka dengan rincian pesanan dan total harga yang sudah terisi rapi.
- Cukup kirimkan pesan tersebut. Admin Karang Taruna akan langsung memproses pesanan dan menyepakati pengantaran (COD / tunai / kurir lokal).

---

## 2. Panduan Alur untuk Pengelola (Karang Taruna / Admin)

> **Praktis & Efisien:** Pengelolaan katalog, stok, dan kontak admin dilakukan melalui satu pintu di **Panel Admin**.

### Langkah 1: Masuk ke Panel Admin
1. Buka halaman login di browser: `/login`.
2. Masukkan email dan password admin yang telah terdaftar.
3. Anda akan diarahkan ke Dashboard Panel Kerja (`/panel`).

### Langkah 2: Menambah Produk Baru
1. Masuk ke menu **Produk** (`/panel/produk`) dan klik **"+ Produk baru"**.
2. Masukkan data produk:
   - **Nama & Deskripsi Produk:** Nama barang dan penjelasannya.
   - **Cara Seduh / Petunjuk Penyajian (Opsional):** Panduan seduh atau penyajian untuk pembeli.
   - **Harga & Berat:** Harga jual produk dalam rupiah dan berat dalam gram.
   - **Stok Awal:** Jumlah stok produk yang tersedia saat ini.
   - **Kategori & Produsen:** Kategori barang dan kelompok UMKM desa pembuatnya.
   - **Unggah Foto:** Pilih foto dari galeri atau kamera (maksimal **5 MB**). Foto akan otomatis dikonversi ke format WebP agar cepat dimuat di ponsel warga.
3. Klik **"Simpan produk"**. Produk akan **langsung aktif** dan seketika muncul di katalog publik website.

### Langkah 3: Mengelola Stok Barang
1. Buka menu **Stok & opname** (`/panel/stok`).
2. Jika ada pasokan baru dari warga atau stok berkurang di luar pesanan web, klik **"Mutasi stok"** pada produk terkait.
3. Masukkan jumlah mutasi (Masuk / Keluar). Stok pada katalog publik akan otomatis terbarui.

### Langkah 4: Mengatur Nomor WhatsApp Penerima Pesanan
- Nomor WhatsApp admin yang menerima pesanan otomatis dari web dapat diganti kapan saja melalui file konfigurasi `.env` pada baris `BOKA_WHATSAPP=08xxxxxxxxxx`.
- Semua tombol WhatsApp di website toko akan otomatis mengikuti nomor terbaru tersebut.

---

*Dokumen ini disusun untuk mendukung digitalisasi UMKM desa yang mandiri dan berkelanjutan bersama Karang Taruna.*

