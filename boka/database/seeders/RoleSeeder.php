<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hak akses disimpan sebagai data (bagian 2), sehingga peran baru bisa ditambah tanpa mengubah kode.
 * Super Admin melewati semua pemeriksaan izin lewat Gate::before di AppServiceProvider.
 */
class RoleSeeder extends Seeder
{
    public const ADMIN_PERMISSIONS = [
        'panel.akses', 'produk.kelola', 'stok.kelola', 'pesanan.kelola', 'promosi.kelola',
        'konten.kelola', 'pelanggan.kelola', 'laporan.lihat',
    ];

    public const TRANSACTION_PERMISSIONS = [
        'pembayaran.verifikasi', 'refund.proses', 'laporan.keuangan', 'ekspor.data_pribadi',
    ];

    public const SUPER_PERMISSIONS = ['admin.kelola', 'pengaturan.kelola', 'audit.lihat_semua'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...self::ADMIN_PERMISSIONS, ...self::TRANSACTION_PERMISSIONS, ...self::SUPER_PERMISSIONS] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('pembeli', 'web');
        Role::findOrCreate('admin', 'web')->syncPermissions(self::ADMIN_PERMISSIONS);
        Role::findOrCreate('admin_transaksi', 'web')->syncPermissions([...self::ADMIN_PERMISSIONS, ...self::TRANSACTION_PERMISSIONS]);
        Role::findOrCreate('super_admin', 'web')->syncPermissions([...self::ADMIN_PERMISSIONS, ...self::TRANSACTION_PERMISSIONS, ...self::SUPER_PERMISSIONS]);
    }
}
