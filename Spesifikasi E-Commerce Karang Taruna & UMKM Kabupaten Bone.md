# Spesifikasi E-Commerce Karang Taruna & UMKM Kabupaten Bone

Nov 2, 2026 · @Nur Fuad

## 1. Ringkasan dan asumsi

Fase 1 membangun **toko tunggal**: Admin menjadi satu-satunya penjual dan mengelola seluruh transaksi, sementara produk tetap mencantumkan asal produsennya (Karang Taruna atau UMKM). Arsitektur disiapkan sejak awal untuk aplikasi mobile (API v1) dan untuk berkembang menjadi marketplace multi-penjual tanpa menulis ulang inti sistem.

**Keputusan yang sudah diambil**

| Aspek | Keputusan |
| --- | --- |
| Stack | Laravel 13, Inertia.js v3, React 19 + TypeScript, SSR aktif |
| Database | MySQL 8.4 LTS |
| Hosting | VPS (Ubuntu LTS), pusat data di Indonesia |
| Pembayaran | Semi-otomatis: transfer bank, upload bukti, verifikasi Admin |
| Peran | Tamu, Pembeli, Admin, Super Admin |
| Rekomendasi | Berbasis aturan dan statistik pencarian, tanpa AI |
| Mobile | API v1 dengan Laravel Sanctum disiapkan sejak fase 1 |
| Produsen | Profil produsen sejak fase 1, disiapkan untuk naik menjadi akun penjual |
| Pengiriman | Beberapa titik asal di Kabupaten Bone; satu pesanan bisa terdiri dari beberapa paket |
| Bahasa dan tema | Bahasa Indonesia bawaan, bisa ganti bahasa; mode terang bawaan, bisa ganti ke mode gelap |

**Keputusan klien** (dikonfirmasi 2 November 2026)

1. Tamu boleh melihat katalog dan mencari produk tanpa login. Login hanya diwajibkan saat checkout.
2. Produsen (Karang Taruna desa atau UMKM) dicatat sebagai **profil produsen** mulai fase 1, belum sebagai akun penjual. Struktur datanya disiapkan agar profil bisa dinaikkan menjadi akun penjual pada fase multi-penjual tanpa migrasi data besar.
3. Barang bisa dikirim dari **beberapa titik asal** di Kabupaten Bone, termasuk Watampone. Pesanan yang berisi produk dari titik berbeda dipecah menjadi beberapa paket (bagian 6).
4. Semua nominal dalam rupiah bulat, disimpan sebagai bilangan bulat.
5. Antarmuka bawaan berbahasa Indonesia dan pengguna bisa mengganti bahasa. Mode terang menjadi bawaan dan pengguna bisa beralih ke mode gelap (bagian 12.7).

**Di luar cakupan fase 1:** akun penjual terpisah, payment gateway otomatis, chat real-time, dan aplikasi mobile itu sendiri (API-nya disiapkan, aplikasinya belum dibuat).

**Fitur tambahan yang disetujui masuk spesifikasi:** profil produsen, pemotongan stok saat checkout, kode unik nominal transfer, deteksi bukti transfer ganda, alur penolakan bukti, kurir lokal dan ambil sendiri, wishlist, ulasan pembeli terverifikasi, halaman dokumentasi kegiatan, laporan, log audit, 2FA untuk Admin, dan kepatuhan UU PDP. Rinciannya ada di bagian 3 sampai 11, dan masing-masing punya kriteria penerimaan di bagian 15.

## 2. Peran pengguna dan hak akses

Sistem memakai empat peran utama, ditambah Admin Transaksi, yaitu Admin dengan izin keuangan. Hak akses disimpan sebagai data (paket spatie/laravel-permission), sehingga peran baru seperti Staf Gudang atau Penjual bisa ditambah nanti tanpa mengubah kode otorisasi.

| Peran | Cara mendapat akun | Ringkasan kewenangan |
| --- | --- | --- |
| Tamu | Tidak perlu akun | Melihat landing page, katalog, detail produk, pencarian, konten dokumentasi |
| Pembeli | Daftar sendiri, aktif setelah verifikasi email | Keranjang, wishlist, checkout, upload bukti transfer, ulasan, kelola akun |
| Admin | Dibuatkan oleh Super Admin lewat undangan email | Mengelola produk, stok, pesanan, verifikasi pembayaran, promosi, banner, konten, laporan |
| Admin Transaksi | Admin resmi yang ditunjuk klien; izinnya diberikan oleh Super Admin | Semua kewenangan Admin + memegang akses internet banking untuk memeriksa mutasi rekening, menyetujui atau menolak pembayaran, memproses refund, laporan keuangan, ekspor berisi data Pembeli |
| Super Admin | Dibuat lewat perintah server saat instalasi | Semua kewenangan Admin + akun Admin, peran, pengaturan sistem, log audit |

**Matriks hak akses inti**

| Kemampuan | Tamu | Pembeli | Admin | Super Admin |
| --- | --- | --- | --- | --- |
| Lihat katalog dan cari produk | Ya | Ya | Ya | Ya |
| Keranjang dan wishlist | Keranjang lokal | Ya | Tidak | Tidak |
| Checkout dan upload bukti | Tidak | Ya (email terverifikasi) | Tidak | Tidak |
| Kelola produk, kategori, produsen, stok | Tidak | Tidak | Ya | Ya |
| Input resi dan kelola pengiriman | Tidak | Tidak | Ya | Ya |
| Verifikasi pembayaran dan refund | Tidak | Tidak | Hanya Admin Transaksi | Ya |
| Laporan keuangan dan ekspor berisi data Pembeli | Tidak | Tidak | Hanya Admin Transaksi | Ya |
| Voucher, diskon, flash sale, banner | Tidak | Tidak | Ya | Ya |
| Konten berita dan dokumentasi | Tidak | Tidak | Ya | Ya |
| Lihat dan nonaktifkan akun Pembeli | Tidak | Tidak | Ya | Ya |
| Buat, ubah, nonaktifkan akun Admin | Tidak | Tidak | Tidak | Ya |
| Rekening tujuan, batas waktu bayar, API key, SMTP | Tidak | Tidak | Tidak | Ya |
| Lihat log audit | Tidak | Tidak | Miliknya sendiri | Semua |

**Aturan tambahan**

- Admin dan Super Admin wajib memakai autentikasi dua langkah (TOTP).
- Undangan Admin berupa tautan bertanda tangan yang kedaluwarsa dalam 48 jam. Admin mengatur password sendiri, sehingga password tidak pernah dikirim lewat email.
- "Tanpa batasan" bagi Super Admin tetap punya dua pengecualian: tidak bisa melihat password siapa pun (hanya mereset), dan tidak bisa menghapus log audit dari antarmuka. Keduanya melindungi Anda sebagai developer saat terjadi sengketa transaksi.
- Siapkan minimal dua akun Super Admin, agar akses tidak hilang jika satu perangkat 2FA rusak.

**Admin Transaksi.** Klien menunjuk Admin resmi yang memegang akses internet banking rekening toko. Hanya Admin Transaksi dan Super Admin yang bisa menyetujui atau menolak pembayaran, karena hanya mereka yang bisa memastikan dana benar-benar masuk di mutasi rekening (11.1 T1). Persetujuan kedua untuk nominal besar (T3) dan refund (T8) dilakukan oleh Admin Transaksi lain atau Super Admin, tidak boleh oleh orang yang sama. Sebaiknya klien menunjuk minimal dua Admin Transaksi, agar verifikasi tidak terhenti saat salah satunya berhalangan.

## 3. Fitur sisi publik dan Pembeli

Pengunjung bisa menjelajah dan mencari tanpa login. Akun baru diwajibkan saat checkout, dan checkout hanya terbuka untuk email yang sudah terverifikasi.

**3.1 Landing page (sebelum login)**

1. Header tetap: logo, kotak pencarian instan, ikon keranjang dengan jumlah item, tombol Masuk/Daftar.
2. Hero banner berganti otomatis, dijadwalkan dari panel Admin.
3. Kategori populer dan blok "Jelajahi juga" (kategori jarang dicari), lihat bagian 7.
4. Promo aktif: flash sale dengan hitung mundur, dan produk diskon.
5. Produk terlaris dan produk terbaru.
6. Cerita produsen: kartu Karang Taruna dan UMKM beserta desa dan kecamatannya.
7. Dokumentasi kegiatan terbaru (berita dan galeri).
8. Ulasan dari pembeli terverifikasi.
9. Footer: kontak, rekening resmi, kebijakan privasi, syarat dan ketentuan, kebijakan pengembalian.

**3.2 Akun Pembeli**

- Registrasi dengan nama, email, nomor WhatsApp, password, dan persetujuan kebijakan privasi.
- Aktivasi lewat tautan email bertanda tangan, berlaku 60 menit, bisa dikirim ulang maksimal 3 kali per jam.
- Login, lupa password, ubah profil, ubah password, hapus akun.
- Buku alamat dengan beberapa alamat. Kecamatan dipilih dari daftar tujuan penyedia ongkir, bukan diketik bebas.

**3.3 Katalog dan detail produk**

- Filter: kategori, rentang harga, produsen, kecamatan asal, rating, ketersediaan stok. Urutan: relevansi, terbaru, terlaris, harga.
- Detail produk: galeri foto (zoom di desktop, geser di HP), varian (ukuran, berat, rasa), harga coret saat diskon, stok tersisa, berat untuk ongkir, profil produsen, ulasan, produk terkait.
- Tombol bagikan ke WhatsApp. Tautan memunculkan foto, nama, dan harga karena meta Open Graph dirender di server (SSR).

**3.4 Keranjang dan wishlist**

- Keranjang Tamu disimpan di browser, lalu digabung ke keranjang akun saat login.
- Keranjang Pembeli disimpan di database, sehingga sama di web dan aplikasi mobile.
- Harga di keranjang selalu harga terkini. Jika harga atau stok berubah sejak item ditambahkan, keranjang menampilkan pemberitahuan. Aturan jumlah beli terhadap stok ada di bagian 4.4.
- Wishlist dengan pemberitahuan email saat produk yang habis tersedia kembali.

**3.5 Checkout**

1. Pilih alamat pengiriman.
2. Sistem mengelompokkan item per titik asal. Setiap kelompok menjadi satu paket, misalnya "Paket 1 dari Watampone, Paket 2 dari Kec. Bengo".
3. Pilih metode kirim **per paket**: kurir ekspedisi, kurir lokal dalam Kabupaten Bone, atau ambil sendiri di titik asal paket itu (jika titik tersebut melayani pengambilan).
4. Masukkan voucher (maksimal satu voucher belanja dan satu voucher ongkir).
5. Pilih rekening tujuan transfer.
6. Tinjau rincian biaya (bagian 6), lalu buat pesanan.

Jika item berasal dari lebih dari satu titik, checkout menampilkan pemberitahuan bahwa pesanan dikirim dalam beberapa paket dengan ongkir masing-masing. Pembeli tetap membayar satu kali untuk seluruh pesanan.

**3.6 Pesanan saya**

- Daftar pesanan per status, dengan linimasa status di halaman detail.
- Hitung mundur batas pembayaran, nomor rekening dengan tombol salin, dan nominal tepat termasuk kode unik.
- Form upload bukti transfer: file, bank pengirim, nama pemilik rekening, tanggal transfer.
- Nomor resi per paket dengan tautan lacak, tombol "Pesanan diterima", dan unduh invoice PDF.
- Ulasan dengan rating 1 sampai 5 dan foto, hanya untuk pesanan berstatus Selesai.

**3.7 Notifikasi**

Email dan notifikasi di dalam situs untuk: pesanan dibuat, bukti diterima, pembayaran disetujui atau ditolak, pesanan dikirim, pengingat 2 jam sebelum batas bayar, dan pesanan kedaluwarsa.

**3.8 Bahasa dan tampilan**

- Pemilih bahasa dan tombol mode terang/gelap tersedia di header untuk semua pengunjung, termasuk Tamu.
- Pilihan Tamu disimpan di cookie. Pilihan Pembeli juga disimpan di profil, sehingga ikut ke perangkat lain dan aplikasi mobile.
- Email notifikasi dikirim dalam bahasa pilihan Pembeli. Rincian teknis di bagian 12.7.

## 4. Panel Admin dan Super Admin

Panel berada di `/panel`, terpisah dari tampilan toko, dan dibuka hanya setelah login dengan 2FA. Halaman pertama Admin adalah antrean kerja, bukan grafik: pesanan yang menunggu verifikasi tampil paling atas.

