@echo off
rem Klik dua kali untuk menjalankan semua tes otomatis Boka. Lihat scripts\uji.ps1.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\uji.ps1"
pause
