<?php

namespace Database\Seeders;

use App\Domain\Catalog\ProductPublisher;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Catalog\SimilarityChecker;
use App\Domain\Order\OrderStatus;
use App\Domain\Shipping\BoneDistricts;
use App\Models\BankAccount;
use App\Models\Banner;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Origin;
use App\Models\Page;
use App\Models\Post;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\Tag;
use App\Models\User;
use App\Models\Voucher;
use App\Support\ProductArt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data contoh untuk pengembangan dan staging. Nama produsen dan produk bersifat ilustratif.
 */
class BokaSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->users();
        $origins = $this->origins();
        $categories = $this->categories();
        $producers = $this->producers($origins);
        $products = $this->products($categories, $producers);

        BankAccount::query()->create(['bank_name' => 'BRI', 'account_number' => '0123-01-000456-30-1', 'account_holder' => 'Bone Karya (Karang Taruna Kab. Bone)', 'active_from' => now()->subDays(3)]);
        BankAccount::query()->create(['bank_name' => 'Bank Sulselbar', 'account_number' => '130-002-000123-4', 'account_holder' => 'Bone Karya (Karang Taruna Kab. Bone)', 'active_from' => now()->subDays(3)]);

        Voucher::query()->create(['code' => 'BOKABARU', 'type' => 'belanja', 'discount_type' => 'persen', 'value' => 10, 'max_discount' => 25_000, 'min_spend' => 50_000, 'quota' => 500, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3), 'scope' => ['new_buyer_only' => true]]);
        Voucher::query()->create(['code' => 'ONGKIRBONE', 'type' => 'ongkir', 'discount_type' => 'nominal', 'value' => 10_000, 'min_spend' => 75_000, 'quota' => 300, 'per_user_limit' => 3, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3)]);

        $flash = FlashSale::query()->create(['name' => 'Pekan Karya Bone', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(2)->setTime(21, 0)]);
        foreach (['kopi-bubuk-robusta-bontocani', 'barongko-pisang-khas-bone', 'abon-ikan-tuna-bajoe'] as $slug) {
            $variant = $products[$slug]->variants()->first();
            $flash->items()->create(['variant_id' => $variant->id, 'sale_price' => (int) round($variant->price * 0.8, -2), 'quota' => 20]);
        }

        $this->content($users['super']);
        $this->history($users['buyer'], $products);
    }

    private function users(): array
    {
        $make = function (string $name, string $email, string $phone, ?string $role) {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => 'password', 'privacy_accepted_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $role && $user->assignRole($role);

            return $user;
        };

        return [
            'super' => $make('Super Admin Boka', 'super@boka.test', '081200000001', 'super_admin'),
            'super2' => $make('Super Admin Cadangan', 'super2@boka.test', '081200000002', 'super_admin'),
            'finance' => $make('Andi Transaksi', 'transaksi@boka.test', '081200000003', 'admin_transaksi'),
            'admin' => $make('Besse Admin', 'admin@boka.test', '081200000004', 'admin'),
            'buyer' => $make('Rahmawati', 'pembeli@boka.test', '081234564567', 'pembeli'),
        ];
    }

    private function origins(): array
    {
        $data = [
            'watampone' => ['Gudang Watampone', 'Jl. Jenderal Sudirman No. 12, Watampone', 'Tanete Riattang', true, 'Senin–Sabtu, 08.00–17.00 WITA'],
            'bontocani' => ['Titik Bontocani', 'Jl. Poros Kahu–Bontocani, Desa Pattuku', 'Bontocani', false, null],
            'bajoe' => ['Titik Pelabuhan Bajoe', 'Jl. Pelabuhan Bajoe, Kel. Bajoe', 'Tanete Riattang Timur', true, 'Setiap hari, 07.00–15.00 WITA'],
            'kahu' => ['Titik Kahu', 'Jl. Poros Palattae, Kec. Kahu', 'Kahu', false, null],
        ];

        $origins = [];

        foreach ($data as $key => [$name, $address, $district, $pickup, $hours]) {
            $origin = Origin::query()->create([
                'name' => $name, 'address' => $address, 'district' => $district,
                'contact' => '0812-4000-'.str_pad((string) (count($origins) + 1), 4, '0', STR_PAD_LEFT),
                'couriers' => ['jne', 'jnt', 'pos'], 'pickup_enabled' => $pickup, 'pickup_hours' => $hours,
            ]);

            $originIndex = array_search($district, BoneDistricts::ALL, true);

            foreach (BoneDistricts::ALL as $i => $destination) {
                $distance = abs($i - $originIndex);
                $origin->localRates()->create(['destination_district' => $destination, 'fee' => $distance === 0 ? 8_000 : min(35_000, 12_000 + $distance * 1_000)]);
            }

            $origins[$key] = $origin;
        }

        return $origins;
    }

    private function categories(): array
    {
        $tree = [
            ['KOP', 'kopi-minuman', 'Kopi & Minuman', 'Coffee & Drinks', 'coffee', [['KPB', 'kopi', 'Kopi', 'Coffee'], ['GAR', 'gula-aren', 'Gula Aren', 'Palm Sugar']]],
            ['SUT', 'kain-sutra', 'Kain & Sutra', 'Silk & Textiles', 'scroll', [['LPS', 'lipa-sabbe', "Lipa' Sabbe", 'Silk Sarong'], ['SLD', 'selendang', 'Selendang', 'Shawls']]],
            ['LAU', 'hasil-laut', 'Hasil Laut', 'Seafood', 'fish', [['IKK', 'ikan-kering', 'Ikan Kering', 'Dried Fish'], ['ABN', 'abon-olahan-ikan', 'Abon & Olahan Ikan', 'Fish Floss']]],
            ['PGN', 'olahan-pangan', 'Olahan Pangan', 'Food & Snacks', 'cookie', [['KUE', 'kue-tradisional', 'Kue Tradisional', 'Traditional Cakes'], ['KRP', 'keripik-camilan', 'Keripik & Camilan', 'Chips & Snacks']]],
            ['KRJ', 'kerajinan', 'Kerajinan', 'Crafts', 'gem', [['ANY', 'anyaman', 'Anyaman', 'Woven Goods'], ['UKR', 'ukiran-kayu', 'Ukiran Kayu', 'Wood Carving']]],
            ['TAN', 'pertanian-rempah', 'Pertanian & Rempah', 'Farm & Spices', 'leaf', [['MDU', 'madu', 'Madu', 'Honey'], ['RMP', 'rempah', 'Rempah', 'Spices']]],
        ];

        $map = [];

        foreach ($tree as $order => [$code, $slug, $id, $en, $icon, $children]) {
            $parent = Category::query()->create(['name' => ['id' => $id, 'en' => $en], 'slug' => $slug, 'code' => $code, 'icon' => $icon, 'sort_order' => $order]);
            $map[$code] = $parent;

            foreach ($children as $childOrder => [$childCode, $childSlug, $childId, $childEn]) {
                $map[$childCode] = Category::query()->create(['parent_id' => $parent->id, 'name' => ['id' => $childId, 'en' => $childEn], 'slug' => $childSlug, 'code' => $childCode, 'icon' => $icon, 'sort_order' => $childOrder]);
            }
        }

        return $map;
    }

    private function producers(array $origins): array
    {
        $data = [
            'kt-bontocani' => ['Karang Taruna Sipakatau', 'karang_taruna', 'Pattuku', 'Bontocani', 'bontocani', 'Pemuda Desa Pattuku memetik, menjemur, dan menyangrai sendiri kopi robusta dari kebun keluarga di lereng Bontocani. Hasil penjualan dipakai untuk kegiatan pemuda desa.'],
            'umkm-tenun' => ["UMKM Tenun Ade' Bone", 'umkm', 'Pakkasalo', 'Sibulue', 'watampone', 'Kelompok penenun ibu-ibu Desa Pakkasalo menenun sutra dengan alat gedogan dan ATBM. Satu lembar sarung butuh dua sampai tiga minggu.'],
            'umkm-bajoe' => ['UMKM Bajo Bahari', 'umkm', 'Bajoe', 'Tanete Riattang Timur', 'bajoe', 'Nelayan dan istri nelayan Bajoe mengolah tangkapan harian menjadi ikan kering dan abon tanpa pengawet.'],
            'kt-kahu' => ['Karang Taruna Bina Karya Kahu', 'karang_taruna', 'Palattae', 'Kahu', 'kahu', 'Pemuda Kahu menyadap nira aren dan mencetak gula merah dengan tungku kayu, mengikuti cara orang tua mereka.'],
            'umkm-dapur' => ["UMKM Dapur Indo' Bone", 'umkm', 'Macege', 'Tanete Riattang Barat', 'watampone', 'Dapur rumahan di Watampone yang membuat barongko, bolu peca, dan baje setiap pagi untuk pesanan hajatan dan oleh-oleh.'],
            'kt-awangpone' => ['Karang Taruna Tunas Mallari', 'karang_taruna', 'Mallari', 'Awangpone', 'watampone', 'Pemuda Mallari menganyam daun lontar menjadi tas, tikar, dan wadah serbaguna. Mereka juga melatih anak sekolah menganyam setiap Sabtu.'],
            'umkm-madu' => ['UMKM Madu Hutan Libureng', 'umkm', 'Mattirowalie', 'Libureng', 'kahu', 'Pemburu madu Libureng memanen sarang lebah hutan dengan cara tiris, tanpa memeras, agar madu tetap jernih.'],
        ];

        $producers = [];

        foreach ($data as $key => [$name, $type, $village, $district, $origin, $story]) {
            $producers[$key] = Producer::query()->create([
                'name' => $name, 'slug' => Str::slug($name), 'type' => $type, 'village' => $village, 'district' => $district,
                'story' => ['id' => $story], 'contact' => '0813-5500-'.str_pad((string) (count($producers) + 11), 4, '0', STR_PAD_LEFT),
                'default_origin_id' => $origins[$origin]->id,
            ]);
        }

        return $producers;
    }

    private function products(array $categories, array $producers): array
    {
        $data = [
            ['Kopi Bubuk Robusta Bontocani', 'Bontocani Ground Robusta Coffee', 'KPB', 'kt-bontocani', 'Robusta petik merah, sangrai sedang. Rasa cokelat dan kacang dengan pahit yang bersih. Cocok diseduh tubruk.', ['kopi', 'robusta', 'oleh-oleh'], [[['Berat' => '250 g'], 45_000, null, 40, 270], [['Berat' => '500 g'], 85_000, null, 18, 520]], 120, true],
            ['Kopi Biji Robusta Sangrai', 'Roasted Robusta Beans', 'KPB', 'kt-bontocani', 'Biji utuh untuk Anda giling sendiri. Disangrai dalam jumlah kecil setiap minggu.', ['kopi', 'robusta'], [[['Berat' => '250 g'], 48_000, 55_000, 25, 260]], 64, false],
            ['Gula Aren Cetak Kahu', 'Kahu Palm Sugar Blocks', 'GAR', 'kt-kahu', 'Gula aren cetak tempurung dari nira segar. Manis legit dengan aroma karamel.', ['gula aren', 'pemanis alami'], [[['Berat' => '500 g'], 25_000, null, 60, 520], [['Berat' => '1 kg'], 47_000, null, 30, 1_020]], 210, true],
            ['Gula Semut Aren', 'Granulated Palm Sugar', 'GAR', 'kt-kahu', 'Gula aren butiran, larut cepat untuk kopi dan kue.', ['gula aren', 'pemanis alami'], [[['Berat' => '250 g'], 22_000, null, 8, 260]], 40, false],
            ["Lipa' Sabbe Motif Balo Renni", 'Balo Renni Silk Sarong', 'LPS', 'umkm-tenun', 'Sarung sutra tenun tangan dengan motif kotak kecil balo renni. Ukuran 200 x 110 cm.', ['sutra', 'tenun', 'bugis'], [[['Warna' => 'Merah Marun'], 650_000, 750_000, 4, 450], [['Warna' => 'Hijau Daun'], 650_000, 750_000, 3, 450]], 12, true],
            ["Lipa' Sabbe Motif Cobo'", "Cobo' Silk Sarong", 'LPS', 'umkm-tenun', 'Motif zig-zag cobo\' khas Bugis, ditenun dengan benang sutra lokal.', ['sutra', 'tenun', 'bugis'], [[['Warna' => 'Kuning Kunyit'], 720_000, null, 2, 450]], 6, false],
            ['Selendang Sutra Bone', 'Bone Silk Shawl', 'SLD', 'umkm-tenun', 'Selendang sutra ringan 180 x 45 cm untuk acara adat dan hadiah.', ['sutra', 'hadiah'], [[['Warna' => 'Emas'], 185_000, null, 9, 150], [['Warna' => 'Marun'], 185_000, null, 0, 150]], 30, false],
            ['Ikan Teri Kering Bajoe', 'Bajoe Dried Anchovies', 'IKK', 'umkm-bajoe', 'Teri nasi dijemur sinar matahari, tawar dan tidak terlalu asin.', ['ikan', 'hasil laut'], [[['Berat' => '250 g'], 38_000, null, 35, 270]], 95, false],
            ['Ikan Asin Layang', 'Salted Scad Fish', 'IKK', 'umkm-bajoe', 'Ikan layang belah, diasinkan ringan dan dijemur dua hari.', ['ikan', 'hasil laut'], [[['Berat' => '500 g'], 42_000, null, 20, 520]], 55, false],
            ['Abon Ikan Tuna Bajoe', 'Bajoe Tuna Floss', 'ABN', 'umkm-bajoe', 'Abon tuna pedas manis tanpa pengawet. Tahan dua bulan dalam toples tertutup.', ['ikan', 'abon', 'oleh-oleh'], [[['Rasa' => 'Original'], 35_000, null, 30, 150], [['Rasa' => 'Pedas'], 35_000, null, 22, 150]], 150, true],
            ['Barongko Pisang Khas Bone', 'Bone Banana Barongko', 'KUE', 'umkm-dapur', 'Kue pisang kukus bungkus daun pisang. Dibuat pagi, dikirim hari yang sama untuk wilayah Bone.', ['kue', 'oleh-oleh', 'bugis'], [[['Isi' => '10 buah'], 40_000, null, 15, 900]], 180, true],
            ['Bolu Peca', 'Bolu Peca Cake', 'KUE', 'umkm-dapur', 'Bolu lembut berbahan telur bebek dan gula aren Kahu.', ['kue', 'oleh-oleh'], [[['Isi' => '1 loyang'], 55_000, null, 10, 700]], 75, false],
            ['Baje Canggoreng', 'Baje Canggoreng Sweets', 'KUE', 'umkm-dapur', 'Ketan sangrai gula merah, dibungkus kulit jagung kering.', ['kue', 'bugis'], [[['Isi' => '20 buah'], 30_000, 35_000, 12, 450]], 48, false],
            ['Keripik Pisang Gula Aren', 'Palm Sugar Banana Chips', 'KRP', 'umkm-dapur', 'Pisang kepok iris tipis, digoreng lalu dibalut karamel gula aren.', ['camilan', 'pisang'], [[['Berat' => '200 g'], 20_000, null, 50, 220]], 130, false],
            ['Tas Anyaman Lontar', 'Lontar Woven Bag', 'ANY', 'kt-awangpone', 'Tas jinjing anyaman lontar dengan tali kain. Ukuran 35 x 30 cm.', ['anyaman', 'kerajinan', 'hadiah'], [[['Ukuran' => 'Sedang'], 95_000, null, 14, 400]], 22, false],
            ['Tikar Lontar Motif Sulapa Eppa', 'Sulapa Eppa Lontar Mat', 'ANY', 'kt-awangpone', 'Tikar 150 x 100 cm dengan motif segi empat sulapa eppa.', ['anyaman', 'kerajinan'], [[['Ukuran' => '150 x 100 cm'], 160_000, null, 5, 1_500]], 9, false],
            ['Hiasan Dinding Ukir Phinisi', 'Phinisi Wood Carving', 'UKR', 'kt-awangpone', 'Ukiran kayu jati bentuk perahu phinisi, finishing pernis.', ['kerajinan', 'hadiah'], [[['Ukuran' => '40 cm'], 240_000, null, 3, 1_200]], 4, false],
            ['Madu Hutan Libureng', 'Libureng Wild Honey', 'MDU', 'umkm-madu', 'Madu lebah hutan dipanen dengan cara tiris. Kadar air rendah, rasa sedikit asam.', ['madu', 'kesehatan'], [[['Isi' => '250 ml'], 75_000, null, 20, 400], [['Isi' => '500 ml'], 140_000, null, 10, 750]], 88, true],
            ['Jahe Merah Bubuk', 'Red Ginger Powder', 'RMP', 'kt-kahu', 'Jahe merah instan dengan gula aren, tinggal seduh air panas.', ['rempah', 'kesehatan', 'minuman'], [[['Berat' => '250 g'], 28_000, null, 0, 260]], 33, false],
        ];

        Storage::disk('public')->makeDirectory('produk');
        $products = [];

        foreach ($data as [$nameId, $nameEn, $categoryCode, $producerKey, $description, $tags, $variants, $sold, $featured]) {
            $category = $categories[$categoryCode];
            $producer = $producers[$producerKey];
            $slug = Str::slug($nameId);

            $product = Product::query()->create([
                'producer_id' => $producer->id,
                'category_id' => $category->id,
                'origin_id' => $producer->default_origin_id,
                'name' => ['id' => $nameId, 'en' => $nameEn],
                'name_normalized' => SimilarityChecker::normalize($nameId),
                'slug' => $slug,
                'description' => ['id' => $description],
                'status' => ProductStatus::Aktif,
                'is_featured' => $featured,
                'similarity_resolved' => true,
                'sold_count' => $sold,
            ]);

            $parentCode = $category->parent?->code ?? $categoryCode;
            Storage::disk('public')->put("produk/{$slug}.svg", ProductArt::svg(Str::limit($nameId, 26), $parentCode, $producer->name));
            $product->images()->create(['path' => "produk/{$slug}.svg", 'width' => 800, 'height' => 800, 'dominant_color' => ProductArt::PALETTE[$parentCode][1], 'is_cover' => true]);

            foreach ($variants as [$options, $price, $compare, $stock, $weight]) {
                $product->variants()->create([
                    'sku' => ProductPublisher::generateSku($product, $options),
                    'options' => $options,
                    'options_key' => ProductPublisher::optionsKey($options),
                    'price' => $price,
                    'compare_price' => $compare,
                    'stock' => $stock,
                    'weight_gram' => $weight,
                ]);
            }

            $product->tags()->sync(collect($tags)->map(fn ($t) => Tag::query()->firstOrCreate(['name' => $t])->id));
            $products[$slug] = $product;
        }

        return $products;
    }

    private function content(User $author): void
    {
        Banner::query()->create(['title' => ['id' => 'Karya pemuda Bone, langsung dari desanya', 'en' => 'Made by Bone youth, straight from their villages'], 'subtitle' => ['id' => 'Kopi Bontocani, sutra Sibulue, gula aren Kahu. Setiap pembelian ikut membiayai kegiatan Karang Taruna.', 'en' => 'Bontocani coffee, Sibulue silk, Kahu palm sugar. Every purchase funds youth programs.'], 'link' => '/produk', 'sort_order' => 1]);
        Banner::query()->create(['title' => ['id' => 'Pekan Karya Bone: potongan 20%', 'en' => 'Bone Craft Week: 20% off'], 'subtitle' => ['id' => 'Kopi, barongko, dan abon tuna. Kuota terbatas.', 'en' => 'Coffee, barongko and tuna floss. Limited quota.'], 'link' => '/produk?sort=terlaris', 'sort_order' => 2]);
        Banner::query()->create(['title' => ['id' => "Lipa' Sabbe tenun tangan", 'en' => 'Hand-woven silk sarongs'], 'subtitle' => ['id' => 'Satu lembar butuh tiga minggu di alat tenun ibu-ibu Pakkasalo.', 'en' => 'Each piece takes three weeks on the looms of Pakkasalo.'], 'link' => '/kategori/kain-sutra', 'sort_order' => 3]);

        $posts = [
            ['Pelatihan foto produk untuk 30 pemuda desa', 'dokumentasi', 'Watampone', 'Karang Taruna dari 12 desa belajar memotret produk dengan HP: cahaya jendela, latar polos, dan sudut 45 derajat. Hasil fotonya langsung dipakai di Boka.'],
            ['Panen kopi Bontocani dimulai', 'berita', 'Bontocani', 'Musim petik merah tahun ini dimulai lebih awal. Karang Taruna Sipakatau menargetkan 2 ton biji kopi untuk disangrai bertahap sampai Maret.'],
            ['Pameran UMKM Hari Jadi Bone', 'dokumentasi', 'Lapangan Merdeka Watampone', 'Tujuh produsen Boka membuka lapak di pameran Hari Jadi Bone. Barongko Dapur Indo\' Bone habis di hari kedua.'],
        ];

        foreach ($posts as $i => [$title, $type, $location, $body]) {
            Post::query()->create([
                'type' => $type, 'title' => ['id' => $title], 'slug' => Str::slug($title), 'location' => $location,
                'excerpt' => ['id' => Str::limit($body, 120)], 'body' => ['id' => "<p>{$body}</p>"],
                'published_at' => now()->subDays(3 + $i * 9), 'author_id' => $author->id,
            ]);
        }

        $pages = [
            'tentang' => ['Tentang Boka', '<p>Boka (Bone Karya) adalah toko daring milik Karang Taruna Kabupaten Bone. Kami menjual produk Karang Taruna desa dan UMKM, lalu mencantumkan nama produsennya di setiap produk.</p><p>Fase pertama ini, Admin Boka mengelola seluruh transaksi. Produsen menerima laporan penjualan per periode sebagai dasar pembagian hasil.</p>'],
            'kebijakan-privasi' => ['Kebijakan Privasi', '<p>Kami hanya meminta nama, email, nomor WhatsApp, dan alamat kirim. Kami tidak meminta NIK atau tanggal lahir.</p><p>Anda bisa mengunduh data Anda dan menghapus akun dari halaman Profil. Penghapusan menganonimkan identitas Anda, tetapi catatan pesanan tetap kami simpan sebagai catatan keuangan sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</p><p>Jika terjadi kebocoran data, kami memberi tahu Anda secara tertulis paling lambat 3 x 24 jam.</p>'],
            'syarat-ketentuan' => ['Syarat dan Ketentuan', '<p>Harga tercantum dalam rupiah. Pesanan wajib dibayar sebelum batas waktu yang tertera di halaman pesanan. Pesanan yang lewat batas otomatis kedaluwarsa dan stok kembali ke toko.</p>'],
            'kebijakan-pengembalian' => ['Kebijakan Pengembalian', '<p>Laporkan barang rusak atau tidak sesuai dalam 2 x 24 jam setelah diterima, sertakan foto dan video unboxing. Kami mengembalikan dana hanya ke rekening atas nama Anda atau nama pengirim pada bukti transfer.</p>'],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            Page::query()->create(['slug' => $slug, 'title' => ['id' => $title], 'body' => ['id' => $body]]);
        }
    }

    /** Beberapa pesanan selesai beserta ulasan terverifikasi, dan log pencarian untuk rekomendasi. */
    private function history(User $buyer, array $products): void
    {
        $address = $buyer->addresses()->create([
            'label' => 'Rumah', 'recipient' => $buyer->name, 'phone' => $buyer->phone,
            'destination_id' => 'bone:tanete-riattang', 'district' => 'Tanete Riattang',
            'destination_label' => 'Kec. Tanete Riattang, Kab. Bone, Sulawesi Selatan',
            'full_address' => 'Jl. Ahmad Yani No. 7, Kel. Manurunge', 'postal_code' => '92713', 'is_default' => true,
        ]);
        $bank = BankAccount::query()->first();

        $reviews = [
            ['kopi-bubuk-robusta-bontocani', 5, 'Wangi sekali waktu dibuka. Saya seduh tubruk, pahitnya pas dan tidak asam.'],
            ['barongko-pisang-khas-bone', 5, 'Sampai di rumah masih dingin dan lembut. Rasanya seperti buatan nenek.'],
            ['gula-aren-cetak-kahu', 4, 'Gulanya keras dan wangi. Kemasan bisa lebih rapat.'],
            ['abon-ikan-tuna-bajoe', 5, 'Abonnya kering, tidak berminyak. Anak saya suka yang original.'],
        ];

        foreach ($reviews as $i => [$slug, $rating, $body]) {
            $product = $products[$slug];
            $variant = $product->variants()->first();
            $order = Order::query()->create([
                'code' => 'BN-'.now()->subDays(20 - $i)->format('ymd').'-SEED'.$i,
                'user_id' => $buyer->id, 'status' => OrderStatus::Selesai,
                'subtotal' => $variant->price, 'shipping_cost' => 12_000, 'unique_code' => 100 + $i,
                'grand_total' => $variant->price + 12_000 + 100 + $i, 'bank_account_id' => $bank->id,
                'bank_snapshot' => $bank->snapshot(), 'address_snapshot' => $address->snapshot(),
                'idempotency_key' => 'seed-'.$i, 'completed_at' => now()->subDays(10 - $i),
                'created_at' => now()->subDays(20 - $i),
            ]);
            $shipment = $order->shipments()->create(['origin_id' => $product->origin_id, 'origin_snapshot' => $product->origin->snapshot(), 'method' => 'lokal', 'courier' => 'Kurir Lokal Boka', 'weight_gram' => $variant->weight_gram, 'cost' => 12_000, 'status' => 'terkirim', 'receiver_name' => $buyer->name, 'shipped_at' => now()->subDays(15 - $i)]);
            $item = $order->items()->create(['shipment_id' => $shipment->id, 'variant_id' => $variant->id, 'product_id' => $product->id, 'product_name' => $product->getTranslation('name', 'id'), 'variant_name' => $variant->label(), 'unit_price' => $variant->price, 'qty' => 1, 'weight_gram' => $variant->weight_gram]);
            Review::query()->create(['order_item_id' => $item->id, 'product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => $rating, 'body' => $body]);
            $product->update(['rating_avg' => $rating, 'rating_count' => 1]);
        }

        $searches = ['kopi' => 'kopi-bubuk-robusta-bontocani', 'kopi robusta' => 'kopi-biji-robusta-sangrai', 'barongko' => 'barongko-pisang-khas-bone', 'gula aren' => 'gula-aren-cetak-kahu', 'madu' => 'madu-hutan-libureng', 'abon' => 'abon-ikan-tuna-bajoe', 'sarung sutra' => null, 'dodol' => null];

        foreach ($searches as $query => $slug) {
            foreach (range(1, $slug ? 6 : 3) as $n) {
                SearchLog::query()->create([
                    'session_id' => 'seed-'.$n, 'query' => $query, 'results_count' => $slug ? 2 : 0,
                    'clicked_product_id' => $slug ? $products[$slug]->id : null,
                    'category_id' => $slug ? $products[$slug]->category_id : null,
                    'created_at' => now()->subDays($n * 2),
                ]);
            }
        }
    }
}