| Modul | Fungsi | Peran |
| --- | --- | --- |
| Dasbor | Pesanan menunggu verifikasi (diurutkan dari yang paling lama), pesanan siap kirim, stok menipis, pendapatan hari ini dan bulan ini | Admin |
| Verifikasi pembayaran | Bukti transfer berdampingan dengan nominal tagihan, rekening tujuan, dan riwayat pesanan pembeli; setujui atau tolak dengan template alasan; peringatan bukti ganda; daftar periksa mutasi rekening wajib (11.1 T1); persetujuan kedua untuk nominal besar (T3) | Admin Transaksi |
| Pesanan | Filter per status, ubah status sesuai aturan bagian 5, input resi per paket, cetak label alamat, batalkan dengan alasan, catat pengembalian dana dengan persetujuan dua orang (T8); kode ambil dan foto serah terima per paket (T6, T7) | Admin |
| Produk | Produk, varian, foto (urut dan pilih sampul), tag, berat, status tayang, impor massal dari CSV, deteksi produk serupa, alat gabung produk (4.1, 4.2) | Admin |
| Kategori | Kategori bertingkat dua level dengan ikon dan urutan | Admin |
| Produsen | Profil Karang Taruna dan UMKM: nama, jenis, desa, kecamatan, cerita, foto, kontak, titik asal kirim bawaan; disiapkan untuk dinaikkan menjadi akun penjual | Admin |
| Titik asal kirim | Daftar titik asal di Kabupaten Bone: nama, alamat, kecamatan, ID lokasi penyedia ongkir, kontak, kurir yang dilayani, layanan ambil sendiri dan jamnya, tarif kurir lokal per kecamatan tujuan; aktif/nonaktif | Admin |
| Stok | Mutasi stok masuk dan keluar dengan alasan, stok opname per titik asal, riwayat mutasi per varian, ambang stok menipis (4.3) | Admin |
| Promosi | Voucher, diskon produk, flash sale dengan kuota dan jadwal | Admin |
| Banner | Gambar desktop dan HP, tautan tujuan, jadwal tayang, urutan, posisi | Admin |
| Konten | Berita dan dokumentasi kegiatan, galeri, halaman statis (Tentang, Kebijakan); terjemahan opsional untuk setiap konten | Admin |
| Ulasan | Moderasi: sembunyikan ulasan yang melanggar, balas sebagai toko | Admin |
| Pelanggan | Daftar Pembeli, riwayat pesanan, nonaktifkan akun | Admin |
| Laporan | Penjualan per periode, produk dan kategori terlaris, kata kunci pencarian tanpa hasil, pemakaian voucher; ekspor Excel | Admin |
| Pengguna panel | Undang, ubah peran, nonaktifkan Admin; reset 2FA | Super Admin |
| Pengaturan | Identitas toko, rekening tujuan, batas waktu bayar, bahasa yang aktif, API key ongkir, SMTP | Super Admin |
| Log audit | Siapa mengubah apa, kapan, nilai lama dan baru | Super Admin |
| Sistem | Mode pemeliharaan, status antrean dan backup, bersihkan cache | Super Admin |

**Mengapa laporan "kata kunci tanpa hasil" penting:** daftar ini memberi tahu Admin produk apa yang dicari warga tetapi belum dijual. Laporan ini menjadi masukan langsung untuk Karang Taruna dan UMKM tentang produk yang perlu disediakan.

### 4.1 Pengelolaan barang

Setiap produk melewati empat status. Hanya produk berstatus Aktif yang tampil di toko, muncul di pencarian, dan bisa dibeli.

| Status | Arti | Tampil di toko | Bisa dibeli |
| --- | --- | --- | --- |
| Draf | Sedang disiapkan Admin | Tidak | Tidak |
| Aktif | Lolos syarat tayang | Ya | Ya, jika stok varian lebih dari 0 |
| Nonaktif | Disembunyikan sementara (misalnya musim panen selesai) | Tidak; tautan lama menampilkan "produk tidak tersedia" | Tidak |
| Arsip | Tidak dijual lagi; menggantikan hapus untuk produk yang pernah dipesan | Tidak | Tidak |

**Syarat tayang (Draf ke Aktif).** Sistem menolak aktivasi dan menyebut syarat yang belum terpenuhi:

- Minimal 1 foto (lebar minimal 800 px), kategori, produsen, dan titik asal kirim terisi.
- Minimal satu varian dengan harga lebih dari 0 dan berat lebih dari 0 gram.
- Harga coret, jika diisi, harus lebih besar dari harga jual.
- Pemeriksaan produk serupa (4.2) sudah diputuskan.

**Varian dan SKU.**

- Produk tanpa pilihan tetap punya satu varian bawaan, agar stok dan harga selalu tersimpan di level varian.
- Varian dibedakan oleh opsi seperti ukuran, berat, atau rasa. Kombinasi opsi dalam satu produk harus unik.
- SKU dibuat otomatis dengan pola kode kategori, nomor urut produk, dan kode varian (contoh `KOP-0042-250G`). Admin boleh menggantinya, tetapi SKU wajib unik di seluruh toko.
- Kolom barcode opsional. Jika diisi, barcode juga wajib unik.

**Perubahan harga** dicatat di log audit beserta harga lama. Pesanan yang sudah dibuat tetap memakai harga snapshot (bagian 6).

### 4.2 Deteksi produk serupa

Saat Admin menyimpan produk baru, mengubah nama produk, atau mengimpor CSV, sistem mencari produk yang mungkin sama. Jika ada kandidat, Admin wajib memilih: menambah stok ke produk lama, menjadikannya varian baru, atau menyimpan sebagai produk terpisah. Pemeriksaan ini memakai aturan dan skor tetap, tanpa AI, dan ambangnya bisa disempurnakan dari riwayat keputusan Admin.

&#91;embedded content: alur pemeriksaan produk serupa · 3 pilihan Admin\]

Skor di bawah 0,85 tidak menghentikan Admin. Skor 0,85 ke atas, atau SKU/barcode yang sama, menahan penyimpanan sampai Admin memilih salah satu dari tiga hasil.

**Langkah 1: normalisasi nama.** Huruf kecil, tanda baca dihapus, spasi dirapikan, kata umum dibuang ("khas", "asli", "original", "premium", "bone"), satuan diseragamkan ("250 gr", "250gram", "1/4 kg" menjadi "250g"), lalu kata diurutkan alfabetis agar urutan kata tidak berpengaruh. Contoh: "Kopi Bubuk Robusta ASLI Bone 250 gr" dan "Robusta Kopi Bubuk 250gram" sama-sama menjadi "250g bubuk kopi robusta".

**Langkah 2: ambil kandidat.** Sistem mengambil 10 produk teratas dari Meilisearch berdasarkan nama ternormalisasi, termasuk produk berstatus Draf, Nonaktif, dan Arsip.

**Langkah 3: hitung skor kemiripan** (0 sampai 1):

```latex
\text{skor} = 0.60 \cdot \text{nama} + 0.15 \cdot \text{kategori} + 0.15 \cdot \text{produsen} + 0.10 \cdot \text{ukuran}
```

| Komponen | Nilai |
| --- | --- |
| nama | Kemiripan trigram (koefisien Jaccard) antara dua nama ternormalisasi, 0 sampai 1 |
| kategori | 1 jika kategori sama, 0,5 jika induk kategori sama, selain itu 0 |
| produsen | 1 jika produsen sama, selain itu 0 |
| ukuran | 1 jika berat atau isi berbeda paling banyak 10%, selain itu 0 |

**Langkah 4: tentukan tingkat peringatan.**

| Kondisi | Tingkat | Perilaku sistem |
| --- | --- | --- |
| SKU atau barcode sama | Duplikat pasti | Penyimpanan ditolak sampai Admin memilih produk lama atau mengganti SKU/barcode |
| Skor 0,85 atau lebih | Kemungkinan sama | Penyimpanan ditahan; Admin wajib memilih salah satu dari tiga pilihan |
| Skor 0,65 sampai di bawah 0,85 | Mirip | Kandidat ditampilkan sebagai saran; Admin boleh langsung melanjutkan |
| Skor di bawah 0,65 | Tidak serupa | Produk tersimpan tanpa peringatan |

**Tiga pilihan Admin**

| Pilihan | Kapan dipakai | Hasil | Syarat |
| --- | --- | --- | --- |
| Tambah stok ke produk lama | Barang benar-benar sama: produsen, titik asal, dan ukuran sama | Tidak ada produk baru; jumlah yang diisi menjadi mutasi stok masuk pada varian lama (4.3) | Produsen dan titik asal sama; jika harga berbeda, Admin memilih harga mana yang berlaku |
| Jadikan varian baru | Barang sama tetapi ukuran, berat, atau rasa berbeda | Varian baru ditambahkan ke produk lama dengan stok dan harga sendiri | Produsen dan titik asal sama; kombinasi opsi belum ada |
| Simpan sebagai produk terpisah | Produsen berbeda, titik asal berbeda, atau mutu berbeda | Produk baru tersimpan | Alasan wajib dipilih: produsen berbeda, titik asal berbeda, mutu/grade berbeda, atau lainnya dengan catatan |

Keputusan disimpan per pasangan produk, sehingga pasangan yang sudah diputuskan "terpisah" tidak memunculkan peringatan lagi. Produk dari produsen berbeda sengaja dipisah karena setiap produsen perlu dipromosikan dengan namanya sendiri.

**Impor CSV.** Pratinjau impor menandai baris yang serupa dengan produk lama atau dengan baris lain di file yang sama. Baris berstatus "duplikat pasti" tidak diimpor sampai diputuskan.

**Gabungkan produk yang terlanjur dobel.** Panel menyediakan alat gabung untuk dua produk yang sudah ada:

1. Admin memilih produk utama dan produk yang digabung.
2. Varian dipindahkan ke produk utama. Varian yang sama persis digabung, dan stoknya dijumlahkan lewat mutasi stok.
3. Ulasan, wishlist, dan statistik pindah ke produk utama.
4. Alamat lama dialihkan permanen (HTTP 301) ke produk utama, sehingga tautan yang sudah dibagikan di WhatsApp tetap berfungsi.
5. Produk yang digabung menjadi Arsip. Pesanan lama tidak berubah karena memakai snapshot.

Penggabungan ditolak jika titik asal kedua produk berbeda (batasan fase 1). Setiap penggabungan tercatat di log audit.

### 4.3 Pengaturan stok

Stok disimpan per varian dan hanya berubah lewat **mutasi**: tambah atau kurangi dengan jumlah tertentu. Admin tidak pernah menimpa angka stok secara langsung. Dengan cara ini, penjualan yang terjadi saat Admin membuka form tidak hilang tertimpa, dan setiap perubahan bisa ditelusuri.

**Jenis mutasi**

| Jenis | Arah | Dibuat oleh | Alasan wajib |
| --- | --- | --- | --- |
| Stok masuk | + | Admin (penerimaan dari produsen) | Tidak; catatan opsional (misalnya nomor nota) |
| Penyesuaian keluar | − | Admin | Ya: rusak, hilang, kedaluwarsa, sampel promosi, lainnya |
| Koreksi stok opname | + atau − | Sistem, dari sesi opname | Otomatis: "selisih opname" |
| Checkout | − | Sistem saat pesanan dibuat | Otomatis, dengan kode pesanan |
| Pengembalian stok | + | Sistem saat pesanan kedaluwarsa atau dibatalkan | Otomatis, dengan kode pesanan |
| Retur diterima | + | Admin setelah barang retur diperiksa layak jual | Ya, dengan kode pesanan |

Setiap mutasi menyimpan stok sebelum, stok sesudah, jumlah, jenis, alasan, pengguna, dan referensi. Riwayat ini tidak bisa diedit atau dihapus.

**Penolakan perubahan stok.** Satu layanan `StockService::adjust()` dipakai oleh panel, impor CSV, checkout, dan pengembalian. Layanan ini menolak perubahan berikut:

| Kondisi | Respons |
| --- | --- |
| Jumlah bukan bilangan bulat lebih dari 0 | Ditolak: "Jumlah harus bilangan bulat lebih dari 0" |
| Pengurangan lebih besar dari stok saat ini | Ditolak: "Stok saat ini 12, pengurangan maksimal 12" |
| Pengurangan tanpa alasan | Ditolak sampai alasan dipilih |
| Stok masuk di atas batas wajar: lebih dari 10 kali rata-rata penjualan bulanan varian, atau lebih dari 1.000 unit sekali input (ambang diatur Super Admin) | Ditahan; Admin harus mengonfirmasi ulang dengan password. Aturan ini menangkap salah ketik seperti 1000 yang seharusnya 100 |
| Hasil stok melebihi batas maksimal sistem (999.999 unit) | Ditolak |
| Varian atau produk berstatus Arsip | Ditolak: aktifkan kembali produk terlebih dahulu |
| Titik asal produk nonaktif | Ditolak: aktifkan titik asal atau pindahkan produk ke titik lain |
| Pengguna tanpa izin kelola stok | Ditolak (403) |

Di database, pengurangan berjalan sebagai satu perintah atomik `UPDATE product_variants SET stock = stock - :qty WHERE id = :id AND stock >= :qty` di dalam transaksi bersama pencatatan mutasi. Jika tidak ada baris yang berubah, stok tidak cukup dan perubahan ditolak. Dua Admin atau dua pembeli yang bertindak bersamaan tidak bisa membuat stok negatif.

