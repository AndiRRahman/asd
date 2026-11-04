<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetOrder;
use App\Mcp\Tools\ListPendingVerifications;
use App\Mcp\Tools\LowStock;
use App\Mcp\Tools\SalesSummary;
use App\Mcp\Tools\SearchInsights;
use App\Mcp\Tools\SearchOrders;
use App\Mcp\Tools\SetTrackingNumber;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Server MCP opsional untuk operasional Admin (bagian 14). Sengaja tanpa tool untuk menyetujui pembayaran,
 * membatalkan pesanan, mengubah harga, atau mengelola akun: keputusan uang dan akses tetap di panel.
 */
#[Name('Boka Toko')]
#[Version('1.0.0')]
#[Instructions('Asisten operasional toko Boka (Bone Karya). Semua nominal dalam rupiah bulat; semua tanggal masukan dan keluaran dalam WITA (Asia/Makassar). Status pesanan: menunggu_pembayaran, menunggu_verifikasi, diproses, dikirim, selesai, kedaluwarsa, dibatalkan. Untuk pertanyaan tentang antrean verifikasi pakai toko_list_pending_verifications; untuk detail pakai toko_get_order. Menyetujui/menolak pembayaran, membatalkan pesanan, dan mengubah harga tidak tersedia di sini; arahkan Admin ke panel /panel.')]
class TokoServer extends Server
{
    protected array $tools = [
        ListPendingVerifications::class,
        GetOrder::class,
        SearchOrders::class,
        SalesSummary::class,
        LowStock::class,
        SearchInsights::class,
        SetTrackingNumber::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
