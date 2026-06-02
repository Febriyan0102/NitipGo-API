<?php

namespace App\Mail;

use App\Models\TravelerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TravelerRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TravelerRequest $request,
        public string $reason,
        public string $solution,
    ) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.traveler.rejected',
            with: [
                'name'     => $this->request->name,
                'reason'   => $this->reason,
                'solution' => $this->solution,
            ],
        );
    }
}