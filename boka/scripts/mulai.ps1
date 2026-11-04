# Menyiapkan dan menjalankan Boka untuk dicoba di komputer lokal.
# Aman dijalankan berulang kali: langkah yang sudah beres akan dilewati.
#
#   mulai.bat            siapkan (jika perlu) lalu jalankan website
#   mulai.bat -Reset     hapus semua data lalu isi ulang data contoh
#   mulai.bat -Port 8080 pakai port lain

param(
    [switch]$Reset,
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

# Semua yang tampil di layar juga disimpan, agar galat bisa dibaca setelah jendela ditutup.
$logFile = Join-Path $root 'storage\logs\mulai.log'
Start-Transcript -Path $logFile -Force | Out-Null

function Step($text) { Write-Host "`n>> $text" -ForegroundColor Yellow }
function Ok($text) { Write-Host "   $text" -ForegroundColor Green }
function Fail($text) {
    Write-Host "`nGAGAL: $text" -ForegroundColor Red
    Write-Host "Catatan lengkap tersimpan di: $logFile"
    Stop-Transcript | Out-Null
    exit 1
}
# Galat PowerShell yang tidak terduga juga dilaporkan lewat Fail agar tercatat.
trap { Fail "Galat tak terduga: $($_.Exception.Message) (baris $($_.InvocationInfo.ScriptLineNumber))" }

# Keluaran perintah (termasuk pesan galatnya) dialirkan lewat PowerShell supaya ikut tercatat.
function Run($exe, [string[]]$arguments) {
    $ErrorActionPreference = 'Continue'
    & $exe @arguments 2>&1 | ForEach-Object { Write-Host "$_" }
    if ($LASTEXITCODE -ne 0) { Fail "Perintah gagal: $exe $($arguments -join ' ')" }
}
function PortOpen($hostName, $portNumber) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $attempt = $client.BeginConnect($hostName, $portNumber, $null, $null)
        return $attempt.AsyncWaitHandle.WaitOne(500) -and $client.Connected
    } catch { return $false } finally { $client.Close() }
}
function Newer($file, $than) {
    return -not (Test-Path $than) -or ((Get-Item $file).LastWriteTime -gt (Get-Item $than).LastWriteTime)
}

Write-Host '==============================================' -ForegroundColor DarkRed
Write-Host '  Boka (Bone Karya) - persiapan dan menjalankan' -ForegroundColor DarkRed
Write-Host '=============================================='

# 1. Alat yang dibutuhkan
Step 'Memeriksa PHP, Composer, Node.js'
if (-not (Get-Command php -ErrorAction SilentlyContinue) -and (Test-Path 'C:\xampp\php\php.exe')) {
    $env:Path = "C:\xampp\php;$env:Path"
}
foreach ($tool in 'php', 'composer', 'node', 'npm') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { Fail "$tool tidak ditemukan. Pasang dulu lalu ulangi." }
}
$phpVersion = (& php -r 'echo PHP_VERSION;')
if ([version]$phpVersion -lt [version]'8.3') { Fail "Butuh PHP 8.3 atau lebih baru (terpasang $phpVersion)." }
$nodeVersion = (& node -v).TrimStart('v')
if ([version]$nodeVersion -lt [version]'20.19') { Fail "Butuh Node.js 20.19 atau lebih baru (terpasang $nodeVersion)." }
Ok "PHP $phpVersion, Node $nodeVersion"

# 2. Dependensi
Step 'Memeriksa paket PHP (composer)'
if (-not (Test-Path 'vendor\autoload.php') -or (Newer 'composer.lock' 'vendor\composer\installed.json')) {
    Run composer @('install', '--no-interaction')
} else { Ok 'Sudah terpasang' }

Step 'Memeriksa paket JavaScript (npm)'
if (-not (Test-Path 'node_modules\.package-lock.json') -or (Newer 'package-lock.json' 'node_modules\.package-lock.json')) {
    Run npm @('install')
} else { Ok 'Sudah terpasang' }

# 3. Konfigurasi
Step 'Memeriksa file .env'
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    Ok '.env dibuat dari .env.example'
}
if (-not (Select-String -Path '.env' -Pattern '^APP_KEY=base64:' -Quiet)) {
    Run php @('artisan', 'key:generate', '--force')
}
$envValues = @{}
Get-Content '.env' | Where-Object { $_ -match '^\s*([A-Z_]+)=(.*)$' } | ForEach-Object { $envValues[$Matches[1]] = $Matches[2].Trim('"') }
Ok "Database: $($envValues['DB_CONNECTION'])"

# 4. Server database (MySQL/MariaDB XAMPP dinyalakan jika belum jalan)
if ($envValues['DB_CONNECTION'] -in 'mysql', 'mariadb') {
    $dbHost = $envValues['DB_HOST']; $dbPort = [int]$envValues['DB_PORT']
    Step "Memeriksa server database di ${dbHost}:${dbPort}"
    if (-not (PortOpen $dbHost $dbPort)) {
        if ($dbPort -eq 3306 -and (Test-Path 'C:\xampp\mysql\bin\mysqld.exe')) {
            Write-Host '   Belum jalan. Menyalakan MySQL XAMPP...'
            Start-Process 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini', '--standalone' -WindowStyle Hidden
            foreach ($i in 1..20) { if (PortOpen $dbHost $dbPort) { break }; Start-Sleep -Seconds 1 }
        }
        if (-not (PortOpen $dbHost $dbPort)) { Fail "Server database di ${dbHost}:${dbPort} tidak bisa dihubungi. Nyalakan MySQL (misalnya lewat XAMPP Control Panel) lalu ulangi." }
    }
    Ok 'Server database aktif'
}

