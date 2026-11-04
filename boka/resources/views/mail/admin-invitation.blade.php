<x-mail::message>
# Undangan Admin Boka

Anda diundang mengelola toko Boka sebagai **{{ str_replace('_', ' ', $role) }}**.

Buka tautan di bawah untuk mengatur nama dan password Anda sendiri. Tautan berlaku 48 jam dan hanya bisa dipakai sekali.

<x-mail::button :url="$url">
Terima undangan
</x-mail::button>

Setelah masuk, Anda wajib mengaktifkan autentikasi dua langkah (2FA) sebelum membuka panel.

Jika Anda tidak merasa diundang, abaikan email ini.
</x-mail::message>
