<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDeviceLoginNotification extends Notification
{
    use Queueable;

    protected string $ip;
    protected string $userAgent;

    public function __construct(string $ip, string $userAgent)
    {
        $this->ip        = $ip;
        $this->userAgent = $userAgent;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Login Detected on Your Account')
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line("A new login to your account was detected.")
            ->line("**IP Address:** {$this->ip}")
            ->line("**Device/Browser:** {$this->userAgent}")
            ->line("**Time:** " . now()->format('d M Y, H:i:s') . ' UTC')
            ->action('Secure My Account', url('/profile/security'))
            ->line("If this wasn't you, please change your password immediately.");
    }
}