# 5. Tabel dan data contoh
Step 'Menyiapkan database'
$ErrorActionPreference = 'Continue'
$dbOutput = @(& php 'scripts\siapkan-db.php' 2>&1 | ForEach-Object { "$_" })
$dbExit = $LASTEXITCODE
$ErrorActionPreference = 'Stop'
if ($dbExit -ne 0) {
    $dbOutput | ForEach-Object { Write-Host "   $_" -ForegroundColor Red }
    Fail 'Database belum bisa dipakai (lihat pesan di atas).'
}
$state = $dbOutput[-1]

if ($Reset) {
    $answer = Read-Host '   -Reset akan MENGHAPUS semua data lalu mengisi ulang data contoh. Lanjut? (y/n)'
    if ($answer -ne 'y') { Fail 'Dibatalkan.' }
    Run php @('artisan', 'migrate:fresh', '--seed', '--force')
} elseif ($state -eq 'baru') {
    Run php @('artisan', 'migrate', '--seed', '--force')
} elseif ($state -eq 'kosong') {
    # Tabel ada tapi belum ada akun sama sekali (misalnya percobaan sebelumnya terhenti di tengah):
    # tidak ada data sungguhan, jadi aman dibangun ulang dari awal.
    Run php @('artisan', 'migrate:fresh', '--seed', '--force')
} else {
    Run php @('artisan', 'migrate', '--force')
    Ok 'Data sudah ada; hanya menjalankan migrasi baru (jika ada)'
}

if (-not (Test-Path 'public\storage')) { Run php @('artisan', 'storage:link') }

# 6. Aset tampilan (dibangun ulang hanya jika kode tampilan berubah)
Step 'Memeriksa build tampilan'
$manifest = 'public\build\manifest.json'
$sources = @(Get-ChildItem 'resources\js', 'resources\css' -Recurse -File) + (Get-Item 'vite.config.ts', 'package-lock.json')
$latest = ($sources | Sort-Object LastWriteTime -Descending | Select-Object -First 1).LastWriteTime
if (-not (Test-Path $manifest) -or -not (Test-Path 'bootstrap\ssr\app.js') -or $latest -gt (Get-Item $manifest).LastWriteTime) {
    Run npm @('run', 'build:ssr')
} else { Ok 'Build sudah terbaru' }

Run php @('artisan', 'optimize:clear')
# Bangun ulang cache paket sekali di sini; jika empat proses di bawah membangunnya bersamaan,
# Windows mengunci file dan salah satunya gagal.
Run php @('artisan', 'package:discover')

# 7. Port
if (PortOpen '127.0.0.1' 13714) { try { & php artisan inertia:stop-ssr *> $null } catch {} }
while (PortOpen '127.0.0.1' $Port) { $Port++ }
$url = "http://localhost:$Port"

# 8. Jalankan
Write-Host ''
Write-Host '==============================================' -ForegroundColor Green
Write-Host "  Website: $url" -ForegroundColor Green
Write-Host "  Panel  : $url/panel"
Write-Host '=============================================='
Write-Host '  Akun contoh (password semua: password)'
Write-Host '    Pembeli          pembeli@boka.test'
Write-Host '    Admin            admin@boka.test'
Write-Host '    Admin Transaksi  transaksi@boka.test'
Write-Host '    Super Admin      super@boka.test'
Write-Host ''
Write-Host '  Setelah login, Admin langsung masuk panel; Pembeli ke beranda.'
Write-Host '  2FA sedang dimatikan (BOKA_TWO_FACTOR=false di .env).'
Write-Host '  Email (verifikasi, notifikasi) tidak dikirim sungguhan; isinya'
Write-Host '  tercatat di storage\logs\laravel.log.'
Write-Host ''
Write-Host '  Tekan Ctrl+C untuk menghentikan semua proses.' -ForegroundColor Yellow
Write-Host '==============================================' -ForegroundColor Green

# Buka browser begitu server siap.
Start-Job -ArgumentList $url -ScriptBlock {
    param($target)
    foreach ($i in 1..60) {
        try { Invoke-WebRequest "$target/up" -UseBasicParsing -TimeoutSec 2 | Out-Null; Start-Process $target; return } catch { Start-Sleep -Seconds 1 }
    }
} | Out-Null

& 'node_modules\.bin\concurrently.cmd' --names 'web,ssr,antrean,jadwal' --prefix-colors 'red,yellow,cyan,green' `
    "php artisan serve --host=127.0.0.1 --port=$Port" `
    'php artisan inertia:start-ssr' `
    'php artisan queue:work --tries=3' `
    'php artisan schedule:work'
