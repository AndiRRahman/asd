<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(public string $url, public string $role) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Undangan menjadi Admin Boka');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin-invitation');
    }
}
