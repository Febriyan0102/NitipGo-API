<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    public function __construct(string $token)
    {
        $this->resetUrl = env('FRONTEND_URL') . '/reset-password?token=' . $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Password - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            // $resetUrl otomatis tersedia di blade karena public property
        );
    }

    public function attachments(): array
    {
        return [];
    }
}