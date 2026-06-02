# NitipGo API
Gambaran Proyek

NitipGo merupakan platform jasa titip berbasis perjalanan yang mempertemukan pelanggan yang ingin membeli atau mengirim barang dengan traveler yang memiliki rute perjalanan yang sesuai. Melalui sistem ini, customer dapat membuat permintaan titipan, sementara traveler dapat menerima dan mengelola pesanan selama perjalanan berlangsung.

| Komponen           | Teknologi                      |
| ------------------ | ------------------------------ |
| Backend Framework  | Laravel 11                     |
| Bahasa Pemrograman | PHP 8.2+                       |
| Authentication     | Laravel Sanctum & Google OAuth |
| Email Service      | Resend                         |
| Payment Gateway    | Midtrans Snap                  |
| API Documentation  | Scramble                       |
| Database           | MySQL / SQLite                 |
| Location Service   | stevebauman/location           |
# Fitur Sistem
- Manajemen Akun
- Registrasi akun customer dan traveler.
- Login menggunakan email maupun Google.
- Refresh token otomatis.
- Fitur lupa password dan reset password.
- Logout perangkat saat ini maupun seluruh perangkat.
- Pemberitahuan login dari perangkat baru.
# Customer
- Membuat pesanan titipan barang
- Melihat daftar perjalanan yang tersedia
- Melakukan pembayaran melalui Midtrans
- Memantau status pesanan
- Memberikan rating dan ulasan
- Mengirim laporan atau sezngketa transaksi
- Mengakses tiket bantuan
# Traveler
- Mengelola profil dan informasi pembayaran
- Membuat serta mengelola perjalanan
- Menerima atau menolak pesanan customer
- Melakukan pelacakan lokasi perjalanan
- Mengelola saldo dan proses penarikan dana
- Mengaktifkan booster perjalanan
- Menangani laporan dari customer
# Admin
Mengelola data pengguna dan traveler
Melakukan persetujuan pendaftaran
Mengatur konfigurasi sistem
Mengelola paket booster dan iklan
Menangani laporan sengketa
Mengelola transaksi dan keuangan platform
Mengelola FAQ serta tiket bantuan
Mengelola notifikasi sistem
## Instalasi Backend
Persyaratan
PHP 8.2 
Composer
Node.js dan NPM
MySQL atau SQLite
Langkah Instalasi
git clone <repository-url>
cd NitipGo-API

composer install
npm install

cp .env.example .env

php artisan key:generate

php artisan migrate

php artisan db:seed

Jalankan aplikasi:

php artisan serve
php artisan queue:work

Atau gunakan:

composer dev
Integrasi Layanan Eksternal
Google OAuth

Digunakan untuk proses autentikasi menggunakan akun Google.

# Resend

Digunakan untuk pengiriman email seperti:

Reset password
Aktivitas login
Notifikasi sistem

## Midtrans (untuk pembayaran order & booster)
Daftar di midtrans.com, masuk ke Dashboard > Settings > Access Keys.

Digunakan untuk:

Pembayaran pesanan customer
Pembelian booster traveler

## Struktur Database (Tabel Utama)
users — data akun customer & admin
travelers — data akun traveler
trips — trip yang dibuat traveler
trip_trackings — riwayat lokasi perjalanan
order_processes — proses order titipan
transactions — transaksi keuangan
payments — bukti pembayaran
payment_boosters — pembayaran booster
payout_accounts — akun payout traveler
withdraw_requests — permintaan penarikan saldo traveler
platform_withdraw_requests — penarikan saldo platform oleh admin
user_requests / traveler_requests — pendaftaran menunggu approval
ratings — penilaian
reports — laporan/dispute transaksi
boosters — paket booster trip
traveler_boosters — booster aktif milik traveler
advertisements / advertisement_payments — iklan & pembayarannya
help_tickets — tiket bantuan customer
notifications — notifikasi in-app
system_settings — konfigurasi sistem
login_activities — log aktivitas login
# Dokumentasi API

Dokumentasi endpoint tersedia setelah aplikasi dijalankan:

http://localhost:8000/docs/api

Dokumentasi dibuat secara otomatis menggunakan Scramble.


## Development Contributions

Beberapa pengembangan yang dilakukan pada proyek ini meliputi:

- Implementasi autentikasi pengguna, manajemen token, serta fitur forgot password dan reset password berbasis email.
- Integrasi sistem pembayaran menggunakan Midtrans untuk transaksi order dan fitur booster.
- Pengembangan sistem notifikasi untuk customer, traveler, dan admin.
- Implementasi fitur laporan/dispute transaksi beserta alur penanganannya.
- Pengelolaan data perjalanan (trip) dan pemesanan (order) pada sistem.
- Perbaikan dan pengembangan fitur Kelola Perjalanan (Trip Management), termasuk pengelolaan data trip, status perjalanan, dan integrasi dengan backend API.
- Analisis struktur database serta relasi antar tabel pada modul order, transaksi, dan fitur pendukung lainnya.
- Integrasi dashboard admin dan traveler dengan backend API untuk menampilkan data transaksi, order, monitoring aktivitas, dan statistik secara real-time.
- Pengembangan serta optimalisasi modul order, termasuk integrasi API, detail transaksi, dan sinkronisasi data dengan backend.
- Perbaikan bug, debugging, dan pengujian endpoint API menggunakan Postman.
- Penyempurnaan alur transaksi, komunikasi data antar modul, serta mekanisme pengiriman notifikasi antar pengguna.
- Peningkatan stabilitas sistem dan pengalaman pengguna melalui perbaikan tampilan serta alur penggunaan pada berbagai fitur frontend.
- Memperbaiki bug pada modul trip yang sebelumnya masih memungkinkan customer melakukan pemesanan meskipun tanggal dan waktu keberangkatan trip telah terlewati.
- Mengembangkan dan memperbaiki fitur Iklan (Advertisement), termasuk pengelolaan data iklan, proses approval, dan integrasi dengan sistem backend.