**Stok opname** (penghitungan fisik berkala):

1. Admin membuka sesi opname untuk satu titik asal. Sistem mencatat stok sistem setiap varian saat sesi dimulai.
2. Admin memasukkan hasil hitung fisik per varian, bisa lewat HP di gudang.
3. Sistem menampilkan selisih (fisik dikurangi stok sistem saat sesi dimulai). Selisih lebih dari 5% ditandai untuk diperiksa ulang.
4. Admin memposting sesi. Setiap selisih menjadi mutasi "koreksi stok opname". Penjualan selama sesi berlangsung tetap tercatat terpisah, sehingga tidak tertimpa.

**Stok menipis dan habis**

- Setiap varian punya ambang stok menipis (bawaan 5). Varian di bawah ambang tampil di dasbor, dan Admin menerima ringkasan email harian.
- Varian dengan stok 0 tetap tampil di toko berlabel "Habis" dengan tombol "Kabari saya" (wishlist). Saat stok masuk, sistem mengirim email kepada pembeli yang menunggu.
- Batas beli per pesanan (opsional, per varian) mencegah satu pembeli memborong produk terbatas atau stok flash sale.

### 4.4 Validasi jumlah beli terhadap stok

Jumlah beli diperiksa di lima titik. Empat titik pertama membantu Pembeli lebih awal. Titik kelima di server adalah pemeriksaan penentu yang tidak bisa dilewati, termasuk lewat API atau permintaan yang dimanipulasi.

| No | Titik | Aturan | Jika jumlah melebihi stok |
| --- | --- | --- | --- |
| 1 | Halaman produk | Pemilih jumlah maksimal = stok tersedia (dibatasi batas beli per pesanan) dikurangi jumlah yang sudah di keranjang | Tombol + berhenti di batas; tombol "Tambah ke keranjang" nonaktif jika sisa 0 |
| 2 | Tambah ke keranjang (server) | Jumlah di keranjang + jumlah baru tidak boleh melebihi stok tersedia | Ditolak, tidak dipotong diam-diam. Pesan: "Stok tersisa 3, di keranjang Anda sudah ada 2. Anda bisa menambah 1 lagi", dengan tombol "Tambahkan 1" |
| 3 | Halaman keranjang | Dicek ulang setiap dibuka dan saat menekan Checkout | Item ditandai merah ("Sisa 2" atau "Habis"); tombol Checkout nonaktif sampai Pembeli menyesuaikan, atau menekan "Sesuaikan ke stok tersedia" |
| 4 | Rincian biaya checkout | Dicek ulang saat menghitung `POST /checkout/quote` | Pembeli dikembalikan ke keranjang dengan item yang bermasalah ditandai |
| 5 | Pembuatan pesanan | Pemotongan stok atomik per varian di dalam satu transaksi (4.3) | Jika satu varian gagal, seluruh pesanan dibatalkan (rollback). Tidak ada pesanan sebagian. Pembeli kembali ke keranjang dengan daftar item dan stok tersisanya |

**Format galat di API v1** (HTTP 409):

```json
{
  "message": "Stok tidak mencukupi untuk sebagian item.",
  "code": "insufficient_stock",
  "items": [
    { "variant_id": 31, "requested": 5, "available": 3 }
  ]
}
```

**Kasus khusus**

- Keranjang Tamu dari browser diperiksa dengan aturan yang sama saat digabung ke akun dan saat checkout.
- Item yang kembali ke keranjang dari pesanan kedaluwarsa bisa melebihi stok terbaru. Item itu ditandai seperti titik 3.
- Stok ditampilkan ke Pembeli sebagai angka pasti jika 10 atau kurang ("Sisa 3"), dan sebagai "Stok tersedia" jika lebih.
- **Verifikasi pembayaran oleh Admin tidak pernah gagal karena stok.** Stok sudah dipotong saat pesanan dibuat, sehingga barang pesanan yang menunggu verifikasi tidak bisa dibeli orang lain. Inilah alasan utama keputusan ADR-5.

### 4.5 Kapan stok dipotong

**Keputusan: stok dipotong saat pesanan dibuat (checkout), lalu dikembalikan otomatis jika pesanan kedaluwarsa, dibatalkan, atau bukti ditolak dua kali.** Keranjang hanya memvalidasi jumlah (4.4) tanpa memotong stok. Konfirmasi Admin tidak mengubah stok sama sekali.

| Kriteria | Saat masuk keranjang | Saat checkout (dipilih) | Saat Admin konfirmasi |
| --- | --- | --- | --- |
| Risiko jual dobel (dua orang membayar barang terakhir) | Tidak ada | Tidak ada | Tinggi: jeda transfer dan verifikasi manual bisa berjam-jam |
| Stok terkunci tanpa pembayaran | Tinggi: keranjang sering ditinggal, Tamu dan bot ikut mengunci | Terbatas sampai batas bayar, dan dibatasi aturan anti-penimbunan | Tidak ada |
| Kepastian bagi Pembeli setelah transfer | Ya | Ya, barang pasti tersedia | Tidak; Pembeli yang sudah transfer bisa ditolak karena stok habis dan harus menunggu refund |
| Verifikasi Admin bisa gagal karena stok | Tidak | Tidak | Ya |
| Kompleksitas | Tinggi: perlu timer per item keranjang | Sedang: penjadwal kedaluwarsa (sudah ada di bagian 5) | Rendah, tetapi beban refund manual tinggi |

Opsi "saat Admin konfirmasi" terlihat paling sederhana, tetapi paling berbahaya untuk pembayaran manual. Pembeli bisa sudah mentransfer uang untuk barang yang ternyata habis, dan toko harus mengembalikan dana secara manual. Opsi "saat masuk keranjang" hanya cocok untuk penjualan tiket dengan timer beberapa menit.

**Aturan anti-penimbunan stok.** Kelemahan opsi checkout adalah pesanan tanpa pembayaran bisa dipakai untuk mengunci stok, baik iseng maupun disengaja oleh pesaing. Aturan berikut menutup celah itu. Semua nilainya bisa diubah Super Admin.

| Aturan | Nilai usulan |
| --- | --- |
| Maksimal pesanan berstatus Menunggu Pembayaran per akun | 3 pesanan aktif sekaligus |
| Batas waktu bayar untuk pesanan berisi item flash sale | 1 jam (batas umum tetap 24 jam) |
| Pemblokiran checkout sementara | 3 pesanan kedaluwarsa dalam 7 hari: checkout diblokir 24 jam; Admin bisa membuka blokir lebih awal |
| Batas pembuatan pesanan | Maksimal 5 pesanan per jam per akun |
| Batas beli per pesanan | Per varian, opsional (4.3) |
| Syarat akun | Email terverifikasi sebelum checkout (bagian 3) |

Pembeli yang membatalkan pesanannya sendiri langsung melepas stok, dan pembatalan ini tidak dihitung sebagai kedaluwarsa. Pembeli yang benar-benar berubah pikiran tidak ikut terkena blokir.

### 4.6 Laporan dan ekspor data

Setiap laporan bisa difilter, ditampilkan sebagai grafik dan tabel, lalu diekspor. Ekspor selalu memakai filter yang sedang aktif, sehingga file berisi persis apa yang terlihat di layar. Semua waktu laporan memakai zona WITA (Asia/Makassar), sedangkan database menyimpan waktu dalam UTC.

| Laporan | Isi | Filter utama | Akses |
| --- | --- | --- | --- |
| Penjualan | Omzet, jumlah pesanan, rata-rata nilai pesanan, per hari, minggu, atau bulan | Periode, kategori, produk, produsen, titik asal, kecamatan tujuan | Admin |
| Penjualan per produsen | Item terjual, omzet, dan daftar pesanan per Karang Taruna atau UMKM; bahan pembagian hasil dan laporan pertanggungjawaban | Periode, produsen | Admin; versi PDF siap cetak |
| Pembayaran dan rekonsiliasi | Pesanan disetujui beserta tanggal mutasi rekening, rekening tujuan, dan penyetuju; pesanan ditolak dan alasannya; rata-rata waktu verifikasi | Periode, rekening, status, penyetuju | Admin Transaksi, Super Admin |
| Refund | Daftar pengembalian dana, alasan, penyetuju, bukti | Periode, status | Admin Transaksi, Super Admin |
| Posisi stok | Stok per varian per titik asal, stok menipis, stok habis | Titik asal, kategori, produsen | Admin |
| Mutasi stok | Seluruh mutasi stok beserta jenis, alasan, dan pengguna | Periode, varian, jenis mutasi, pengguna | Admin |
| Stok opname | Hasil tiap sesi opname dan selisihnya | Periode, titik asal | Admin |
| Produk | Terlaris, tidak laku dalam 30/60/90 hari, keputusan produk serupa | Periode, kategori | Admin |
| Promosi | Pemakaian voucher, potongan yang diberikan, kinerja flash sale | Periode, voucher | Admin |
| Pengiriman | Paket per metode dan kurir, waktu dari bayar sampai kirim, paket ambil sendiri | Periode, titik asal, kurir | Admin |
| Pelanggan | Pembeli baru dan pembeli berulang (angka agregat) | Periode | Admin |
| Pencarian | Kata kunci populer dan kata kunci tanpa hasil | Periode | Admin |
| Log audit | Semua aksi pengguna panel | Periode, pengguna, jenis aksi | Super Admin |

**Aturan ekspor**

- Format: Excel (.xlsx) dan CSV untuk semua laporan; PDF untuk laporan penjualan per produsen dan rekap bulanan.
- Ekspor sampai 5.000 baris langsung diunduh. Ekspor yang lebih besar diproses di antrean, lalu tautan unduhan dikirim ke email pengguna dan tampil di menu "Ekspor saya".
- File ekspor disimpan di disk privat. Tautan unduhan bertanda tangan berlaku 24 jam, dan file dihapus otomatis setelah 7 hari.
- Rentang ekspor maksimal 12 bulan per file agar server tidak terbebani.
- **Perlindungan data pribadi:** ekspor yang memuat nama, nomor WhatsApp, atau alamat Pembeli hanya bisa dibuat oleh Admin Transaksi dan Super Admin. Untuk peran lain, kolom tersebut disamarkan (contoh 0812\*\*\*\*4567). Setiap ekspor tercatat di log audit: siapa, laporan apa, filter apa, dan berapa baris.
- Dasbor menampilkan ringkasan dari laporan yang sama, sehingga angka di dasbor dan file ekspor selalu cocok.

## 5. Alur pembayaran semi-otomatis

Setiap pesanan berjalan di atas state machine dengan tujuh status. Kode hanya mengizinkan perpindahan yang tercantum di tabel transisi; perpindahan lain ditolak, termasuk dari Super Admin.

&#91;embedded content: state machine pesanan · 7 status\]

Kotak berwarna menandai titik saat hitung mundur berhenti. Kotak bergaris putus adalah status akhir yang mengembalikan stok dan kuota voucher.

**Aturan inti**

1. Saat pesanan dibuat, sistem memotong stok dan mencatat batas bayar. Usulan default 24 jam, bisa diatur Super Admin.
2. Upload bukti sebelum batas waktu menghentikan hitung mundur. Pembeli tidak dirugikan oleh Admin yang lambat memverifikasi.
3. Jika batas waktu lewat tanpa bukti, pesanan menjadi Kedaluwarsa. Stok dikembalikan dan item kembali ke keranjang dengan harga terkini.
4. Jika Admin menolak bukti, Pembeli mendapat kesempatan upload ulang dengan batas baru (usulan: 6 jam). Penolakan kedua membatalkan pesanan.
5. Upload bukti setelah batas waktu ditolak sistem, termasuk lewat API.

**Mengapa stok dipotong saat checkout, bukan saat pembayaran disetujui:** jika stok baru dipotong saat verifikasi, dua pembeli bisa mentransfer untuk barang terakhir yang sama. Salah satunya harus dikembalikan uangnya secara manual. Pemotongan di awal ditambah pengembalian otomatis saat kedaluwarsa menghilangkan kasus itu.

**Kode unik nominal transfer.** Sistem menambahkan angka acak 1 sampai 999 rupiah ke total tagihan (contoh Rp 154.000 menjadi Rp 154.237). Admin bisa mencocokkan mutasi rekening dengan pesanan hanya dari nominalnya. Kode unik dicatat terpisah di pesanan dan tampil sebagai baris tersendiri di rincian biaya.

**Tabel transisi status**

