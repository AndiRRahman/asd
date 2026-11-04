@echo off
rem Klik dua kali untuk menyiapkan dan menjalankan Boka. Lihat scripts\mulai.ps1.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\mulai.ps1" %*
pause
