<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Dipanggil oleh scripts/mulai.ps1. Memeriksa ekstensi PHP, membuat database jika belum ada,
 * lalu mencetak satu kata status: "baru" (belum dimigrasi), "kosong" (tabel ada, belum ada data), atau "siap".
 * Exit code selain 0 berarti gagal; pesannya ditulis ke STDERR.
 */

$missing = array_filter(['gd', 'mbstring', 'fileinfo', 'openssl', 'curl'], fn ($ext) => ! extension_loaded($ext));

if ($missing) {
    fwrite(STDERR, 'Ekstensi PHP belum aktif: '.implode(', ', $missing).". Aktifkan di php.ini lalu ulangi.\n");
    exit(2);
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$config = config("database.connections.{$connection}");
$driver = $config['driver'];

if (! extension_loaded("pdo_{$driver}")) {
    fwrite(STDERR, "Ekstensi PHP pdo_{$driver} belum aktif. Aktifkan di php.ini lalu ulangi.\n");
    exit(2);
}

if ($driver === 'sqlite') {
    if (! file_exists($config['database'])) {
        touch($config['database']);
    }
} else {
    try {
        $pdo = new PDO("{$driver}:host={$config['host']};port={$config['port']}", $config['username'], $config['password']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        fwrite(STDERR, "Tidak bisa terhubung ke database {$config['host']}:{$config['port']} sebagai {$config['username']}.\n");
        fwrite(STDERR, 'Periksa DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD di .env. Pesan: '.$e->getMessage()."\n");
        exit(3);
    }
}

$schema = Schema::connection($connection);

if (! $schema->hasTable('migrations')) {
    echo 'baru';
} elseif (! $schema->hasTable('users') || DB::table('users')->count() === 0) {
    echo 'kosong';
} else {
    echo 'siap';
}