| Dari | Ke | Pemicu | Efek samping |
| --- | --- | --- | --- |
| (baru) | Menunggu Pembayaran | Pembeli membuat pesanan | Stok dipotong, batas bayar dicatat, email tagihan |
| Menunggu Pembayaran | Menunggu Verifikasi | Pembeli upload bukti sebelum batas | Hitung mundur berhenti, Admin mendapat notifikasi |
| Menunggu Pembayaran | Kedaluwarsa | Penjadwal: batas bayar lewat | Stok dikembalikan, item kembali ke keranjang, voucher dikembalikan |
| Menunggu Pembayaran | Dibatalkan | Pembeli membatalkan | Stok dan voucher dikembalikan |
| Menunggu Verifikasi | Diproses | Admin menyetujui setelah dana terlihat di mutasi rekening (persetujuan kedua untuk nominal besar) | Email konfirmasi, pesanan masuk antrean kirim |
| Menunggu Verifikasi | Menunggu Pembayaran | Admin menolak bukti (penolakan pertama) | Batas bayar baru, email berisi alasan |
| Menunggu Verifikasi | Dibatalkan | Admin menolak bukti (penolakan kedua) | Stok dan voucher dikembalikan |
| Diproses | Dikirim | Semua paket sudah punya resi atau ditandai siap diambil | Email dengan tautan lacak |
| Diproses | Dibatalkan | Admin membatalkan dengan alasan | Stok dikembalikan, pengembalian dana dicatat manual |
| Dikirim | Selesai | Pembeli menekan "Pesanan diterima", atau otomatis 7 hari setelah paket terakhir dikirim | Ulasan dibuka |

**Kasus tepi yang wajib ditangani**

| Kasus | Penanganan |
| --- | --- |
| Pembeli menekan "Buat pesanan" dua kali | Kunci idempotensi per percobaan checkout; permintaan kedua mengembalikan pesanan yang sama |
| Dua pembeli membeli stok terakhir bersamaan | Pemotongan stok dengan `UPDATE ... WHERE stok >= jumlah` di dalam transaksi; pembeli kedua mendapat pesan stok habis |
| Bukti yang sama dipakai di dua pesanan | Hash SHA-256 file dibandingkan; Admin melihat peringatan "bukti ini pernah dipakai di pesanan X" |
| Nominal di bukti berbeda dari tagihan | Admin menolak dengan alasan template "nominal tidak sesuai" |
| Harga berubah saat item kembali ke keranjang | Keranjang memakai harga baru dan menampilkan pemberitahuan perubahan |
| Voucher sudah berakhir saat pesanan kedaluwarsa | Kuota voucher dikembalikan, tetapi voucher tidak bisa dipakai lagi jika periodenya habis |
| Penjadwal mati | Pemeriksaan kedaluwarsa juga berjalan saat halaman pesanan dibuka, sehingga status tetap benar |
| Pembeli transfer tetapi lupa upload | Pengingat email 2 jam sebelum batas bayar |
| Satu titik asal tidak bisa mengirim setelah pesanan dibayar | Fase 1: Admin membatalkan seluruh pesanan dan mencatat pengembalian dana. Pembatalan sebagian per paket ditunda ke fase 2 agar aturan stok dan voucher tetap sederhana |
| Paket dikirim di hari berbeda | Status pesanan tetap Diproses sampai paket terakhir punya resi; Pembeli melihat status tiap paket di halaman detail |

## 6. Mesin harga: diskon, voucher, dan ongkir

Server selalu menghitung ulang total dari data di database. Angka yang dikirim browser atau aplikasi mobile tidak pernah dipakai sebagai harga. Satu kelas `PriceCalculator` dipakai oleh halaman checkout web, API mobile, dan pembuatan pesanan, sehingga ketiganya tidak mungkin berbeda hasil.

**Urutan perhitungan**

```latex
T = (S - D_v) + (O - D_o) + B + K
```

| Simbol | Arti | Aturan |
| --- | --- | --- |
| S | Subtotal = jumlah (harga aktif x kuantitas) | Harga aktif = harga flash sale jika berlaku, lalu harga diskon produk, lalu harga normal |
| D\_v | Potongan voucher belanja | Persen dengan batas maksimal, atau nominal tetap; tidak boleh melebihi S |
| O | Ongkos kirim | Jumlah ongkir semua paket; tiap paket dari API ongkir, tarif kurir lokal, atau 0 untuk ambil sendiri |
| D\_o | Potongan voucher ongkir | Berlaku untuk total ongkir semua paket; tidak boleh melebihi O |
| B | Biaya layanan | Default 0, bisa diaktifkan Super Admin |
| K | Kode unik transfer | Acak 1 sampai 999 rupiah (bagian 5) |

**Aturan voucher**

- Maksimal satu voucher belanja dan satu voucher ongkir per pesanan. Diskon produk dan flash sale tidak dihitung sebagai voucher, jadi tetap bisa digabung.
- Syarat yang bisa diatur: minimal belanja, periode berlaku, kuota total, kuota per pengguna, kategori atau produk tertentu, khusus pembeli baru.
- Diskon persen dibulatkan ke bawah ke rupiah penuh.
- Kuota voucher dipesan saat pesanan dibuat dan dikembalikan saat pesanan kedaluwarsa atau dibatalkan.
- Harga, diskon, dan ongkir disalin ke pesanan (snapshot). Perubahan harga setelahnya tidak mengubah pesanan lama.

**Ongkos kirim**

