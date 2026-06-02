<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            // ── Umum ──────────────────────────────────────────────────────────
            [
                'category'   => 'Umum',
                'question'   => 'Apa itu NitipGo?',
                'answer'     => 'NitipGo adalah platform jasa titip dan logistik berbasis traveler. Kamu bisa menitipkan pembelian barang atau pengiriman kepada traveler yang sedang melakukan perjalanan sesuai rutenya.',
            ],
            [
                'category'   => 'Umum',
                'question'   => 'Apakah NitipGo aman digunakan?',
                'answer'     => 'Ya. Setiap traveler di NitipGo telah melalui proses verifikasi identitas. Selain itu, sistem pembayaran kami menggunakan escrow — dana hanya diteruskan ke traveler setelah order selesai.',
            ],
            [
                'category'   => 'Umum',
                'question'   => 'Bagaimana cara memulai menggunakan NitipGo?',
                'answer'     => 'Daftar akun, pilih perjalanan traveler yang sesuai rutenya, buat order, lakukan pembayaran, dan pantau statusnya secara real-time di halaman Daftar Order.',
            ],
            [
                'category'   => 'Umum',
                'question'   => 'Apakah ada biaya pendaftaran?',
                'answer'     => 'Tidak ada biaya pendaftaran. Mendaftar dan menggunakan NitipGo sepenuhnya gratis. Biaya hanya dikenakan saat kamu membuat order, sesuai tarif yang disepakati dengan traveler.',
            ],

            // ── Order ─────────────────────────────────────────────────────────
            [
                'category'   => 'Order',
                'question'   => 'Bagaimana cara membuat order?',
                'answer'     => 'Buka halaman Perjalanan, pilih traveler dengan rute yang sesuai, klik "Buat Order", isi detail barang, lalu konfirmasi. Traveler akan menerima notifikasi dan bisa menyetujui atau menolak ordermu.',
            ],
            [
                'category'   => 'Order',
                'question'   => 'Bagaimana cara cek status order?',
                'answer'     => 'Buka halaman Daftar Order dan klik order yang ingin dicek. Status akan diperbarui otomatis setiap ada perubahan dari traveler: Menunggu, Diproses, Dalam Perjalanan, hingga Selesai.',
            ],
            [
                'category'   => 'Order',
                'question'   => 'Apakah saya bisa membatalkan order?',
                'answer'     => 'Order bisa dibatalkan selama statusnya masih "Menunggu Konfirmasi" dan traveler belum mengambil. Jika traveler sudah mengkonfirmasi, pembatalan memerlukan persetujuan kedua pihak.',
            ],
            [
                'category'   => 'Order',
                'question'   => 'Kenapa order saya dibatalkan otomatis?',
                'answer'     => 'Order dibatalkan otomatis jika tidak ada traveler yang mengambil hingga batas waktu yang ditentukan, atau jika traveler membatalkan karena alasan tertentu. Kamu bisa membuat order baru setelahnya.',
            ],
            [
                'category'   => 'Order',
                'question'   => 'Berapa lama traveler harus merespons order saya?',
                'answer'     => 'Traveler memiliki waktu maksimal 24 jam untuk merespons ordermu. Jika tidak ada respons, order akan otomatis dibatalkan dan kamu bisa mencoba traveler lain.',
            ],
            [
                'category'   => 'Order',
                'question'   => 'Apakah bisa ganti traveler setelah order dikonfirmasi?',
                'answer'     => 'Tidak bisa langsung ganti traveler setelah dikonfirmasi. Kamu perlu membatalkan order terlebih dahulu (jika masih memungkinkan), lalu membuat order baru dengan traveler lain.',
            ],

            // ── Pembayaran ────────────────────────────────────────────────────
            [
                'category'   => 'Pembayaran',
                'question'   => 'Metode pembayaran apa saja yang tersedia?',
                'answer'     => 'NitipGo mendukung berbagai metode pembayaran melalui Midtrans: transfer bank (BCA, BNI, Mandiri), e-wallet (GoPay, OVO, Dana), QRIS, dan kartu kredit/debit.',
            ],
            [
                'category'   => 'Pembayaran',
                'question'   => 'Kapan saya harus melakukan pembayaran?',
                'answer'     => 'Untuk order titip beli, pembayaran dilakukan setelah traveler mengkonfirmasi harga final barang. Untuk order pengiriman, pembayaran bisa dilakukan setelah order dikonfirmasi traveler.',
            ],
            [
                'category'   => 'Pembayaran',
                'question'   => 'Apakah saya dikenakan biaya pembatalan?',
                'answer'     => 'Tidak ada biaya pembatalan jika order dibatalkan sebelum traveler mengambil. Jika sudah dalam proses, kebijakan biaya bergantung pada kesepakatan dengan traveler.',
            ],
            [
                'category'   => 'Pembayaran',
                'question'   => 'Apakah dana saya aman sebelum order selesai?',
                'answer'     => 'Ya, dana yang kamu bayarkan disimpan dalam sistem escrow NitipGo dan hanya diteruskan ke traveler setelah order dinyatakan selesai dan kamu mengkonfirmasinya.',
            ],

            // ── Pengiriman ────────────────────────────────────────────────────
            [
                'category'   => 'Pengiriman',
                'question'   => 'Apa itu titip beli dan kirim barang?',
                'answer'     => 'Titip beli adalah layanan di mana traveler membelikan barang untukmu di kota asal mereka, lalu membawanya ke tujuan. Kirim barang adalah layanan di mana kamu mengirimkan barang yang sudah ada melalui traveler.',
            ],
            [
                'category'   => 'Pengiriman',
                'question'   => 'Bagaimana cara mengetahui posisi barang saya?',
                'answer'     => 'Kamu bisa memantau status order di halaman Daftar Order. Jika order sedang dalam perjalanan, tombol tracking akan muncul untuk melihat posisi terkini.',
            ],
            [
                'category'   => 'Pengiriman',
                'question'   => 'Apa yang terjadi jika barang rusak atau tidak sesuai?',
                'answer'     => 'Segera foto kondisi barang dan laporkan melalui menu Laporan pada halaman detail order dalam 24 jam setelah barang diterima. Tim kami dan traveler akan menyelesaikan masalah ini.',
            ],
            [
                'category'   => 'Pengiriman',
                'question'   => 'Apa yang dimaksud dengan titik COD?',
                'answer'     => 'Titik COD adalah lokasi pengumpulan dan pengambilan barang yang disepakati antara kamu dan traveler. Lokasi ini tersedia di halaman detail order beserta link Google Maps-nya.',
            ],
            [
                'category'   => 'Pengiriman',
                'question'   => 'Bagaimana jika traveler tidak bisa dihubungi?',
                'answer'     => 'Jika traveler tidak merespons lebih dari 24 jam, gunakan menu Bantuan untuk melaporkan masalah. Tim kami akan menghubungi traveler dan menindaklanjuti dalam 1×24 jam.',
            ],

            // ── Akun ──────────────────────────────────────────────────────────
            [
                'category'   => 'Akun',
                'question'   => 'Bagaimana cara mendaftar sebagai traveler?',
                'answer'     => 'Daftar akun biasa terlebih dahulu, lalu masuk ke pengaturan profil dan pilih "Daftar sebagai Traveler". Kamu perlu melengkapi data diri dan verifikasi identitas sebelum bisa menerima order.',
            ],
            [
                'category'   => 'Akun',
                'question'   => 'Apakah saya bisa menjadi customer sekaligus traveler?',
                'answer'     => 'Ya, satu akun bisa digunakan untuk keduanya. Kamu bisa membuat order sebagai customer sekaligus menerima order sebagai traveler menggunakan akun yang sama.',
            ],
            [
                'category'   => 'Akun',
                'question'   => 'Bagaimana cara mengubah data profil?',
                'answer'     => 'Buka halaman Pengaturan > Profil, lalu edit informasi yang ingin diubah. Beberapa data seperti nama lengkap dan nomor identitas mungkin memerlukan verifikasi ulang.',
            ],
            [
                'category'   => 'Akun',
                'question'   => 'Apa yang harus dilakukan jika lupa password?',
                'answer'     => 'Klik "Lupa Password" di halaman login, masukkan email terdaftarmu, dan ikuti instruksi yang dikirimkan melalui email untuk membuat password baru.',
            ],
        ];

        $sortOrder = 1;

        foreach ($faqs as $faq) {
            Faq::create([
                'code'       => 'FAQ-' . strtoupper(Str::random(6)),
                'question'   => $faq['question'],
                'answer'     => $faq['answer'],
                'category'   => $faq['category'],
                'sort_order' => $sortOrder++,
                'is_active'  => true,
            ]);
        }
    }
}