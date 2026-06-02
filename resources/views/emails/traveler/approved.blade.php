{{-- resources/views/emails/traveler/approved.blade.php --}}
@component('mail::message')
# Selamat, {{ $name }}!

Pendaftaran kamu sebagai **Traveler** telah **disetujui**. 

Akun kamu sudah aktif dan siap digunakan untuk menerima order perjalanan.

@component('mail::button', ['url' => config('app.frontend_url') . '/login', 'color' => 'success'])
Login Sekarang
@endcomponent

Salam hangat,<br>
**Tim {{ config('app.name') }}**
@endcomponent