- Penyedia: [RajaOngkir API V2 (Komerce)](https://rajaongkir.com/docs/shipping-cost/getting_started/about). API ini mendukung pencarian tujuan langsung dengan mengetik nama kota dan menghitung ongkir berdasarkan ID kelurahan, cocok dengan kotak pencarian alamat di checkout. API key disimpan di server, tidak pernah dikirim ke browser.
- **Beberapa titik asal.** Setiap produk terikat ke satu titik asal kirim (bawaannya mengikuti titik asal produsen). Saat checkout, item dikelompokkan per titik asal, dan setiap kelompok menjadi satu paket dengan ongkir sendiri. Ongkir total adalah jumlah ongkir semua paket.
- Berat kirim per paket = jumlah (berat varian x kuantitas) di paket itu + berat kemasan yang diatur Admin. Berat volumetrik ditunda ke fase 2, kecuali klien menjual barang besar dan ringan.
- Hasil ongkir disimpan di cache selama 12 jam per kombinasi titik asal, tujuan, berat (dibulatkan ke 1 kg), dan kurir. Cache ini menghemat kuota API.
- Jika API ongkir gagal, checkout tetap menawarkan kurir lokal dan ambil sendiri, ditambah pesan "ongkir ekspedisi sedang tidak tersedia".
- Batasan fase 1: satu produk hanya distok di satu titik asal. Jika produk yang sama disimpan di beberapa titik, stok per titik ditambahkan pada fase 2 (lihat pertanyaan terbuka).

**Kurir lokal dan ambil sendiri.** Banyak pembeli produk Karang Taruna dan UMKM kemungkinan tinggal di Kabupaten Bone sendiri. Tarif kurir lokal ditetapkan Admin per pasangan titik asal dan kecamatan tujuan. Opsi ambil sendiri hanya muncul untuk paket yang titik asalnya melayani pengambilan, lengkap dengan alamat dan jam layanan titik itu.

## 7. Pencarian instan dan rekomendasi berbasis aturan

Pencarian memakai Laravel Scout dengan mesin Meilisearch di VPS. Meilisearch mendukung pencarian awalan kata dan toleransi salah ketik, sehingga hasil muncul sejak huruf pertama. Rekomendasi dihitung dari log pencarian, klik, dan penjualan dengan rumus tetap tanpa model AI. Semua bobot disimpan di konfigurasi agar bisa disempurnakan tanpa mengubah kode.

**7.1 Perilaku kotak pencarian**

| Isi kotak | Yang ditampilkan |
| --- | --- |
| Kosong (baru diklik) | 5 riwayat pencarian pengguna + 5 pencarian populer minggu ini |
| 1 huruf | Kategori dan kata kunci populer yang berawalan huruf itu |
| 2 huruf atau lebih | Hingga 6 produk (foto, nama, harga), 3 kategori, 2 produsen, saran kata kunci, dan baris "Cari semua untuk ..." |
| Tanpa hasil | Saran ejaan terdekat dan blok "Jelajahi juga" |

- Permintaan dikirim 150 ms setelah ketikan terakhir. Permintaan lama dibatalkan agar hasil tidak tertukar urutannya.
- Bagian yang cocok ditebalkan. Navigasi panah atas/bawah dan Enter di desktop.
- Sinonim lokal diatur Admin, misalnya "sutra" dan "sutera", "kopi" dan "coffee".
- Batas 60 permintaan per menit per IP.
- Jika Meilisearch mati, sistem beralih ke pencarian `FULLTEXT` MySQL. Hasilnya lebih lambat tetapi toko tetap berfungsi.

**7.2 Data yang dicatat**

Sistem tidak mencatat setiap ketikan, karena "k", "ko", "kop" akan mengotori statistik. Satu pencarian dicatat saat pengguna menekan Enter, memilih saran, atau berhenti mengetik selama 1 detik dengan hasil tampil. Catatan berisi kata kunci yang dinormalkan (huruf kecil, spasi rapi), jumlah hasil, produk yang diklik, ID pengguna atau ID sesi untuk Tamu, dan waktu.

**7.3 Empat aturan rekomendasi**

Setiap pencarian dikaitkan ke kategori: kategori produk yang diklik, atau jika tidak ada klik, kategori dari 3 hasil teratas (masing-masing sepertiga). Pencarian lama berbobot lebih kecil dengan peluruhan mingguan:

```latex
w = 0.5^{\text{umur (hari)} / 7}
```

| Blok | Aturan | Tempat tampil |
| --- | --- | --- |
| Kategori paling sering dicari | Jumlah bobot w per kategori dalam 30 hari terakhir, ambil 8 tertinggi | Landing page, halaman kategori |
| Jelajahi juga (kategori jarang dicari) | Kategori di 30% terbawah yang punya stok, 4 kategori diacak per hari | Landing page, hasil pencarian kosong |
| Terkait pencarian Anda | Skor produk terhadap 10 pencarian terakhir pengguna (rumus di bawah), 12 teratas | Landing page setelah login, keranjang, detail produk |
| Permata tersembunyi (produk jarang dicari) | Produk aktif dan berstok dengan tampilan paling sedikit dalam 30 hari, rating minimal 3,5 jika sudah punya ulasan, diacak per hari | Landing page, bawah hasil pencarian |

Skor produk untuk blok "Terkait pencarian Anda":

```latex
\text{skor}(p) = 3 \cdot \text{kategori} + 2 \cdot \text{tag} + 1 \cdot \text{produsen} + 0.5 \cdot \ln(1 + \text{terjual}_{30})
```

Komponen kategori, tag, dan produsen bernilai kecocokan berbobot w dengan pencarian pengguna. Produk yang sudah dibeli dalam 30 hari terakhir tidak ditampilkan lagi. Tamu tanpa riwayat mendapat produk terlaris.

**Mengapa dua blok "jarang dicari" penting untuk proyek ini:** tanpa blok itu, produk yang sudah populer terus mendapat tampilan dan produk UMKM kecil tidak pernah terlihat. Kedua blok ini memberi giliran tampil yang adil, sesuai tujuan platform sebagai sarana promosi.

**Waktu perhitungan:** statistik kategori dan produk dihitung ulang setiap malam lewat penjadwal dan disimpan di cache. Skor pribadi dihitung saat diminta dan disimpan di cache 15 menit per pengguna. Fase 2 bisa menambah blok "Sering dibeli bersama" dari hitungan pasangan produk dalam pesanan yang sama; ini tetap aturan statistik, bukan AI.

## 8. Arsitektur sistem

Sistem berupa **monolit modular**: satu aplikasi Laravel dengan dua pintu masuk, yaitu halaman Inertia untuk web dan API v1 untuk mobile. Keduanya memanggil kelas Action yang sama, sehingga aturan bisnis ditulis sekali. Mulailah dari [React starter kit resmi Laravel 13](https://laravel.com/docs/13.x/starter-kits), yang sudah berisi Inertia 3, React 19, Tailwind 4, shadcn/ui, Fortify dengan 2FA, dan dukungan SSR.

&#91;embedded content: arsitektur VPS · 3 pintu masuk, 1 lapisan aturan bisnis\]

Ketiga pintu masuk memanggil lapisan Action yang sama. Worker mengerjakan tugas di latar belakang dan memanggil layanan luar (ongkir dan email), sehingga halaman tidak menunggu respons pihak ketiga.

**Komponen di VPS**

| Komponen | Peran |
| --- | --- |
| Nginx | TLS, file statis, meneruskan permintaan ke PHP-FPM |
| Laravel 13 (PHP 8.4, PHP-FPM) | Routing, controller, Action, otorisasi, API |
| Server SSR Inertia (Node.js) | Merender halaman React di server untuk SEO dan preview tautan; dijaga Supervisor |
| MySQL 8.4 | Data utama, hanya bisa diakses dari localhost |
| Redis | Cache, sesi, antrean, kunci atomik |
| Meilisearch | Indeks pencarian produk |
| Laravel Horizon (queue worker) | Email, konversi gambar ke WebP, sinkron indeks pencarian |
| Scheduler (cron tiap menit) | Kedaluwarsa pesanan, pengingat bayar, statistik rekomendasi malam hari, backup |

**Struktur kode per domain**

```
app/
  Domain/
    Catalog/      Produk, varian, kategori, produsen
    Search/       Indeks, saran, log pencarian
    Recommendation/
    Cart/
    Checkout/     PriceCalculator, CreateOrder
    Order/        State machine, transisi status
    Payment/      Bukti transfer, verifikasi
    Promotion/    Voucher, diskon, flash sale, banner
    Shipping/     Klien ongkir, kurir lokal
    Content/      Berita, dokumentasi, galeri
    Identity/     Pengguna, peran, undangan Admin
  Http/
    Controllers/Web/    Mengembalikan halaman Inertia
    Controllers/Api/V1/ Mengembalikan JSON
```

Controller hanya memvalidasi masukan, memanggil satu Action, lalu mengembalikan respons. Logika bisnis tidak boleh berada di controller atau komponen React.

**Keputusan arsitektur (ADR ringkas)**

| No | Keputusan | Alasan | Konsekuensi |
| --- | --- | --- | --- |
| ADR-1 | Monolit modular, bukan microservices | Satu tim kecil, satu database, satu deploy | Batas domain dijaga lewat struktur folder dan review kode |
| ADR-2 | Inertia + React dengan SSR | Tampilan setara SPA, tetap satu aplikasi, halaman produk terindeks mesin pencari | Perlu proses Node.js di server; jika SSR mati, halaman tetap jalan dengan rendering di browser |
| ADR-3 | API v1 + Sanctum sejak fase 1 | Aplikasi mobile tinggal memakai API yang sudah teruji | Setiap fitur Pembeli harus punya endpoint dan tes API |
| ADR-4 | Meilisearch dengan cadangan MySQL FULLTEXT | Saran per huruf butuh respons di bawah 100 ms dan toleransi salah ketik | Satu layanan tambahan untuk dijaga dan dicadangkan |
| ADR-5 | Stok dipotong saat checkout | Mencegah dua pembayaran untuk barang terakhir | Butuh penjadwal kedaluwarsa yang andal |
| ADR-6 | Uang disimpan sebagai BIGINT rupiah | Rupiah tidak punya pecahan, bilangan bulat bebas galat pembulatan | Diskon persen harus dibulatkan eksplisit |
| ADR-7 | Gambar produk di object storage S3-compatible | Disk VPS tidak cepat penuh, migrasi server lebih mudah | Biaya bulanan kecil tambahan; bisa mulai di disk lokal |
| ADR-8 | Satu produk terikat ke satu titik asal; pesanan dipecah per titik menjadi paket (order\_shipments) | Klien mengirim dari beberapa titik di Bone; ongkir harus akurat per lokasi | Resi dan status kirim per paket; stok multi-gudang untuk produk yang sama belum didukung di fase 1 |
| ADR-9 | Bahasa Indonesia di URL utama, bahasa lain di prefiks URL (misalnya /en/...) | Setiap bahasa punya URL sendiri sehingga mesin pencari bisa mengindeksnya, dan SSR merender bahasa yang benar sejak awal | Perlu tag hreflang dan peta situs per bahasa |
| ADR-10 | Tema disimpan di cookie dan dibaca saat SSR | Halaman langsung tampil dalam tema pilihan pengguna tanpa kedipan putih saat dimuat | Setiap warna wajib lewat token; warna yang ditulis langsung di komponen dilarang |

**Paket utama**

- Backend: Laravel Fortify (login, verifikasi email, 2FA), Sanctum (token mobile), Scout (pencarian), Horizon (antrean), spatie/laravel-permission (peran), spatie/laravel-activitylog (log audit), spatie/laravel-csp (header CSP), dedoc/scramble (dokumentasi OpenAPI otomatis), Pest (pengujian).
- Frontend: React 19 + TypeScript, Tailwind CSS 4, shadcn/ui, Motion (animasi komponen dan transisi halaman), GSAP ScrollTrigger (khusus landing page), Lottie (ilustrasi animasi), Lucide (ikon), Swiper (carousel), Recharts (grafik panel).

## 9. Rancangan basis data MySQL

Skema terdiri dari sekitar 30 tabel dalam 7 kelompok. Semua tabel memakai InnoDB dan `utf8mb4_unicode_ci`, nominal uang disimpan sebagai `BIGINT UNSIGNED` dalam rupiah, dan setiap skema dibuat lewat migration Laravel sehingga riwayat perubahannya tercatat di Git.

| Kelompok | Tabel | Kolom penting |
| --- | --- | --- |
| Identitas | `users` | name, email, phone (unik), password, email\_verified\_at, status (aktif/nonaktif), locale, theme, checkout\_blocked\_until, two\_factor\_secret, last\_login\_at |
| Identitas | `roles`, `permissions`, `model_has_roles` | Dari spatie/laravel-permission |
| Identitas | `admin_invitations` | email, role, token\_hash, expires\_at, accepted\_at, invited\_by |
| Identitas | `addresses` | user\_id, label, recipient, phone, destination\_id (ID kelurahan penyedia ongkir), full\_address, postal\_code, is\_default |
| Katalog | `producers` | name, type (karang\_taruna/umkm), village, district, story, photo, contact, default\_origin\_id, user\_id (kosong sampai produsen dinaikkan menjadi akun penjual) |
| Katalog | origins | name, address, district, destination\_id (ID lokasi penyedia ongkir), contact, couriers (JSON), pickup\_enabled, pickup\_hours, is\_active |
| Katalog | local\_courier\_rates | origin\_id, destination\_district, fee |
| Katalog | `categories` | parent\_id, name (JSON terjemahan), slug, icon, sort\_order |
| Katalog | `products` | producer\_id, category\_id, origin\_id, name, name\_normalized, slug, description, status (draf/aktif/nonaktif/arsip), is\_featured, deleted\_at; name dan description berupa JSON terjemahan |
| Katalog | `product_variants` | product\_id, sku, barcode, options (JSON), price, compare\_price, stock, weight\_gram, low\_stock\_threshold, max\_per\_order |
| Katalog | `product_images`, `tags`, `product_tag` | Urutan foto, sampul, original\_path (privat), variants (JSON jalur WebP per lebar), width, height, dominant\_color, status proses; tag untuk rekomendasi |
| Katalog | `stock_movements` | variant\_id, type (masuk/keluar/opname/checkout/restore/retur), qty\_change, stock\_before, stock\_after, reason, reference, user\_id, note; tidak bisa diubah atau dihapus |
| Katalog | stock\_opnames, stock\_opname\_items | origin\_id, status, started\_by, posted\_at; variant\_id, system\_qty\_at\_start, counted\_qty, difference |
| Katalog | product\_similarity\_decisions | product\_id, candidate\_product\_id, score, decision (tambah\_stok/varian\_baru/terpisah), reason, user\_id |
| Katalog | product\_redirects | old\_slug, product\_id; untuk tautan produk yang sudah digabung (HTTP 301) |
| Belanja | `carts`, `cart_items` | user\_id, variant\_id, qty |
| Belanja | `wishlists` | user\_id, product\_id, notify\_restock |
| Pesanan | `orders` | code (contoh BN-261102-7K3Q9), user\_id, status, subtotal, voucher\_discount, shipping\_cost, shipping\_discount, service\_fee, unique\_code, grand\_total, payment\_deadline\_at, bank\_account\_id, address\_snapshot (JSON), rejection\_count, idempotency\_key |
| Pesanan | `order_items` | order\_id, variant\_id, product\_name, variant\_name, unit\_price, qty, weight\_gram (semua salinan) |
| Pesanan | order\_shipments | order\_id, origin\_id, method (ekspedisi/lokal/ambil), courier, service, weight\_gram, cost, shipping\_discount, tracking\_number, status, shipped\_at, pickup\_code\_hash, delivery\_photo, receiver\_name; order\_items.shipment\_id menunjuk ke paket ini |
| Pesanan | order\_refunds | order\_id, amount, bank\_name, account\_number, account\_holder, reason, status, requested\_by, approved\_by (wajib orang berbeda), proof\_path, refunded\_at |
| Sistem | report\_exports | user\_id, report, filters (JSON), format, row\_count, contains\_personal\_data, status, file\_path (privat), expires\_at |
| Pesanan | `order_status_histories` | order\_id, from\_status, to\_status, actor\_id, note |
| Pembayaran | `bank_accounts` | bank\_name, account\_number, account\_holder, is\_active, active\_from (masa tunggu 24 jam) |
| Pembayaran | `payment_proofs` | order\_id, file\_path (disk privat), file\_sha256, sender\_bank, sender\_name, transferred\_at, status, reviewed\_by, reviewed\_at, reject\_reason, mutation\_datetime, second\_reviewed\_by, original\_path (terenkripsi) |
| Promosi | `vouchers` | code, type (belanja/ongkir), discount\_type (persen/nominal), value, max\_discount, min\_spend, quota, per\_user\_limit, starts\_at, ends\_at, scope (JSON) |
| Promosi | `voucher_usages` | voucher\_id, user\_id, order\_id, status (dipesan/dipakai/dikembalikan) |
| Promosi | `flash_sales`, `flash_sale_items` | Periode; variant\_id, sale\_price, quota, sold |
| Promosi | `banners` | image\_desktop, image\_mobile, link, position, starts\_at, ends\_at, sort\_order |
| Konten | `posts`, `galleries`, `pages` | Berita dan dokumentasi kegiatan, galeri foto, halaman statis; judul dan isi berupa JSON terjemahan |
| Konten | `reviews` | order\_item\_id, user\_id, rating, body, photos, is\_hidden, reply |
| Analitik | `search_logs` | user\_id atau session\_id, query, results\_count, clicked\_product\_id, created\_at |
| Analitik | `product_daily_stats`, `category_daily_stats` | views, search\_hits, sold per hari, sumber rekomendasi |
| Sistem | `settings`, `activity_log`, `notifications`, `jobs`, `failed_jobs` | Pengaturan, log audit, notifikasi, antrean |

**Aturan integritas**

- Pesanan menyimpan salinan nama, harga, berat, dan alamat. Mengedit atau menghapus produk tidak mengubah pesanan lama.
- Produk dihapus secara lunak (`deleted_at`). Produk yang pernah dipesan tidak bisa dihapus permanen.
- Indeks wajib: `orders(user_id, status)`, `orders(status, payment_deadline_at)` untuk penjadwal kedaluwarsa, `payment_proofs(file_sha256)`, `search_logs(created_at)`, unik `orders(code)` dan `orders(idempotency_key)`.
- `search_logs` diringkas ke tabel statistik harian, lalu baris mentah yang berumur lebih dari 180 hari dihapus agar tabel tidak membengkak.

Kolom terjemahan memakai satu kolom JSON per field (paket spatie/laravel-translatable), misalnya `{"id": "Kopi Robusta Bone", "en": "Bone Robusta Coffee"}`. Jika terjemahan kosong, sistem menampilkan teks bahasa Indonesia. Pola ini tidak menambah tabel ketika bahasa baru ditambahkan.

## 10. Desain API v1 untuk aplikasi mobile

API v1 mencakup semua fitur Pembeli di fase 1. Fitur Admin tetap hanya di web sampai ada kebutuhan aplikasi admin. API memakai token Sanctum dan berbagi Action dengan halaman web, sehingga tes API sekaligus menguji aturan bisnis.

**Konvensi**

- Prefiks `/api/v1`, format JSON, nama field `snake_case`, waktu ISO 8601 dengan zona waktu.
- Autentikasi: header `Authorization: Bearer <token>`. Token per perangkat, dicabut saat logout atau ganti password.
- Paginasi berbasis kursor untuk daftar produk dan pesanan.
- Galat dalam satu bentuk: `{ "message": ..., "code": ..., "errors": { field: [...] } }`, dengan kode HTTP yang tepat (401, 403, 404, 409, 422, 429).
- `POST /orders` wajib membawa header `Idempotency-Key`, karena jaringan seluler sering mengirim ulang permintaan.
- Dokumentasi OpenAPI dibuat otomatis oleh Scramble dari kode, sehingga dokumen tidak pernah tertinggal dari implementasi.

**Endpoint**

| Kelompok | Metode dan path | Keterangan |
| --- | --- | --- |
| Auth | `POST /auth/register`, `POST /auth/login`, `POST /auth/logout` | Login mengembalikan token |
| Auth | `POST /auth/email/resend`, `POST /auth/password/forgot` | Verifikasi tetap lewat tautan email |
| Profil | `GET/PATCH /me`, `DELETE /me` | Termasuk preferensi locale dan theme; hapus akun menganonimkan data |
| Alamat | `GET/POST /addresses`, `PATCH/DELETE /addresses/{id}` |  |
| Wilayah | `GET /shipping/destinations?q=` | Proksi ke penyedia ongkir; API key tetap di server |
| Katalog | `GET /categories`, `GET /products`, `GET /products/{slug}` | Filter dan urutan lewat query string |
| Katalog | `GET /producers/{slug}` | Profil produsen dan produknya |
| Pencarian | `GET /search/suggest?q=`, `GET /search?q=` | Saran instan dan hasil lengkap |
| Rekomendasi | `GET /recommendations/home`, `GET /recommendations/product/{slug}` | Empat blok di bagian 7 |
| Konten | `GET /banners?position=`, `GET /posts`, `GET /posts/{slug}` |  |
| Keranjang | `GET /cart`, `POST /cart/items`, `PATCH/DELETE /cart/items/{id}` |  |
| Wishlist | `GET /wishlist`, `POST/DELETE /wishlist/{product_id}` |  |
| Checkout | `POST /checkout/quote` | Mengembalikan rincian biaya (bagian 6) tanpa membuat pesanan |
| Checkout | `POST /checkout/shipping-options` | Paket per titik asal, beserta pilihan kurir dan tarif tiap paket |
| Pesanan | `POST /orders`, `GET /orders`, `GET /orders/{code}` |  |
| Pesanan | `POST /orders/{code}/payment-proof` | Multipart, maks 5 MB |
| Pesanan | `POST /orders/{code}/cancel`, `POST /orders/{code}/complete` | Sesuai tabel transisi bagian 5 |
| Ulasan | `POST /order-items/{id}/review` | Hanya untuk pesanan Selesai |
| Notifikasi | `GET /notifications`, `POST /notifications/read` | Fase 3: pendaftaran token push |

**Batas laju:** 60 permintaan per menit per token untuk endpoint umum, 5 per menit untuk login, dan 10 per jam untuk upload bukti.

**Bahasa di API:** aplikasi mobile mengirim header `Accept-Language` (misalnya `id` atau `en`). API mengembalikan nama produk, kategori, konten, dan pesan galat dalam bahasa itu, dengan bahasa Indonesia sebagai cadangan. Respons pesanan memuat daftar `shipments`, satu objek per paket.

## 11. Keamanan dan kepatuhan UU PDP

Risiko terbesar platform ini ada pada tiga titik: akun Admin yang dibobol, bukti transfer palsu, dan kebocoran data pribadi Pembeli. Kontrol di bawah disusun mengikuti tiga titik itu dan daftar OWASP Top 10.

| Area | Kontrol |
| --- | --- |
| Autentikasi | Hash password Argon2id atau bcrypt, minimal 8 karakter dan ditolak jika ada di daftar password bocor; login dibatasi 5 percobaan per menit per email dan IP; sesi diperbarui setelah login |
| Akun panel | 2FA TOTP wajib untuk Admin dan Super Admin; undangan bertanda tangan 48 jam; sesi panel berakhir setelah 2 jam tidak aktif; konfirmasi password sebelum aksi sensitif (setujui pembayaran, ubah rekening) |
| Otorisasi | Policy Laravel untuk setiap model; Pembeli hanya bisa membuka pesanan miliknya (pesanan orang lain menghasilkan 404, bukan 403); kode pesanan acak, bukan angka berurutan |
| Masukan dan keluaran | Form Request untuk semua masukan; Eloquent dengan parameter terikat; React meng-escape teks secara bawaan; `dangerouslySetInnerHTML` dilarang kecuali untuk isi berita yang sudah disaring HTMLPurifier |
| Bukti transfer | Hanya JPG, PNG, WebP, atau PDF, maksimal 5 MB, tipe diperiksa dari isi file; salinan tampilan di-encode ulang (menghapus EXIF dan muatan tersembunyi), file asli disimpan terenkripsi untuk sengketa; nama file acak; disimpan di disk privat dan dibuka lewat URL bertanda tangan yang berlaku 10 menit, hanya untuk pemilik dan Admin; hash SHA-256 untuk deteksi bukti ganda |
| Integritas transaksi | Total dihitung ulang di server; pembuatan pesanan dan pemotongan stok dalam satu transaksi database; kunci idempotensi; transisi status dijaga state machine; setiap persetujuan pembayaran tercatat siapa dan kapan |
| Header HTTP | HTTPS wajib dengan HSTS; Content Security Policy; `X-Frame-Options: DENY`; cookie `Secure`, `HttpOnly`, `SameSite=Lax` |
| Server | Firewall hanya membuka 80, 443, dan SSH dari IP tertentu; login SSH hanya dengan kunci; fail2ban; pembaruan keamanan otomatis; MySQL, Redis, dan Meilisearch hanya mendengar di localhost, Meilisearch dengan master key |
| Rahasia | API key, kredensial SMTP, dan `APP_KEY` hanya di `.env` server, tidak pernah di Git |
| Dependensi | `composer audit` dan `npm audit` dijalankan di setiap build CI; build gagal untuk kerentanan tingkat tinggi |
| Log audit | Semua aksi Admin dan Super Admin tercatat dengan nilai lama dan baru; tidak bisa dihapus dari antarmuka |
| Backup | Database harian terenkripsi ke lokasi di luar VPS; uji pemulihan sebulan sekali |

**Kepatuhan UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi**

- Kebijakan privasi dalam bahasa Indonesia yang jelas, dan persetujuan eksplisit saat registrasi.
- Data minimal: nama, email, nomor WhatsApp, dan alamat kirim. Tidak meminta NIK atau tanggal lahir.
- Pembeli bisa mengunduh datanya dan menghapus akun. Penghapusan menganonimkan identitas, tetapi pesanan tetap disimpan sebagai catatan keuangan.
- Log pencarian Tamu memakai ID sesi acak, bukan alamat IP.
- Server di pusat data Indonesia.
- Prosedur insiden tertulis: siapa yang dihubungi, cara menghentikan kebocoran, dan pemberitahuan tertulis kepada pemilik data paling lambat 3 x 24 jam sesuai UU PDP.

**Pemeriksaan sebelum rilis:** pindai otomatis dengan OWASP ZAP mode baseline, lalu uji manual untuk IDOR (akses pesanan orang lain), unggah file berbahaya, dan manipulasi harga lewat API.

### 11.1 Penguatan keamanan transaksi dan belanja

Celah terbesar pembayaran manual: **gambar bukti transfer bisa dipalsukan dengan mudah.** Karena itu, pembayaran hanya sah jika dana terlihat di mutasi rekening tujuan. Gambar bukti hanya alat bantu pencocokan. Tabel di bawah memetakan celah lain beserta perbaikannya.

| No | Celah | Perbaikan | Fase |
| --- | --- | --- | --- |
| T1 | Bukti transfer palsu atau hasil edit | Layar verifikasi punya daftar periksa wajib: "nominal tepat termasuk kode unik sudah masuk di mutasi rekening tujuan", ditambah isian tanggal dan jam mutasi. Tombol Setujui baru aktif setelah daftar periksa terisi | 1 |
| T2 | Pencocokan mutasi manual lambat dan rawan salah | Admin mengunggah file mutasi (CSV/Excel) dari internet banking. Sistem mencocokkan otomatis berdasarkan nominal unik dan tanggal, lalu Admin tinggal mengonfirmasi pasangan yang cocok. Tidak perlu API bank | 2 |
| T3 | Admin keliru atau curang pada pesanan bernilai besar | Pesanan di atas Rp 5.000.000 (bisa diatur) butuh persetujuan kedua dari Admin lain atau Super Admin | 1 |
| T4 | Rekening tujuan diganti oleh akun yang dibobol | Hanya Super Admin yang bisa mengubah rekening, dengan konfirmasi password dan kode 2FA. Semua Super Admin menerima email pemberitahuan. Rekening baru baru muncul di checkout setelah masa tunggu 24 jam. Pesanan lama tetap memakai rekening snapshot | 1 |
| T5 | Penipu mengaku sebagai toko lewat WhatsApp dan meminta transfer ke rekening lain | Nomor rekening hanya tampil di halaman pesanan dan email resmi. Halaman pembayaran menampilkan peringatan tetap bahwa toko tidak pernah meminta transfer ke rekening lain. Ada halaman publik "Rekening resmi". Domain email memakai SPF, DKIM, dan DMARC agar email toko sulit dipalsukan | 1 |
| T6 | Barang ambil sendiri diambil orang lain | Pembeli mendapat kode ambil 6 digit dan QR di halaman pesanan. Petugas titik asal memasukkan kode itu untuk menandai paket "diambil". Kode disimpan sebagai hash | 1 |
| T7 | Sengketa "barang tidak sampai" pada kurir lokal | Kurir lokal atau Admin mengunggah foto serah terima dan nama penerima sebelum paket ditandai terkirim | 1 |
| T8 | Pengembalian dana ke rekening yang salah atau fiktif | Refund hanya ke rekening atas nama Pembeli atau nama pengirim pada bukti transfer, butuh persetujuan dua orang, dan bukti transfer refund wajib diunggah. Semua refund tercatat di tabel tersendiri | 1 |
| T9 | Akun Pembeli diambil alih | Email pemberitahuan untuk login dari perangkat baru, ganti password, dan ganti email. Ganti email wajib dikonfirmasi dari email lama dan baru. Pembeli bisa mengeluarkan semua sesi lain. 2FA opsional untuk Pembeli | 1 |
| T10 | Alamat kirim diubah setelah pembayaran | Alamat tersimpan sebagai snapshot. Perubahan hanya bisa dilakukan Admin atas permintaan Pembeli, dan tercatat di log audit | 1 |
| T11 | Bot mendaftar massal atau menebak password | Cloudflare Turnstile pada registrasi, lupa password, dan login setelah 3 kali gagal, ditambah batas laju yang sudah ada | 1 |
| T12 | Voucher pembeli baru dipakai berulang dengan akun ganda | Satu nomor WhatsApp hanya untuk satu akun. Voucher pembeli baru dibatasi per nomor WhatsApp dan per alamat kirim. Verifikasi OTP WhatsApp menyusul di fase 2 | 1 |
| T13 | Pola kecurangan tidak terlihat | Panel "Sinyal risiko" per Pembeli menghitung pesanan kedaluwarsa, bukti ditolak, bukti ganda, serta akun yang berbagi nomor atau alamat. Panel ini berbasis aturan, bukan AI. Admin bisa memblokir checkout akun tersebut | 1 |
| T14 | Invoice dipalsukan untuk klaim ke pihak lain | Invoice PDF memuat QR menuju halaman verifikasi invoice. Halaman itu menampilkan kode pesanan, total, dan status, tanpa data pribadi | 1 |
| T15 | Bukti asli hilang saat terjadi sengketa | File bukti asli disimpan terenkripsi di disk privat selama masa simpan catatan transaksi. Admin melihat salinan yang di-encode ulang dan bersih dari muatan tersembunyi | 1 |

Perbaikan T1, T3, T4, dan T8 paling penting dan wajib ada sebelum peluncuran. Keempatnya melindungi uang secara langsung: dari bukti palsu, dari kesalahan atau kecurangan internal, dari pengalihan rekening, dan dari refund fiktif.

## 12. Desain UI/UX dan identitas visual

Prinsip utamanya: **unik di permukaan, familiar di struktur.** Warna, motif, ilustrasi, dan animasi membawa identitas Bugis-Bone. Navigasi, kartu produk, keranjang, dan checkout mengikuti pola marketplace yang sudah dikenal warga, sehingga tidak ada yang perlu dipelajari ulang.

**12.1 Identitas visual (arah awal, perlu persetujuan klien)**

| Elemen | Usulan |
| --- | --- |
| Tema | "Bugis Modern": hangat, bersih, banyak ruang kosong |
| Warna utama | Merah marun #7A1E2C (warna kain sutra Bugis) untuk tombol utama dan aksen |
| Warna pendukung | Emas kunyit #D9A21B untuk promo dan harga diskon; hijau daun #2F6B4F untuk status berhasil dan stok tersedia |
| Netral | Krem #FAF6EE sebagai latar, arang #1F1B16 untuk teks; mode terang menjadi bawaan; mode gelap memakai latar arang dan teks krem, dengan warna aksen yang dicerahkan agar kontras tetap cukup |
| Huruf | Plus Jakarta Sans (huruf rancangan Indonesia, lisensi terbuka) untuk semua teks, angka tabular untuk harga |
| Motif | Pola geometris terinspirasi anyaman walasuji dan konsep sulapa eppa (segi empat), dipakai tipis di latar hero, pemisah bagian, dan animasi memuat |
| Ikon | Lucide untuk ikon antarmuka; ikon kategori khusus (kopi, sutra, hasil laut, olahan pangan, kerajinan) digambar dengan ketebalan garis yang sama dengan Lucide |
| Foto | Foto produk berlatar polos dan foto produsen saat bekerja; panduan foto sederhana untuk Admin |

Semua warna disimpan sebagai token desain Tailwind (`--color-primary`, dan seterusnya). Mengganti palet cukup di satu file.

**12.2 Tata letak dan navigasi**

- Mobile-first. Di HP, navigasi bawah berisi 5 tab: Beranda, Kategori, Cari, Keranjang, Akun.
- Kotak pencarian menempel di atas saat menggulir.
- Kartu produk seragam: foto 1:1, nama 2 baris, harga, harga coret, nama produsen, rating.
- Checkout satu halaman dengan ringkasan biaya menempel di sisi kanan (desktop) atau bawah (HP).
- Tombol utama satu per layar, berwarna marun. Aksi lain memakai tombol garis atau teks.

**12.3 Rencana animasi**

| Tempat | Animasi | Pustaka |
| --- | --- | --- |
| Pindah halaman | Fade dan geser ringan 150 sampai 200 ms | Motion |
| Tambah ke keranjang | Gambar produk "terbang" ke ikon keranjang, angka keranjang memantul | Motion |
| Wishlist | Ikon hati mengisi dengan pantulan kecil | Motion |
| Landing page | Cerita produsen muncul bertahap saat digulir, motif walasuji tersusun perlahan | GSAP ScrollTrigger |
| Pembayaran disetujui, keranjang kosong, hasil kosong | Ilustrasi animasi pendek | Lottie |
| Hitung mundur pembayaran | Cincin progres yang berkurang, berubah emas saat sisa 2 jam | CSS + Motion |
| Memuat data | Kerangka (skeleton) berbentuk kartu, bukan pemutar | Tailwind |

Aturan animasi: hanya menganimasikan `transform` dan `opacity`; umpan balik interaksi maksimal 300 ms; semua animasi dimatikan atau disederhanakan saat pengguna mengaktifkan `prefers-reduced-motion`.

**12.4 Aksesibilitas dan keterbacaan**

- Kontras warna memenuhi WCAG 2.2 AA. Marun di atas krem dan teks arang di atas krem perlu diuji dengan alat kontras sebelum token final.
- Area sentuh minimal 44 x 44 px. Ukuran huruf kolom isian minimal 16 px agar iOS tidak memperbesar layar.
- Semua ikon tanpa teks punya label untuk pembaca layar. Fokus keyboard selalu terlihat.

**12.5 Panel Admin**

Admin kemungkinan staf Karang Taruna dengan pengalaman teknologi beragam. Panel memakai layout sidebar starter kit, bahasa Indonesia sederhana tanpa istilah teknis, tombol besar, dan konfirmasi sebelum aksi yang tidak bisa dibatalkan. Layar verifikasi pembayaran menampilkan bukti dan tagihan berdampingan, sehingga satu keputusan butuh satu layar.

**12.6 Anggaran performa**

| Ukuran | Target |
| --- | --- |
| Largest Contentful Paint, HP Android kelas menengah, 4G | di bawah 2,5 detik |
| JavaScript awal halaman toko (gzip) | di bawah 200 KB |
| Skor Lighthouse mobile (performa / aksesibilitas) | minimal 85 / 95 |
| Gambar | WebP dengan `srcset`, dimuat saat mendekati layar |

GSAP dan Lottie hanya dimuat di halaman yang memakainya, agar halaman produk dan checkout tetap ringan.

**12.7 Bahasa dan mode terang/gelap**

| Aspek | Bahasa | Mode tampilan |
| --- | --- | --- |
| Bawaan | Bahasa Indonesia | Terang |
| Pilihan fase 1 | Indonesia dan Inggris (bahasa tambahan cukup menambah file terjemahan) | Terang dan Gelap |
| Letak pengatur | Header (semua halaman) dan halaman profil | Header (ikon matahari/bulan) dan halaman profil |
| Penyimpanan | URL (`/` untuk Indonesia, `/en/...` untuk Inggris), cookie, kolom `users.locale` | Cookie dan kolom `users.theme` |
| Cara kerja di SSR | Server membaca bahasa dari URL dan merender teks yang benar sejak HTML pertama | Server membaca cookie dan memasang kelas `dark` di `<html>`, sehingga tidak ada kedipan putih |

**Teks antarmuka.** Semua teks antarmuka disimpan di file terjemahan Laravel (`lang/id.json`, `lang/en.json`) dan dikirim ke React lewat props bersama Inertia. Tidak ada teks antarmuka yang ditulis langsung di komponen, sehingga menambah bahasa tidak menyentuh kode.

**Konten.** Nama produk, deskripsi, kategori, berita, dan banner punya kolom terjemahan opsional (bagian 9). Admin mengisi bahasa Indonesia sebagai wajib dan bahasa lain sebagai opsional. Pencarian mencocokkan semua bahasa sekaligus, jadi "coffee" tetap menemukan "Kopi Robusta Bone".

**Format angka dan tanggal.** Harga selalu dalam rupiah dengan format Indonesia (Rp 154.237) di semua bahasa, karena transaksi memakai rupiah. Tanggal mengikuti bahasa yang dipilih.

**Mode gelap.** Semua warna berasal dari token desain dengan nilai terang dan gelap. Gambar produk tidak diubah. Ilustrasi Lottie dan motif walasuji punya versi gelap. Kontras WCAG 2.2 AA diuji di kedua mode, termasuk panel Admin. Opsi "ikuti pengaturan perangkat" bisa ditambahkan, tetapi bawaannya tetap terang sesuai keputusan klien.

**12.8 Pengolahan gambar ke WebP**

Semua gambar yang diunggah ke situs dikonversi otomatis ke WebP dalam beberapa ukuran. Browser hanya mengunduh ukuran yang sesuai layarnya. Konversi memakai fitur [Image bawaan Laravel 13](https://laravel.com/docs/13.x/images) (berbasis Intervention Image, driver Imagick dengan GD sebagai cadangan), sehingga tidak perlu layanan berbayar.

**Alur unggah**

1. Admin atau Pembeli mengunggah JPG, PNG, atau WebP, maksimal 10 MB. HEIC dari iPhone diterima jika ekstensi Imagick di server mendukungnya. Foto produk minimal lebarnya 800 px.
2. File asli disimpan di disk privat sebagai sumber untuk diproses ulang nanti.
3. Antrean memproses gambar: memutar sesuai orientasi kamera (`orient()`), mengecilkan ke setiap lebar turunan (`scale()`, tidak pernah memperbesar), lalu `toWebp()->quality(80)`. Encode ulang ini juga membuang metadata EXIF, termasuk lokasi GPS dari foto HP.
4. Sistem mencatat lebar, tinggi, dan warna dominan gambar. Warna dominan dipakai sebagai latar sementara selama gambar dimuat.
5. Selama antrean berjalan, situs menampilkan placeholder. Jika proses gagal, antrean mencoba ulang 3 kali lalu memberi tahu Admin.

**Ukuran turunan**

| Jenis gambar | Lebar turunan (px) | Catatan |
| --- | --- | --- |
| Foto produk | 320, 640, 1280, 1920 | Rasio 1:1 tanpa memotong produk; sisi kosong diisi warna dominan. Ukuran 1920 hanya untuk zoom |
| Banner | Desktop 1280 dan 1920; HP 640 dan 960 | Gambar desktop dan HP diunggah terpisah |
| Foto produsen, berita, dokumentasi | 640, 1280 |  |
| Galeri | 320, 1280 |  |
| Foto ulasan | 320, 1024 |  |
| Gambar preview tautan (Open Graph) | 1200 x 630, format JPEG | Tetap JPEG karena tidak semua aplikasi chat menampilkan preview WebP dengan baik |
| Bukti transfer | Salinan tampilan 1600, di disk privat | Tidak pernah dipublikasikan; aturan di bagian 11 |

**Penyajian di halaman**

- Setiap gambar memakai `srcset` dan `sizes`, ditambah atribut `width` dan `height` agar tata letak tidak bergeser saat gambar dimuat.
- Gambar di bawah layar memakai `loading="lazy"`. Gambar utama di atas layar (hero, foto pertama produk) dimuat lebih dulu dengan `fetchpriority="high"`.
- Nama file berisi hash isi gambar, sehingga gambar bisa di-cache browser dan CDN selama 1 tahun. Gambar yang diganti otomatis mendapat nama baru.
- Ikon memakai SVG. Gambar statis bawaan situs disimpan langsung sebagai WebP atau SVG.
- Perintah `php artisan images:regenerate` membuat ulang semua turunan dari file asli jika ukuran turunan diubah di kemudian hari.

## 13. Kebutuhan non-fungsional dan infrastruktur VPS

Satu VPS dengan 2 vCPU, 4 GB RAM, dan SSD 80 GB cukup untuk tahap awal. Angka ini berdasarkan asumsi beban di bawah, yang perlu dikonfirmasi ke klien. Jika beban nyata jauh lebih besar, langkah pertama adalah memindahkan MySQL ke server sendiri, bukan memecah aplikasi.

**Asumsi beban awal:** hingga 2.000 kunjungan per hari, 50 pesanan per hari, dan 5.000 varian produk.

| Kebutuhan | Target |
| --- | --- |
| Ketersediaan | 99,5% per bulan (henti layanan maksimal sekitar 3,6 jam per bulan) |
| Waktu respons server | p95 di bawah 300 ms untuk halaman, di bawah 100 ms untuk saran pencarian |
| Kehilangan data maksimal (RPO) | 24 jam; turun ke 1 jam jika binary log MySQL dicadangkan per jam |
| Waktu pemulihan (RTO) | 4 jam dari backup ke server baru |
| Penyimpanan backup | Harian disimpan 14 hari, mingguan disimpan 8 minggu, di lokasi di luar VPS |
| Browser yang didukung | Dua versi terakhir Chrome, Safari, Firefox, Edge, dan Samsung Internet |

**Lingkungan dan alur rilis**

1. Tiga lingkungan: lokal, staging (salinan produksi dengan data palsu), dan produksi.
2. Kode di GitHub dengan cabang `main` yang dilindungi; setiap perubahan lewat pull request.
3. GitHub Actions menjalankan Pint, PHPStan, ESLint, Pest, dan audit dependensi pada setiap pull request.
4. Gabung ke `main` otomatis rilis ke staging. Rilis ke produksi dipicu manual dengan tag versi.
5. Rilis tanpa henti layanan (folder rilis baru, lalu symlink dipindah), migration dijalankan, lalu Horizon dan server SSR dimulai ulang.

**Pemantauan**

- Pemantau ketersediaan dari luar yang mengirim peringatan ke email dan WhatsApp tim.
- Laravel Pulse untuk permintaan lambat, query lambat, dan antrean.
- Pelacak galat (misalnya Sentry atau Laravel Nightwatch).
- Peringatan khusus: antrean macet lebih dari 10 menit, penjadwal tidak berjalan lebih dari 5 menit, backup gagal, disk lebih dari 80%.

**Sistem operasi dan pengelolaan server:** Ubuntu Server LTS. Server bisa disiapkan manual atau lewat layanan seperti Laravel Forge, yang mengurus Nginx, PHP, SSL Let's Encrypt, Supervisor, dan rilis.

## 14. Server MCP opsional untuk operasional Admin (fase 3)

Server MCP memungkinkan Admin bertanya ke asisten AI seperti Claude, misalnya "pesanan mana yang menunggu verifikasi lebih dari 12 jam?", lalu asisten membaca data toko lewat tool yang aman. Fitur ini opsional dan tidak mengubah keputusan bahwa sistem rekomendasi toko tidak memakai AI. Server ini hanya alat bantu operasional di sisi Admin.

**Implementasi**

- Paket resmi [Laravel MCP](https://laravel.com/docs/13.x/mcp), didaftarkan di `routes/ai.php` dengan `Mcp::web('/mcp/toko', TokoServer::class)`.
- Autentikasi dengan Sanctum (`auth:sanctum`), karena aplikasi sudah memakainya. Dokumentasi Laravel menyarankan Passport (OAuth 2.1) jika nanti ada klien MCP yang hanya mendukung OAuth.
- Setiap tool memanggil Action dan Policy yang sama dengan panel web, sehingga Admin tidak mendapat hak lebih lewat MCP.
- Setiap panggilan tool tercatat di log audit dengan nama tool, argumen, dan pengguna.
- Batas laju 30 panggilan per menit per token.

**Daftar tool**

| Tool | Fungsi | Anotasi |
| --- | --- | --- |
| `toko_list_pending_verifications` | Pesanan menunggu verifikasi, diurutkan dari yang paling lama, dengan nominal dan umur antrean | Read-only |
| `toko_get_order` | Detail satu pesanan berdasarkan kode: item, status, riwayat, bukti (tanpa file) | Read-only |
| `toko_search_orders` | Cari pesanan per status, rentang tanggal, atau nama pembeli; berhalaman | Read-only |
| `toko_sales_summary` | Ringkasan penjualan per periode, per kategori, per produsen | Read-only |
| `toko_low_stock` | Varian dengan stok di bawah ambang | Read-only |
| `toko_search_insights` | Kata kunci populer dan kata kunci tanpa hasil | Read-only |
| `toko_set_tracking_number` | Isi nomor resi untuk pesanan berstatus Diproses | Idempotent, tidak destruktif |

**Yang sengaja tidak disediakan:** menyetujui atau menolak pembayaran, membatalkan pesanan, mengubah harga, dan mengelola akun. Keputusan yang menyangkut uang dan akses tetap dilakukan manusia di panel, dengan 2FA dan log audit.

**Pengujian:** unit test per tool dengan `TokoServer::actingAs($admin)->tool(...)`, uji manual dengan `php artisan mcp:inspector mcp/toko`, dan 10 pertanyaan evaluasi realistis yang jawabannya bisa dicek dari data staging (misalnya "Produsen mana yang penjualannya paling tinggi bulan lalu?").

## 15. Pengujian dan kriteria penerimaan

Setiap aturan bisnis punya tes otomatis yang membuktikannya. Fitur dianggap selesai jika tesnya lulus di CI, bukan saat terlihat berjalan di layar.

**Lapisan pengujian**

| Lapisan | Alat | Cakupan |
| --- | --- | --- |
| Unit | Pest | `PriceCalculator`, aturan voucher, state machine pesanan, skor rekomendasi |
| Fitur / HTTP | Pest | Setiap endpoint web dan API v1, termasuk otorisasi dan validasi |
| Konkurensi | Pest + proses paralel | Pemotongan stok dan kuota voucher |
| End-to-end | Playwright (atau Pest Browser) | Alur daftar, verifikasi, belanja, checkout, upload bukti, verifikasi Admin |
| Beban | k6 | Checkout dan saran pencarian di staging |
| Keamanan | OWASP ZAP, `composer audit`, `npm audit` | Sebelum setiap rilis besar |

**Kriteria penerimaan yang bisa diverifikasi**

| No | Kriteria | Cara verifikasi |
| --- | --- | --- |
| K1 | Total pesanan selalu sama dengan rumus bagian 6 | 30 kasus tabel (voucher persen, nominal, batas maksimal, ongkir gratis, kode unik) lulus |
| K2 | Stok tidak pernah negatif | 50 checkout paralel untuk stok 10: tepat 10 berhasil, 40 mendapat pesan stok habis |
| K3 | Pesanan tanpa bukti kedaluwarsa tepat waktu | Waktu dimajukan melewati batas: status Kedaluwarsa dalam 1 menit, stok kembali, item kembali di keranjang |
| K4 | Upload bukti setelah batas ditolak | Respons 409 dari web dan API |
| K5 | Penolakan kedua membatalkan pesanan | Status Dibatalkan, stok dan voucher kembali |
| K6 | Pembeli tidak bisa membuka pesanan orang lain | Respons 404 untuk web dan API |
| K7 | Hanya Super Admin bisa membuat Admin | Admin mendapat 403 pada endpoint undangan |
| K8 | Checkout butuh email terverifikasi | Pengguna belum terverifikasi diarahkan ke halaman verifikasi |
| K9 | Bukti ganda terdeteksi | File yang sama di pesanan kedua memunculkan peringatan di panel |
| K10 | Saran pencarian cepat | p95 server di bawah 100 ms dengan 5.000 varian di staging |
| K11 | Blok "jarang dicari" tidak menampilkan produk habis | Tes dengan produk stok 0 di kategori jarang |
| K12 | Halaman toko ringan | Lighthouse mobile: performa minimal 85, aksesibilitas minimal 95 di landing, kategori, produk, checkout |
| K13 | Preview tautan WhatsApp benar | Meta `og:title`, `og:image`, `og:description` ada di HTML awal halaman produk |
| K14 | `POST /orders` idempoten | Dua permintaan dengan kunci sama menghasilkan satu pesanan |
| K15 | Pesanan dari dua titik asal menjadi dua paket | Keranjang berisi produk dari dua titik: checkout menampilkan 2 paket, dan O sama dengan jumlah ongkir kedua paket |
| K16 | Status Dikirim hanya setelah semua paket punya resi | Pesanan 2 paket dengan 1 resi tetap berstatus Diproses |
| K17 | Mode gelap tampil tanpa kedipan | Dengan cookie tema gelap, HTML awal dari SSR sudah memuat kelas dark; tanpa cookie, halaman tampil terang |
| K18 | Pergantian bahasa berjalan benar | Halaman /en merender antarmuka Inggris; produk tanpa terjemahan tampil dalam bahasa Indonesia; tag hreflang ada di kedua versi; tidak ada teks antarmuka yang hilang terjemahannya (cek otomatis di CI) |
| K19 | Duplikat pasti ditolak | Produk baru dengan SKU atau barcode yang sudah dipakai tidak tersimpan sampai Admin memilih produk lama atau mengganti kodenya |
| K20 | Produk serupa terdeteksi | "Robusta Kopi Bubuk 250gram" dari produsen dan kategori yang sama dengan "Kopi Bubuk Robusta 250 gr" mendapat skor 0,85 atau lebih, dan penyimpanan ditahan sampai Admin memilih; pasangan yang diputuskan terpisah tidak diperingatkan lagi |
| K21 | Pengurangan stok berlebih ditolak | Stok 12, pengurangan 13: ditolak, stok tetap 12, tidak ada mutasi tercatat |
| K22 | Stok masuk tidak wajar ditahan | Input 1.500 unit sekaligus tidak diproses sampai Admin mengonfirmasi dengan password |
| K23 | Jumlah beli melebihi stok ditolak | Stok 3 dan keranjang berisi 2: menambah 2 ditolak dengan pesan sisa 1; pesanan berisi satu item kurang stok gagal seluruhnya dan tidak ada stok varian lain yang terpotong (API: 409 insufficient\_stock) |
| K24 | Stok opname tidak menimpa penjualan | Stok awal sesi 20, hitung fisik 18, terjual 2 selama sesi: setelah posting, stok menjadi 16 |
| K25 | Stok dipotong tepat saat checkout | Tambah ke keranjang tidak mengubah stok; pembuatan pesanan mengurangi stok; persetujuan Admin tidak mengubah stok |
| K26 | Aturan anti-penimbunan berjalan | Pesanan ke-4 yang menunggu pembayaran ditolak; 3 pesanan kedaluwarsa dalam 7 hari memblokir checkout 24 jam; pesanan berisi flash sale berbatas bayar 1 jam; pembatalan oleh Pembeli tidak dihitung |
| K27 | Verifikasi berbasis mutasi rekening | Tombol Setujui nonaktif sebelum daftar periksa mutasi terisi; pesanan di atas ambang butuh persetujuan kedua, dan persetujuan kedua oleh orang yang sama ditolak |
| K28 | Rekening baru tertahan masa tunggu | Rekening yang baru ditambahkan tidak tampil di checkout sebelum 24 jam; setiap perubahan memicu email ke semua Super Admin |
| K29 | Kode ambil wajib benar | Paket ambil sendiri tidak bisa ditandai diambil tanpa kode yang benar; 5 kali salah mengunci input selama 15 menit |
| K30 | Refund terkendali | Refund ke rekening atas nama lain ditolak; refund tidak tercatat selesai tanpa penyetuju kedua dan bukti transfer refund |
| K31 | Verifikasi hanya oleh Admin Transaksi | Admin tanpa izin transaksi mendapat 403 saat menyetujui atau menolak pembayaran dan memproses refund; Admin Transaksi dan Super Admin berhasil |
| K32 | Ekspor sesuai filter dan terlindungi | Isi file sama dengan tabel yang terfilter; ekspor 10.000 baris diproses di antrean dan tautannya kedaluwarsa setelah 24 jam; Admin biasa melihat nomor WhatsApp tersamar; setiap ekspor tercatat di log audit |
| K33 | Gambar dikonversi ke WebP | Unggah JPG 4 MB menghasilkan turunan WebP 320 sampai 1920 px tanpa metadata GPS; halaman produk menyajikan WebP lewat srcset dengan width dan height; og:image tetap JPEG 1200 x 630 |

Target cakupan kode minimal 80% untuk folder `app/Domain`. Angka cakupan bukan tujuan; kriteria K1 sampai K33 adalah tujuan.

## 16. Roadmap dan pertanyaan terbuka

Pekerjaan dibagi menjadi lima fase. Setiap fase punya syarat selesai yang bisa diperiksa. Durasi belum ditulis karena bergantung pada jumlah anggota tim dan jawaban pertanyaan di bawah.

1. **Fase 0: Fondasi.** Konfirmasi asumsi, identitas visual, skema database, setup repositori, CI, staging, dan VPS.
   - Selesai jika: pertanyaan terbuka terjawab, migration awal berjalan di staging, pipeline CI hijau.
2. **Fase 1: Toko inti (MVP).** Katalog, pencarian instan, akun dan verifikasi email, keranjang, checkout, ongkir, pembayaran semi-otomatis, panel Admin inti (termasuk mutasi stok, stok opname, dan deteksi produk serupa), peran, API v1 untuk Pembeli, beberapa titik asal kirim, bahasa Indonesia dan Inggris, serta mode terang dan gelap.
   - Selesai jika: kriteria K1 sampai K33 lulus, uji coba dengan 10 pembeli nyata dan 2 Admin tanpa galat kritis.
3. **Fase 2: Promosi dan wawasan.** Flash sale, empat blok rekomendasi lengkap, laporan, konten dokumentasi, ulasan, wishlist dengan notifikasi stok, pencocokan mutasi rekening dari file (11.1 T2), verifikasi OTP WhatsApp.
   - Selesai jika: rekomendasi tampil sesuai aturan bagian 7 dan laporan bisa diekspor.
4. **Fase 3: Mobile dan MCP.** Aplikasi mobile memakai API v1, notifikasi push, server MCP untuk Admin.
   - Selesai jika: aplikasi lolos uji end-to-end alur belanja, tool MCP lolos 10 pertanyaan evaluasi.
5. **Fase 4: Skala.** Payment gateway otomatis, akun penjual untuk produsen (marketplace multi-penjual), "Sering dibeli bersama".
   - Selesai jika: ditentukan bersama klien setelah fase 1 berjalan.

**Pertanyaan terbuka untuk klien**

- [x] Bolehkah Tamu melihat katalog dan mencari tanpa login? Dijawab: boleh, login hanya saat checkout.
- [ ] Berapa batas waktu pembayaran: usulan 24 jam, atau lebih pendek?
- [ ] Apakah kode unik nominal transfer disetujui?
- [ ] Rekening tujuan atas nama siapa? Usulan saya: rekening resmi organisasi, bukan rekening pribadi pengurus.
- [x] Dari mana barang dikirim: satu gudang atau beberapa titik? Dijawab: beberapa titik di Kabupaten Bone, termasuk Watampone.
- [ ] Daftar titik asal: alamat, kecamatan, kontak, dan titik mana saja yang melayani ambil sendiri?
- [ ] Apakah ada produk yang sama disimpan di lebih dari satu titik? Jawaban ini menentukan perlu tidaknya stok per titik di fase 1.
- [ ] Bahasa apa saja selain Indonesia? Usulan: Inggris di fase 1. Siapa yang menyiapkan terjemahan konten produk?
- [ ] Kurir apa saja yang dipakai, dan apakah ada kurir lokal serta titik ambil sendiri?
- [ ] Berapa perkiraan jumlah produk, produsen, kunjungan, dan pesanan per hari saat peluncuran?
- [ ] Bagaimana kebijakan pembatalan, retur, dan pengembalian dana?
- [ ] Nama domain apa yang dipakai, dan siapa pemiliknya secara administratif?
- [ ] Apakah ada logo resmi dan izin memakai motif budaya Bugis-Bone untuk identitas visual?
- [ ] Siapa yang merawat sistem setelah serah terima, dan apakah perlu pelatihan Admin?
- [x] Siapa yang memegang akses internet banking untuk memeriksa mutasi rekening? Dijawab: Admin resmi yang ditunjuk untuk urusan transaksi (peran Admin Transaksi, bagian 2).
- [ ] Berapa ambang nominal pesanan yang butuh persetujuan kedua? Usulan: Rp 5.000.000.
- [ ] Apakah nilai anti-penimbunan di 4.5 (3 pesanan aktif, blokir 24 jam, batas bayar flash sale 1 jam) sesuai kebiasaan pembeli setempat?

**Sumber**

- [Laravel 13: Starter Kits](https://laravel.com/docs/13.x/starter-kits)
- [Laravel 13: MCP](https://laravel.com/docs/13.x/mcp)
- [RajaOngkir API V2 (Komerce): Getting Started](https://rajaongkir.com/docs/shipping-cost/getting_started/about)
- [Laravel 13: Image Manipulation](https://laravel.com/docs/13.x/images)
