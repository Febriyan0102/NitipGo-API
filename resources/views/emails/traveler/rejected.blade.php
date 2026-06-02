{{-- resources/views/emails/traveler/rejected.blade.php --}}
@component('mail::message')
# Halo, {{ $name }}

Mohon maaf, pendaftaran kamu sebagai Traveler **belum dapat disetujui** saat ini.

---

**Alasan penolakan:**

{{ $reason }}

---

**Solusi agar bisa disetujui:**

{{ $solution }}

---

Kamu bisa mendaftar ulang setelah memperbaiki hal-hal di atas.

@component('mail::button', ['url' => config('app.frontend_url').'/register/traveler'])
Daftar Ulang
@endcomponent

Salam,<br>
**Tim {{ config('app_name') }}**
@endcomponent