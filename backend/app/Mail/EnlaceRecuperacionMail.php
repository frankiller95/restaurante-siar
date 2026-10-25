<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EnlaceRecuperacionMail extends Mailable
{
    public function __construct(
        public readonly string $nombres,
        public readonly string $enlace,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SIAR - Restablecer contraseña');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.enlace-recuperacion', with: [
            'minutos' => config('auth.passwords.users.expire'),
        ]);
    }
}
