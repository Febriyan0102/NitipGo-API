<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 40px 20px; }
        .container { max-width: 500px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; }
        .header { background: #4F46E5; padding: 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 22px; margin: 0; }
        .body { padding: 30px; color: #555; font-size: 15px; line-height: 1.7; }
        .btn { display: block; width: fit-content; margin: 24px auto; background: #4F46E5; color: #fff !important; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .note { font-size: 12px; color: #999; margin-top: 20px; }
        .footer { text-align: center; padding: 16px; font-size: 12px; color: #aaa; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Reset Password</h1>
        </div>
        <div class="body">
            <p>Halo,</p>
            <p>Klik tombol di bawah untuk reset password akun kamu. Link berlaku <strong>30 menit</strong>.</p>

            <a href="{{ $resetUrl }}" class="btn">Reset Password</a>

            <p class="note">
                Jika tombol tidak berfungsi, copy link ini ke browser:<br>
                <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
            </p>
            <p class="note">Jika kamu tidak merasa meminta reset password, abaikan email ini.</p>
        </div>
        <div class="footer">© {{ date('Y') }} {{ config('app.name') }}</div>
    </div>
</body>
</html>