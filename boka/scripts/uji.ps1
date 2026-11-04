# Menjalankan seluruh tes otomatis Boka (kriteria penerimaan bagian 15).
# Tes memakai database SQLite sementara di memori, jadi data di MySQL/MariaDB tidak tersentuh.

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Get-Command php -ErrorAction SilentlyContinue) -and (Test-Path 'C:\xampp\php\php.exe')) {
    $env:Path = "C:\xampp\php;$env:Path"
}
if (-not (Test-Path 'vendor\autoload.php')) {
    Write-Host 'Paket belum terpasang. Jalankan mulai.bat sekali dulu.' -ForegroundColor Red
    exit 1
}

Write-Host '>> Menjalankan tes otomatis (sekitar 1 menit)...' -ForegroundColor Yellow
& php artisan test --colors=always
$code = $LASTEXITCODE

if ($code -eq 0) {
    Write-Host "`nSEMUA TES LOLOS." -ForegroundColor Green
} else {
    Write-Host "`nADA TES YANG GAGAL. Lihat nama tes dan pesannya di atas." -ForegroundColor Red
}
exit $code
